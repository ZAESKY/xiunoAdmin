<?php

namespace app\api\service;

use app\common\model\NotificationModel;
use app\common\service\BaseService;
use app\common\service\PluginStorageService;
use app\common\service\RebateRiskService;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件中心API服务
 * @author QH授权系统
 * @since 2026-05-03
 */
class PluginApiService extends BaseService
{
    private $requestLimit = 60; // 每分钟最大请求数
    private $downloadTokenExpiry = 300; // 下载凭证有效期(秒)

    /**
     * 验证API请求
     */
    private function validateRequest()
    {
        $param = request()->param();
        $appid = !empty($param['appid']) ? intval($param['appid']) : null;
        $api_key = !empty($param['api_key']) ? $param['api_key'] : null;
        $timestamp = !empty($param['timestamp']) ? intval($param['timestamp']) : 0;
        $nonce = !empty($param['nonce']) ? $param['nonce'] : '';
        $sign = !empty($param['sign']) ? $param['sign'] : '';

        if (empty($appid)) {
            throw new Exception(t('app.appid_empty'));
        }
        if (empty($api_key)) {
            throw new Exception(t('plugin_api.api_key_required'));
        }

        // 验证时间戳，防止重放攻击（5分钟有效期）
        if (abs(time() - $timestamp) > 300) {
            throw new Exception(t('plugin_api.request_expired'));
        }

        /*
         * A-09 修复：nonce 与 sign 原先是「带了才校验」——调用方省略字段即可跳过
         * 全部防重放与完整性保护。现改为必填（可由 plugin_api_sign_required 配置回退）。
         */
        $signRequired = conf('plugin_api_sign_required');
        $signRequired = ($signRequired === null) ? true : ((int)$signRequired === 1);

        if ($signRequired && empty($nonce)) {
            throw new Exception(t('plugin_api.nonce_required'));
        }
        if ($signRequired && empty($sign)) {
            throw new Exception(t('plugin_api.signature_required'));
        }

        /*
         * 验证 nonce 防重放。
         *
         * 说明：这里仍是「检查后写入」，在极高并发下存在极小的 TOCTOU 窗口，
         * 同一 nonce 理论上可能被并发放行两次。ThinkPHP 的 Cache 抽象层
         * 没有跨驱动的原子 SETNX，彻底消除该窗口需要在 P2 换成 Redis
         * 原生 SET NX EX。当前窗口不影响本次修复的主要目标
         * （把 nonce 从「可选」变为「必填」）。
         */
        if (!empty($nonce)) {
            if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $nonce)) {
                throw new Exception(t('plugin_api.nonce_invalid'));
            }
            $nonceKey = 'api_nonce_' . hash('sha256', $appid . '|' . $nonce);
            if (Cache::has($nonceKey)) {
                throw new Exception(t('plugin_api.duplicate_request'));
            }
            Cache::set($nonceKey, 1, 600);
        }

        // 验证应用
        $appModel = new \app\admin\model\AppModel();
        $appInfo = $appModel->getInfo($appid);
        if (!$appInfo) {
            throw new Exception(t('app.not_exist'));
        }
        if (!hash_equals((string)$appInfo['api_key'], (string)$api_key)) {
            throw new Exception(t('app.api_key_error'));
        }
        if ($appInfo['status'] != 2) {
            throw new Exception(t('plugin_api.app_disabled'));
        }

        // 验证签名
        if (!empty($sign)) {
            $signParams = $param;
            unset($signParams['sign']);
            ksort($signParams);
            $canonical = http_build_query($signParams);

            // 目标算法：HMAC-SHA256。过渡期同时接受历史 md5(payload + api_key)。
            $expectedHmac   = hash_hmac('sha256', $canonical, (string)$appInfo['api_key']);
            $expectedLegacy = md5($canonical . $appInfo['api_key']);

            $algo = (string)(conf('plugin_api_sign_algo') ?: 'auto');
            $ok = hash_equals($expectedHmac, (string)$sign);
            if (!$ok && $algo !== 'hmac') {
                $ok = hash_equals($expectedLegacy, (string)$sign);
            }
            if (!$ok) {
                throw new Exception(t('plugin_api.signature_invalid'));
            }
        }

        // 频率限制
        $rateLimitKey = 'api_rate_' . $appid . '_' . date('YmdHi');
        $currentCount = Cache::get($rateLimitKey, 0);
        if ($currentCount >= $this->requestLimit) {
            throw new Exception(t('plugin_api.rate_limited'));
        }
        Cache::set($rateLimitKey, $currentCount + 1, 120);

        return $appInfo;
    }

    /**
     * 验证用户登录状态
     *
     * A-05 修复：原实现读取了 $storedToken 却从未与 $user_token 比较，
     * 实际校验退化为「该 user_id 存在」，任何持有 api_key 的调用方
     * 都能任意冒充用户下载已购付费插件、下单、评论评分。
     *
     * 现改为常量时间比对。配置 plugin_api_user_strict 可在应急时回退，
     * 但回退期间上述冒充风险会重新出现，仅供故障排查使用。
     */
    private function validateUser(int $expectedAppId = 0)
    {
        $param = request()->param();
        $user_id = !empty($param['user_id']) ? intval($param['user_id']) : null;
        $user_token = !empty($param['user_token']) ? (string)$param['user_token'] : '';

        if (empty($user_id) || $user_token === '') {
            throw new Exception(t('login.not_logged_in'));
        }

        $user = Db::name('user')->where('id', $user_id)->find();
        if (!$user) {
            throw new Exception(t('user.not_exist'));
        }
        if (isset($user['status']) && (int)$user['status'] !== 1) {
            Cache::delete(self::userTokenKey($user_id));
            throw new Exception(t('plugin_api.account_disabled'));
        }
        if ($expectedAppId > 0 && (int)($user['appid'] ?? 0) !== $expectedAppId) {
            throw new Exception(t('plugin_api.user_app_mismatch'));
        }

        $strict = conf('plugin_api_user_strict');
        $strict = ($strict === null) ? true : ((int)$strict === 1);

        if ($strict) {
            $storedToken = Cache::get(self::userTokenKey($user_id));
            if (empty($storedToken) || !hash_equals((string)$storedToken, $user_token)) {
                throw new Exception(t('plugin_api.login_state_invalid'));
            }
        }

        return $user;
    }

    private static function userTokenKey($userId): string
    {
        return 'plugin_user_token_' . (int)$userId;
    }

    /**
     * 签发插件中心用户令牌
     *
     * 此前系统中没有任何位置写入 plugin_user_token_*，
     * 也就是说不存在可用的合法令牌。本方法补上签发侧，
     * 使 validateUser() 的强校验具备可用路径。
     */
    public function authUser()
    {
        try {
            $appInfo = $this->validateRequest();

            $param = request()->param();
            $username = !empty($param['username']) ? trim((string)$param['username']) : '';
            $password = !empty($param['password']) ? (string)$param['password'] : '';

            if ($username === '' || $password === '') {
                throw new Exception(t('login.credentials_required'));
            }

            // 登录失败次数限制，防止在线撞库
            $failKey = 'plugin_auth_fail_' . hash('sha256', $appInfo['id'] . '|' . $username);
            if ((int)Cache::get($failKey, 0) >= 5) {
                throw new Exception(t('plugin_api.too_many_attempts'));
            }

            $user = Db::name('user')
                ->where('username', $username)
                ->where('appid', $appInfo['id'])
                ->find();

            // 无论账号是否存在都走同一分支，避免账号枚举
            $needsRehash = false;
            $passwordOk = !empty($user) && qh_password_verify($password, $user['password'], $needsRehash);
            if (!$passwordOk) {
                Cache::set($failKey, (int)Cache::get($failKey, 0) + 1, 900);
                throw new Exception(t('plugin_api.credentials_invalid'));
            }
            if (isset($user['status']) && (int)$user['status'] !== 1) {
                throw new Exception(t('plugin_api.account_disabled'));
            }

            if ($needsRehash) {
                $newHash = qh_password_make($password);
                Db::name('user')->where('id', $user['id'])->update(['password' => $newHash]);
                $user['password'] = $newHash;
            }

            Cache::delete($failKey);

            $token = bin2hex(random_bytes(32));
            Cache::set(self::userTokenKey($user['id']), $token, 7200);

            return json(message(t('login.success'), true, [
                'data' => [
                    'user_id'    => (int)$user['id'],
                    'user_token' => $token,
                    'expires_in' => 7200,
                ]
            ]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 获取插件列表
     */
    public function getList()
    {
        try {
            $appInfo = $this->validateRequest();

            $param = request()->param();
            $page = qh_page_number($param['page'] ?? null);
            $limit = qh_page_limit($param['limit'] ?? null, 12, 50);
            $keyword = !empty($param['keyword']) ? trim($param['keyword']) : '';
            $sort = !empty($param['sort']) ? $param['sort'] : 'default';
            $price_type = !empty($param['price_type']) ? $param['price_type'] : '';

            $where = [['status', '=', 1]]; // 只返回已上架的

            if (!empty($keyword)) {
                $keyword = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
                $where[] = ['name|description|author', 'like', '%' . $keyword . '%'];
            }

            if ($price_type === 'free') {
                $where[] = ['price', '=', 0];
            } elseif ($price_type === 'paid') {
                $where[] = ['price', '>', 0];
            }

            $order = ['sort' => 'desc', 'id' => 'desc'];
            if ($sort === 'download') {
                $order = ['download_count' => 'desc', 'id' => 'desc'];
            } elseif ($sort === 'rating') {
                $order = ['rating_avg' => 'desc', 'id' => 'desc'];
            } elseif ($sort === 'newest') {
                $order = ['published_at' => 'desc', 'id' => 'desc'];
            }

            $list = Db::name('plugin')
                ->where($where)
                ->field('id,name,slug,version,author,icon,price,download_count,rating_count,rating_avg,comment_count,is_hot,is_recommend,published_at')
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page,
                ]);

            $list = $list->toArray();
            foreach ($list['data'] as &$plugin) {
                $this->sanitizePublicPlugin($plugin);
            }
            unset($plugin);

            return json(message(t('plugin_action.get_success'), true, ['data' => $list]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 获取插件详情
     */
    public function getDetail()
    {
        try {
            $appInfo = $this->validateRequest();

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;

            if (empty($plugin_id)) {
                throw new Exception(t('plugin_action.id_required'));
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->field('id,name,slug,version,author,author_url,description,content,icon,images,price,download_count,rating_count,rating_avg,comment_count,is_hot,is_recommend,published_at,created_at')
                ->find();

            if (!$plugin) {
                throw new Exception(t('plugin_action.not_published'));
            }

            if (!empty($plugin['images'])) {
                $plugin['images'] = json_decode($plugin['images'], true);
            }

            // 检查当前用户是否已购买
            $user_id = !empty($param['user_id']) ? intval($param['user_id']) : 0;
            $plugin['is_purchased'] = false;
            if ($user_id > 0 || !empty($param['user_token'])) {
                $user = $this->validateUser((int)$appInfo['id']);
                $user_id = (int)$user['id'];
            }
            if ($user_id > 0 && $plugin['price'] > 0) {
                $purchase = Db::name('plugin_purchase')
                    ->where('plugin_id', $plugin_id)
                    ->where('user_id', $user_id)
                    ->where('app_id', $appInfo['id'])
                    ->find();
                $plugin['is_purchased'] = !empty($purchase);
            }

            // 如果是免费插件，标记为已购买
            if ($plugin['price'] == 0) {
                $plugin['is_purchased'] = true;
            }

            $this->sanitizePublicPlugin($plugin, true);

            return json(message(t('plugin_action.get_success'), true, ['data' => $plugin]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 创建插件订单
     */
    public function createOrder()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser((int)$appInfo['id']);

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;

            if (empty($plugin_id)) {
                throw new Exception(t('plugin_action.id_required'));
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception(t('plugin_action.not_published'));
            }

            if ($plugin['price'] <= 0) {
                throw new Exception(t('plugin_action.free_no_purchase'));
            }

            $developerId = intval($plugin['user_id'] ?? 0);
            if ($developerId > 0 && RebateRiskService::relatedAccountReason(intval($user['id']), $developerId) !== '') {
                throw new Exception(t('plugin_action.developer_purchase_forbidden'));
            }

            // 检查是否已购买
            $purchase = Db::name('plugin_purchase')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', $user['id'])
                ->where('app_id', $appInfo['id'])
                ->find();

            if ($purchase) {
                throw new Exception(t('plugin_action.already_purchased'));
            }

            // 检查是否有未支付的订单
            $pendingOrder = Db::name('plugin_order')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', $user['id'])
                ->where('app_id', $appInfo['id'])
                ->where('status', 0)
                ->where('created_at', '>', date('Y-m-d H:i:s', time() - 1800))
                ->find();

            if ($pendingOrder) {
                return json(message(t('plugin_action.get_success'), true, [
                    'data' => [
                        'order_no' => $pendingOrder['order_no'],
                        'price' => $pendingOrder['price'],
                        'plugin_name' => $pendingOrder['plugin_name'],
                    ]
                ]));
            }

            // 创建订单（价格以数据库为准）
            $orderNo = 'PL' . date('YmdHis') . strtoupper(bin2hex(random_bytes(6)));
            $orderData = [
                'order_no' => $orderNo,
                'plugin_id' => $plugin_id,
                'plugin_name' => $plugin['name'],
                'plugin_version' => $plugin['version'],
                'user_id' => $user['id'],
                'app_id' => $appInfo['id'],
                'price' => $plugin['price'],
                'status' => 0,
                'ip' => request()->ip(),
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ];

            Db::name('plugin_order')->insert($orderData);

            // 记录日志
            $content = [
                'Title' => '创建插件订单',
                'Result' => 'success',
                'Detail' => '用户' . $user['id'] . '创建插件订单:' . $orderNo,
            ];
            event('ActionLog', $content);

            return json(message(t('plugin_api.order_created'), true, [
                'data' => [
                    'order_no' => $orderNo,
                    'price' => $plugin['price'],
                    'plugin_name' => $plugin['name'],
                ]
            ]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 查询订单状态
     */
    public function queryOrder()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser((int)$appInfo['id']);

            $param = request()->param();
            $order_no = !empty($param['order_no']) ? trim($param['order_no']) : null;

            if (empty($order_no)) {
                throw new Exception(t('plugin_api.order_no_required'));
            }
            if (!preg_match('/^PL[A-Za-z0-9]{16,40}$/D', $order_no)) {
                throw new Exception(t('plugin_api.order_no_invalid'));
            }

            $order = Db::name('plugin_order')
                ->where('order_no', $order_no)
                ->where('app_id', $appInfo['id'])
                ->where('user_id', $user['id'])
                ->field('order_no,plugin_id,plugin_name,price,status,paid_at,created_at')
                ->find();

            if (!$order) {
                throw new Exception(t('plugin_order.not_found'));
            }

            return json(message(t('plugin_action.get_success'), true, ['data' => $order]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 获取下载凭证
     */
    public function getDownloadToken()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser((int)$appInfo['id']);

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;

            if (empty($plugin_id)) {
                throw new Exception(t('plugin_action.id_required'));
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception(t('plugin_action.not_published'));
            }

            try {
                (new PluginStorageService())->assertValidPackageRecord($plugin);
            } catch (\Throwable $e) {
                throw new Exception(t('plugin_action.file_not_found'));
            }

            $orderId = 0;

            // 付费插件检查购买记录
            if ($plugin['price'] > 0) {
                $purchase = Db::name('plugin_purchase')
                    ->where('plugin_id', $plugin_id)
                    ->where('user_id', $user['id'])
                    ->where('app_id', $appInfo['id'])
                    ->find();

                if (!$purchase) {
                    throw new Exception(t('plugin_action.not_purchased'));
                }
                $orderId = $purchase['order_id'];
            }

            // 生成临时下载凭证
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + $this->downloadTokenExpiry);

            Db::name('plugin_download_token')->insert([
                'token' => $token,
                'plugin_id' => $plugin_id,
                'user_id' => $user['id'],
                'app_id' => $appInfo['id'],
                'order_id' => $orderId,
                'ip' => request()->ip(),
                'used' => 0,
                'expires_at' => $expiresAt,
                'created_at' => datetime(),
            ]);

            return json(message(t('plugin_action.get_success'), true, [
                'data' => [
                    'token' => $token,
                    'expires_in' => $this->downloadTokenExpiry,
                ]
            ]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 下载插件
     */
    public function download()
    {
        try {
            $param = request()->param();
            $token = !empty($param['download_token']) ? trim((string)$param['download_token']) : '';

            if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
                throw new Exception(t('plugin_api.download_token_required'));
            }

            // 验证凭证
            $tokenRecord = Db::name('plugin_download_token')
                ->where('token', $token)
                ->find();

            if (!$tokenRecord) {
                throw new Exception(t('plugin_action.credential_invalid'));
            }

            if ($tokenRecord['used'] == 1) {
                throw new Exception(t('plugin_action.credential_used'));
            }

            if (strtotime($tokenRecord['expires_at']) < time()) {
                throw new Exception(t('plugin_action.credential_expired'));
            }

            $user = $this->validateUser((int)$tokenRecord['app_id']);
            if ((int)$tokenRecord['user_id'] !== (int)$user['id']) {
                throw new Exception(t('plugin_action.credential_account_mismatch'));
            }

            // 验证IP一致性
            if ($tokenRecord['ip'] !== request()->ip()) {
                throw new Exception(t('plugin_action.ip_mismatch'));
            }

            // 获取插件信息
            $plugin = Db::name('plugin')
                ->where('id', $tokenRecord['plugin_id'])
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception(t('plugin_action.unavailable'));
            }

            $storage = new PluginStorageService();
            $filePath = $storage->getDownloadFile($plugin);

            Db::startTrans();
            try {
                $claimed = Db::name('plugin_download_token')
                    ->where('id', $tokenRecord['id'])
                    ->where('used', 0)
                    ->where('expires_at', '>=', datetime())
                    ->update(['used' => 1, 'used_at' => datetime()]);
                if ($claimed !== 1) {
                    throw new Exception(t('plugin_action.credential_used_or_expired'));
                }
                Db::name('plugin_download')->insert([
                    'plugin_id' => $plugin['id'],
                    'plugin_version' => $plugin['version'],
                    'user_id' => $tokenRecord['user_id'],
                    'app_id' => $tokenRecord['app_id'],
                    'order_id' => $tokenRecord['order_id'],
                    'ip' => request()->ip(),
                    'created_at' => datetime(),
                ]);
                Db::name('plugin')->where('id', $plugin['id'])->inc('download_count')->update();
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }

            // 记录日志
            $content = [
                'Title' => '插件下载',
                'Result' => 'success',
                'Detail' => '用户' . $tokenRecord['user_id'] . '下载插件:' . $plugin['name'],
            ];
            event('ActionLog', $content);

            // 返回文件流
            $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$plugin['slug'] . '_v' . (string)$plugin['version']) . '.zip';

            if (preg_match('#^https?://#i', $filePath)) {
                $safeUrl = qh_safe_url($filePath, false);
                if ($safeUrl === '' || strtolower((string)parse_url($safeUrl, PHP_URL_SCHEME)) !== 'https') {
                    throw new Exception(t('plugin_action.unsafe_download_url'));
                }
                header('Location: ' . $safeUrl);
                exit;
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($filePath);
            $storage->deleteTemporaryDownloadFile($filePath);
            exit;
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 提交评论
     */
    public function submitComment()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser((int)$appInfo['id']);

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;
            $content = !empty($param['content']) ? trim($param['content']) : null;

            if (empty($plugin_id)) {
                throw new Exception(t('plugin_action.id_required'));
            }
            if (empty($content)) {
                throw new Exception(t('plugin_action.comment_required'));
            }
            if (mb_strlen($content) > 500) {
                throw new Exception(t('plugin_api.comment_too_long', ['max' => 500]));
            }

            // XSS过滤
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception(t('plugin_action.not_published'));
            }

            if (!empty($plugin['user_id']) && (int)$plugin['user_id'] === (int)$user['id']) {
                throw new Exception(t('plugin_action.comment_self_forbidden'));
            }
            if (!$this->hasPurchasedOrDownloaded($plugin_id, (int)$user['id'], (int)$appInfo['id'])) {
                throw new Exception(t('plugin_action.purchase_before_comment'));
            }

            // 每个用户在当前应用内只能评论一次，避免刷评论。
            $recentComment = Db::name('plugin_comment')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', $user['id'])
                ->where('app_id', $appInfo['id'])
                ->find();

            if ($recentComment) {
                throw new Exception(t('plugin_action.already_commented'));
            }

            Db::name('plugin_comment')->insert([
                'plugin_id' => $plugin_id,
                'user_id' => $user['id'],
                'app_id' => $appInfo['id'],
                'content' => $content,
                'rating' => 5,
                'status' => 0,
                'ip' => request()->ip(),
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ]);

            // 更新评论数量
            $pluginModel = new \app\admin\model\PluginModel();
            $pluginModel->updateCommentCount($plugin_id);

            try {
                if (!empty($plugin['user_id']) && intval($plugin['user_id']) !== intval($user['id'])) {
                    NotificationModel::add([
                        'user_id' => intval($plugin['user_id']),
                        'title' => t('plugin_action.new_comment_title'),
                        'content' => t('plugin_action.new_comment_content', ['plugin' => $plugin['name'], 'rating' => 5]),
                        'type' => 'plugin_comment',
                        'link' => '/UserPlugin/comments.html',
                        'variables' => ['plugin_name' => $plugin['name'], 'rating' => 5, 'comment_content' => $content],
                        'created_at' => datetime(),
                    ]);
                }
                $commenterName = (string)($user['username'] ?? t('plugin_action.unknown_user'));
                NotificationModel::add([
                    'user_id' => 0,
                    'title' => t('plugin_action.comment_pending_title'),
                    'content' => t('plugin_action.comment_pending_content', ['username' => $commenterName, 'plugin' => $plugin['name'], 'rating' => 5]),
                    'type' => 'plugin_comment_pending',
                    'link' => '/PluginComment/list.html',
                    'variables' => [
                        'username' => $commenterName,
                        'plugin_name' => $plugin['name'],
                        'rating' => 5,
                        'comment_content' => $content,
                    ],
                    'created_at' => datetime(),
                ]);
            } catch (\Throwable $e) {
            }

            return json(message(t('plugin_action.comment_submitted'), true));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 提交评分
     */
    public function submitRating()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser((int)$appInfo['id']);

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;
            $rating = !empty($param['rating']) ? intval($param['rating']) : null;

            if (empty($plugin_id)) {
                throw new Exception(t('plugin_action.id_required'));
            }
            if (empty($rating) || $rating < 1 || $rating > 5) {
                throw new Exception(t('plugin_action.rating_invalid'));
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception(t('plugin_action.not_published'));
            }
            if (!empty($plugin['user_id']) && (int)$plugin['user_id'] === (int)$user['id']) {
                throw new Exception(t('plugin_api.rating_self_forbidden'));
            }
            if (!$this->hasPurchasedOrDownloaded($plugin_id, (int)$user['id'], (int)$appInfo['id'])) {
                throw new Exception(t('plugin_api.purchase_before_rating'));
            }

            // 检查是否已评分（每个用户每个应用只能评一次）
            $existingRating = Db::name('plugin_rating')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', $user['id'])
                ->where('app_id', $appInfo['id'])
                ->find();

            if ($existingRating) {
                // 更新评分
                Db::name('plugin_rating')
                    ->where('id', $existingRating['id'])
                    ->update([
                        'rating' => $rating,
                        'updated_at' => datetime(),
                    ]);
            } else {
                // 新增评分
                Db::name('plugin_rating')->insert([
                    'plugin_id' => $plugin_id,
                    'user_id' => $user['id'],
                    'app_id' => $appInfo['id'],
                    'rating' => $rating,
                    'created_at' => datetime(),
                    'updated_at' => datetime(),
                ]);
            }

            // 更新插件评分统计
            $pluginModel = new \app\admin\model\PluginModel();
            $pluginModel->updateRatingStats($plugin_id);

            return json(message(t('plugin_api.rating_success'), true));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 获取评论列表
     */
    public function getComments()
    {
        try {
            $appInfo = $this->validateRequest();

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;
            $page = qh_page_number($param['page'] ?? null);
            $limit = qh_page_limit($param['limit'] ?? null, 10, 50);

            if (empty($plugin_id)) {
                throw new Exception(t('plugin_action.id_required'));
            }

            $list = Db::name('plugin_comment')
                ->where('plugin_id', $plugin_id)
                ->where('status', 1)
                ->field('id,user_id,content,rating,created_at')
                ->order('id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page,
                ]);

            $list = $list->toArray();
            foreach ($list['data'] as &$comment) {
                $comment['content'] = qh_plain_text($comment['content'] ?? '', 500);
            }
            unset($comment);

            return json(message(t('plugin_action.get_success'), true, ['data' => $list]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    /**
     * 检查是否已购买
     */
    public function checkPurchased()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser((int)$appInfo['id']);

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;

            if (empty($plugin_id)) {
                throw new Exception(t('plugin_action.id_required'));
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->field('id,price')
                ->find();

            if (!$plugin) {
                throw new Exception(t('plugin_action.not_found'));
            }

            $purchased = false;
            if ($plugin['price'] <= 0) {
                $purchased = true;
            } else {
                $purchase = Db::name('plugin_purchase')
                    ->where('plugin_id', $plugin_id)
                    ->where('user_id', $user['id'])
                    ->where('app_id', $appInfo['id'])
                    ->find();
                $purchased = !empty($purchase);
            }

            return json(message(t('plugin_action.get_success'), true, ['data' => ['purchased' => $purchased]]));
        } catch (\Exception $e) {
            return json(message(qh_public_exception_message($e, t('plugin_api.operation_failed')), false));
        }
    }

    private function hasPurchasedOrDownloaded(int $pluginId, int $userId, int $appId): bool
    {
        $purchase = Db::name('plugin_purchase')
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->find();
        if ($purchase) {
            return true;
        }
        return (bool)Db::name('plugin_download')
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->find();
    }

    private function sanitizePublicPlugin(array &$plugin, bool $includeRichText = false): void
    {
        foreach (['name' => 100, 'slug' => 100, 'version' => 50, 'author' => 100, 'description' => 1000] as $field => $limit) {
            if (isset($plugin[$field])) {
                $plugin[$field] = qh_plain_text($plugin[$field], $limit);
            }
        }
        if (isset($plugin['icon'])) {
            $plugin['icon'] = qh_safe_url($plugin['icon'], true);
        }
        if (isset($plugin['author_url'])) {
            $plugin['author_url'] = qh_safe_url($plugin['author_url'], false);
        }
        if ($includeRichText && isset($plugin['content'])) {
            $plugin['content'] = clean_rich_text($plugin['content']);
        }
        if (isset($plugin['images'])) {
            $images = is_array($plugin['images']) ? $plugin['images'] : json_decode((string)$plugin['images'], true);
            $plugin['images'] = is_array($images)
                ? array_values(array_filter(array_map(static fn($url) => qh_safe_url($url, true), $images)))
                : [];
        }
    }
}
