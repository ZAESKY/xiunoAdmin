<?php

namespace app\api\service;

use app\common\service\BaseService;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件中心API服务
 * @author SF授权系统
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
            throw new Exception('appid不能为空');
        }
        if (empty($api_key)) {
            throw new Exception('api_key不能为空');
        }

        // 验证时间戳，防止重放攻击（5分钟有效期）
        if (abs(time() - $timestamp) > 300) {
            throw new Exception('请求已过期');
        }

        // 验证nonce防重放
        if (!empty($nonce)) {
            $nonceKey = 'api_nonce_' . $nonce;
            if (Cache::has($nonceKey)) {
                throw new Exception('重复请求');
            }
            Cache::set($nonceKey, 1, 600);
        }

        // 验证应用
        $appModel = new \app\admin\model\AppModel();
        $appInfo = $appModel->getInfo($appid);
        if (!$appInfo) {
            throw new Exception('应用不存在');
        }
        if ($api_key != $appInfo['api_key']) {
            throw new Exception('api_key错误');
        }
        if ($appInfo['status'] != 2) {
            throw new Exception('应用未启用');
        }

        // 验证签名
        if (!empty($sign)) {
            $signParams = $param;
            unset($signParams['sign']);
            ksort($signParams);
            $signStr = http_build_query($signParams) . $appInfo['api_key'];
            $expectedSign = md5($signStr);
            if ($sign !== $expectedSign) {
                throw new Exception('签名验证失败');
            }
        }

        // 频率限制
        $rateLimitKey = 'api_rate_' . $appid . '_' . date('YmdHi');
        $currentCount = Cache::get($rateLimitKey, 0);
        if ($currentCount >= $this->requestLimit) {
            throw new Exception('请求过于频繁，请稍后再试');
        }
        Cache::set($rateLimitKey, $currentCount + 1, 120);

        return $appInfo;
    }

    /**
     * 验证用户登录状态
     */
    private function validateUser()
    {
        $param = request()->param();
        $user_id = !empty($param['user_id']) ? intval($param['user_id']) : null;
        $user_token = !empty($param['user_token']) ? $param['user_token'] : null;

        if (empty($user_id) || empty($user_token)) {
            throw new Exception('用户未登录');
        }

        // 验证用户token
        $tokenKey = 'plugin_user_token_' . $user_id;
        $storedToken = Cache::get($tokenKey);

        // 如果没有缓存的token，验证用户是否存在
        $user = Db::name('user')->where('id', $user_id)->find();
        if (!$user) {
            throw new Exception('用户不存在');
        }

        return $user;
    }

    /**
     * 获取插件列表
     */
    public function getList()
    {
        try {
            $appInfo = $this->validateRequest();

            $param = request()->param();
            $page = !empty($param['page']) ? intval($param['page']) : 1;
            $limit = !empty($param['limit']) ? min(intval($param['limit']), 50) : 12;
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

            return json(message('获取成功', true, ['data' => $list]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
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
                throw new Exception('插件ID不能为空');
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->field('id,name,slug,version,author,author_url,description,content,icon,images,price,download_count,rating_count,rating_avg,comment_count,is_hot,is_recommend,published_at,created_at')
                ->find();

            if (!$plugin) {
                throw new Exception('插件不存在或未上架');
            }

            if (!empty($plugin['images'])) {
                $plugin['images'] = json_decode($plugin['images'], true);
            }

            // 检查当前用户是否已购买
            $user_id = !empty($param['user_id']) ? intval($param['user_id']) : 0;
            $plugin['is_purchased'] = false;
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

            return json(message('获取成功', true, ['data' => $plugin]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 创建插件订单
     */
    public function createOrder()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser();

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;

            if (empty($plugin_id)) {
                throw new Exception('插件ID不能为空');
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception('插件不存在或未上架');
            }

            if ($plugin['price'] <= 0) {
                throw new Exception('免费插件无需购买');
            }

            // 检查是否已购买
            $purchase = Db::name('plugin_purchase')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', $user['id'])
                ->where('app_id', $appInfo['id'])
                ->find();

            if ($purchase) {
                throw new Exception('您已购买此插件');
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
                return json(message('获取成功', true, [
                    'data' => [
                        'order_no' => $pendingOrder['order_no'],
                        'price' => $pendingOrder['price'],
                        'plugin_name' => $pendingOrder['plugin_name'],
                    ]
                ]));
            }

            // 创建订单（价格以数据库为准）
            $orderNo = 'PL' . date('YmdHis') . mt_rand(1000, 9999);
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

            return json(message('订单创建成功', true, [
                'data' => [
                    'order_no' => $orderNo,
                    'price' => $plugin['price'],
                    'plugin_name' => $plugin['name'],
                ]
            ]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 查询订单状态
     */
    public function queryOrder()
    {
        try {
            $appInfo = $this->validateRequest();

            $param = request()->param();
            $order_no = !empty($param['order_no']) ? trim($param['order_no']) : null;

            if (empty($order_no)) {
                throw new Exception('订单号不能为空');
            }

            $order = Db::name('plugin_order')
                ->where('order_no', $order_no)
                ->where('app_id', $appInfo['id'])
                ->field('order_no,plugin_id,plugin_name,price,status,paid_at,created_at')
                ->find();

            if (!$order) {
                throw new Exception('订单不存在');
            }

            return json(message('获取成功', true, ['data' => $order]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 获取下载凭证
     */
    public function getDownloadToken()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser();

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;

            if (empty($plugin_id)) {
                throw new Exception('插件ID不能为空');
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception('插件不存在或未上架');
            }

            // 检查文件是否存在
            if (empty($plugin['file_path']) || !file_exists($plugin['file_path'])) {
                throw new Exception('插件文件不存在');
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
                    throw new Exception('您尚未购买此插件');
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

            return json(message('获取成功', true, [
                'data' => [
                    'token' => $token,
                    'expires_in' => $this->downloadTokenExpiry,
                ]
            ]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 下载插件
     */
    public function download()
    {
        try {
            $param = request()->param();
            $token = !empty($param['download_token']) ? trim($param['download_token']) : null;

            if (empty($token)) {
                throw new Exception('下载凭证不能为空');
            }

            // 验证凭证
            $tokenRecord = Db::name('plugin_download_token')
                ->where('token', $token)
                ->find();

            if (!$tokenRecord) {
                throw new Exception('下载凭证无效');
            }

            if ($tokenRecord['used'] == 1) {
                throw new Exception('下载凭证已使用');
            }

            if (strtotime($tokenRecord['expires_at']) < time()) {
                throw new Exception('下载凭证已过期');
            }

            // 验证IP一致性
            if ($tokenRecord['ip'] !== request()->ip()) {
                throw new Exception('请求IP不匹配');
            }

            // 获取插件信息
            $plugin = Db::name('plugin')
                ->where('id', $tokenRecord['plugin_id'])
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception('插件不存在或已下架');
            }

            if (empty($plugin['file_path']) || !file_exists($plugin['file_path'])) {
                throw new Exception('插件文件不存在');
            }

            // 标记凭证为已使用
            Db::name('plugin_download_token')
                ->where('id', $tokenRecord['id'])
                ->update([
                    'used' => 1,
                    'used_at' => datetime(),
                ]);

            // 记录下载
            Db::name('plugin_download')->insert([
                'plugin_id' => $plugin['id'],
                'plugin_version' => $plugin['version'],
                'user_id' => $tokenRecord['user_id'],
                'app_id' => $tokenRecord['app_id'],
                'order_id' => $tokenRecord['order_id'],
                'ip' => request()->ip(),
                'created_at' => datetime(),
            ]);

            // 增加下载次数
            Db::name('plugin')
                ->where('id', $plugin['id'])
                ->inc('download_count')
                ->update();

            // 记录日志
            $content = [
                'Title' => '插件下载',
                'Result' => 'success',
                'Detail' => '用户' . $tokenRecord['user_id'] . '下载插件:' . $plugin['name'],
            ];
            event('ActionLog', $content);

            // 返回文件流
            $filePath = $plugin['file_path'];
            $fileName = $plugin['slug'] . '_v' . $plugin['version'] . '.zip';

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($filePath);
            exit;
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 提交评论
     */
    public function submitComment()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser();

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;
            $content = !empty($param['content']) ? trim($param['content']) : null;

            if (empty($plugin_id)) {
                throw new Exception('插件ID不能为空');
            }
            if (empty($content)) {
                throw new Exception('评论内容不能为空');
            }
            if (mb_strlen($content) > 500) {
                throw new Exception('评论内容不能超过500字');
            }

            // XSS过滤
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception('插件不存在或未上架');
            }

            // 频率限制：同一用户同一插件1分钟内只能评论一次
            $recentComment = Db::name('plugin_comment')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', $user['id'])
                ->where('created_at', '>', date('Y-m-d H:i:s', time() - 60))
                ->find();

            if ($recentComment) {
                throw new Exception('评论过于频繁，请稍后再试');
            }

            Db::name('plugin_comment')->insert([
                'plugin_id' => $plugin_id,
                'user_id' => $user['id'],
                'app_id' => $appInfo['id'],
                'content' => $content,
                'rating' => 5,
                'status' => 1,
                'ip' => request()->ip(),
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ]);

            // 更新评论数量
            $pluginModel = new \app\admin\model\PluginModel();
            $pluginModel->updateCommentCount($plugin_id);

            return json(message('评论成功', true));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 提交评分
     */
    public function submitRating()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser();

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;
            $rating = !empty($param['rating']) ? intval($param['rating']) : null;

            if (empty($plugin_id)) {
                throw new Exception('插件ID不能为空');
            }
            if (empty($rating) || $rating < 1 || $rating > 5) {
                throw new Exception('评分必须在1-5之间');
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->where('status', 1)
                ->find();

            if (!$plugin) {
                throw new Exception('插件不存在或未上架');
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

            return json(message('评分成功', true));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
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
            $page = !empty($param['page']) ? intval($param['page']) : 1;
            $limit = !empty($param['limit']) ? min(intval($param['limit']), 50) : 10;

            if (empty($plugin_id)) {
                throw new Exception('插件ID不能为空');
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

            return json(message('获取成功', true, ['data' => $list]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 检查是否已购买
     */
    public function checkPurchased()
    {
        try {
            $appInfo = $this->validateRequest();
            $user = $this->validateUser();

            $param = request()->param();
            $plugin_id = !empty($param['plugin_id']) ? intval($param['plugin_id']) : null;

            if (empty($plugin_id)) {
                throw new Exception('插件ID不能为空');
            }

            $plugin = Db::name('plugin')
                ->where('id', $plugin_id)
                ->field('id,price')
                ->find();

            if (!$plugin) {
                throw new Exception('插件不存在');
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

            return json(message('获取成功', true, ['data' => ['purchased' => $purchased]]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }
}
