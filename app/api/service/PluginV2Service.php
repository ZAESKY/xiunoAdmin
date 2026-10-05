<?php

namespace app\api\service;

use app\common\service\ApiErrorService;
use app\common\service\BaseService;
use app\common\service\LicenseAuthService;
use app\common\service\LicenseService;
use app\common\service\LicenseSignatureService;
use app\common\service\PluginStorageService;
use app\common\service\PluginPackageIdentityService;
use app\common\service\RateLimitService;
use app\common\service\SecureTicketService;
use app\user\service\UserPluginService;
use think\facade\Db;
use think\facade\Log;

/**
 * 主题插件市场 v2 服务。
 *
 * 数据仍来自授权中心现有插件表和 UserPluginService；本服务只负责授权站点
 * 的 HMAC 身份、公开字段整形以及免费/已购插件的一次性下载票据。
 */
class PluginV2Service extends BaseService
{
    private const TICKET_TTL = 300;

    private const CATEGORIES = [
        'feature' => '功能增强',
        'security' => '安全防护',
        'content' => '内容管理',
        'ui' => '界面主题',
        'payment' => '支付交易',
        'dev' => '开发工具',
        'analytics' => '统计分析',
        'social' => '社交互动',
        'other' => '其他',
    ];

    public function listing()
    {
        $guard = $this->guard('plugin_market_list', 120, 600);
        if (!$guard['ok']) {
            return $this->out($guard['code'], $guard['msg'], $guard['data']);
        }
        $auth = $guard['auth'];
        $license = $auth['license'];
        $in = $auth['body'];

        $page = qh_page_number($in['page'] ?? null);
        $limit = qh_page_limit($in['limit'] ?? null, 12, 48);
        $keyword = trim((string)($in['keyword'] ?? ''));
        if (strlen($keyword) > 100) {
            return $this->out('4500', '搜索关键词不能超过 100 个字符');
        }
        $category = trim((string)($in['category'] ?? ''));
        if ($category !== '' && !isset(self::CATEGORIES[$category])) {
            return $this->out('4500', '插件分类无效');
        }
        $priceType = trim((string)($in['price_type'] ?? ''));
        if ($priceType === 'all') {
            $priceType = '';
        }
        if (!in_array($priceType, ['', 'free', 'paid'], true)) {
            return $this->out('4500', '价格筛选值无效');
        }
        $sort = trim((string)($in['sort'] ?? 'default'));
        if ($sort === 'download') {
            $sort = 'downloads';
        }
        if (!in_array($sort, ['default', 'downloads', 'comments', 'rating', 'newest'], true)) {
            return $this->out('4500', '排序方式无效');
        }

        try {
            $paginator = (new UserPluginService())->marketList([
                'current_page' => $page,
                'limit' => $limit,
                'text' => $keyword,
                'category' => $category,
                'price_type' => $priceType,
                'sort' => $sort,
            ])->toArray();
            $items = is_array($paginator['data'] ?? null) ? $paginator['data'] : [];
            $purchasedIds = $this->purchasedPluginIds($license, array_column($items, 'id'));
            foreach ($items as &$item) {
                $this->normalizePlugin($item);
                $item['can_download'] = !empty($item['is_free']) || isset($purchasedIds[(int)$item['id']]);
            }
            unset($item);

            $data = [
                'items' => $items,
                'pagination' => [
                    'page' => (int)($paginator['current_page'] ?? $page),
                    'per_page' => (int)($paginator['per_page'] ?? $limit),
                    'last_page' => (int)($paginator['last_page'] ?? 1),
                    'total' => (int)($paginator['total'] ?? count($items)),
                ],
                'filters' => [
                    'categories' => $this->categoryOptions(),
                    'sorts' => ['default', 'downloads', 'rating', 'newest'],
                    'price_types' => ['all', 'free', 'paid'],
                ],
                'generated_at' => time(),
            ];
            return $this->signedOut($data, $auth, '');
        } catch (\Throwable $e) {
            Log::error('[QH-V2][plugin-list] ' . $e->getMessage());
            return $this->out('5000', '插件市场暂时不可用，请稍后重试');
        }
    }

    public function detail()
    {
        $guard = $this->guard('plugin_market_detail', 120, 600);
        if (!$guard['ok']) {
            return $this->out($guard['code'], $guard['msg'], $guard['data']);
        }
        $auth = $guard['auth'];
        $license = $auth['license'];
        $pluginId = (int)($auth['body']['plugin_id'] ?? 0);
        if ($pluginId <= 0) {
            return $this->out('4500', '插件 ID 无效');
        }

        try {
            $plugin = (new UserPluginService())->publicMarketDetail($pluginId);
            if ($plugin === null) {
                return $this->out('4501', '插件不存在或尚未上架');
            }
            $this->normalizePlugin($plugin, true);
            $plugin['can_download'] = $this->downloadEntitlement($plugin, $license)['allowed'];
            foreach (['related_plugin'] as $field) {
                if (isset($plugin[$field]) && is_array($plugin[$field])) {
                    $this->normalizePlugin($plugin[$field]);
                }
            }
            foreach (['author_plugins', 'referencing_plugins'] as $field) {
                if (!empty($plugin[$field]) && is_array($plugin[$field])) {
                    foreach ($plugin[$field] as &$item) {
                        $this->normalizePlugin($item);
                    }
                    unset($item);
                }
            }
            if (!empty($plugin['comments']) && is_array($plugin['comments'])) {
                foreach ($plugin['comments'] as &$comment) {
                    $comment['id'] = (int)($comment['id'] ?? 0);
                    $comment['rating'] = max(1, min(5, (int)($comment['rating'] ?? 0)));
                }
                unset($comment);
            }
            return $this->signedOut([
                'plugin' => $plugin,
                'purchase_url' => rtrim((string)SITE_URL, '/')
                    . '/user.php/UserPlugin/detail.html?id=' . $pluginId,
                'generated_at' => time(),
            ], $auth, '');
        } catch (\Throwable $e) {
            Log::error('[QH-V2][plugin-detail] id=' . $pluginId . ' ' . $e->getMessage());
            return $this->out('5000', '插件详情暂时不可用，请稍后重试');
        }
    }

    public function freeTicket()
    {
        $guard = $this->guard('plugin_market_ticket', 30, 3600);
        if (!$guard['ok']) {
            return $this->out($guard['code'], $guard['msg'], $guard['data']);
        }
        $auth = $guard['auth'];
        $license = $auth['license'];
        $in = $auth['body'];
        $pluginId = (int)($in['plugin_id'] ?? 0);
        $versionId = (int)($in['version_id'] ?? 0);
        $siteId = (string)($in['site_id'] ?? '');
        if ($pluginId <= 0 || $versionId < 0) {
            return $this->out('4500', '插件下载参数无效');
        }

        $plugin = Db::name('plugin')->where('id', $pluginId)->where('status', 1)->find();
        if (!$plugin) {
            return $this->out('4501', '插件不存在或尚未上架');
        }
        $entitlement = $this->downloadEntitlement($plugin, $license);
        if (!$entitlement['allowed']) {
            return $this->out('4502', '付费插件请前往轻鸿授权中心购买下载', [
                'purchase_url' => rtrim((string)SITE_URL, '/')
                    . '/user.php/UserPlugin/detail.html?id=' . $pluginId,
            ]);
        }

        $record = $plugin;
        $version = (string)$plugin['version'];
        if ($versionId > 0) {
            $record = Db::name('plugin_versions')
                ->where('id', $versionId)
                ->where('plugin_id', $pluginId)
                ->find();
            if (!$record) {
                return $this->out('4501', '插件版本不存在');
            }
            $version = (string)$record['version'];
        }

        try {
            (new PluginStorageService())->assertValidPackageRecord($record);
        } catch (\Throwable $e) {
            return $this->out('4503', '插件安装包不存在或暂不可下载');
        }
        $hash = strtolower((string)($record['package_hash'] ?? $record['file_hash'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            return $this->out('4503', '插件安装包缺少完整性校验信息');
        }
        $size = (int)($record['package_file_size'] ?? $record['file_size'] ?? 0);
        $pluginDir = PluginPackageIdentityService::resolveRecord($plugin);
        if ($pluginDir === '') {
            return $this->out('4503', '插件安装包缺少有效的安装目录标识，请管理员重新上传该版本');
        }
        $fileName = $this->safePackageName(
            (string)($record['package_file_name'] ?? ''),
            (string)$plugin['slug'],
            $version
        );

        try {
            $ticket = SecureTicketService::issue([
                'plugin_id' => $pluginId,
                'version_id' => $versionId,
                'kind' => 'plugin',
                'site_id' => $siteId,
            ], [
                'appid' => (int)$license['appid'],
                'auth_id' => 0,
                'ip' => $this->ip(),
            ], self::TICKET_TTL);
            Db::name('download_ticket')
                ->where('ticket_hash', hash('sha256', $ticket))
                ->update(['license_id' => (string)$license['license_id']]);
        } catch (\Throwable $e) {
            Log::error('[QH-V2][plugin-ticket] ' . $e->getMessage());
            return $this->out('5000', '下载凭证签发失败，请稍后重试');
        }

        return $this->signedOut([
            'ticket' => $ticket,
            'expires_in' => self::TICKET_TTL,
            'download_path' => '/api/v2/plugin/download',
            'plugin_id' => $pluginId,
            'version_id' => $versionId,
            'version' => $version,
            'plugin_dir' => $pluginDir,
            'package_file_name' => $fileName,
            'package_size' => $size,
            'package_sha256' => $hash,
        ], $auth, '');
    }

    public function download()
    {
        $ticket = trim((string)request()->param('ticket', ''));
        $siteId = trim((string)request()->param('site_id', ''));
        if ($ticket === '' || !preg_match('/^[a-f0-9]{64}$/D', $siteId)) {
            return $this->out('4301', '缺少或无效的下载凭证');
        }

        SecureTicketService::purgeExpired();
        $payload = SecureTicketService::consume($ticket, $this->ip());
        if ($payload === null
            || (string)($payload['kind'] ?? '') !== 'plugin'
            || !hash_equals((string)($payload['site_id'] ?? ''), $siteId)) {
            return $this->out('4301', '下载凭证无效、已使用或已过期');
        }

        $licenseId = (string)($payload['_license_id'] ?? '');
        $license = $licenseId === '' ? null : LicenseService::findByLicenseId($licenseId);
        if (!$license || LicenseService::effectiveStatus($license) !== LicenseService::STATUS_ACTIVE
            || LicenseService::findSite($licenseId, $siteId) === null) {
            return $this->out('4201', '授权或绑定站点当前不可用');
        }

        $pluginId = (int)($payload['plugin_id'] ?? 0);
        $versionId = (int)($payload['version_id'] ?? 0);
        $plugin = Db::name('plugin')->where('id', $pluginId)->where('status', 1)->find();
        if (!$plugin) {
            return $this->out('4302', '插件资源不存在或已下架');
        }
        $entitlement = $this->downloadEntitlement($plugin, $license);
        if (!$entitlement['allowed']) {
            return $this->out('4302', '当前授权账号尚未购买该付费插件');
        }

        $record = $plugin;
        $version = (string)$plugin['version'];
        if ($versionId > 0) {
            $record = Db::name('plugin_versions')
                ->where('id', $versionId)
                ->where('plugin_id', $pluginId)
                ->find();
            if (!$record) {
                return $this->out('4302', '插件版本不存在或已下架');
            }
            $version = (string)$record['version'];
        }

        $storage = new PluginStorageService();
        try {
            $storage->assertValidPackageRecord($record);
            $file = $storage->getDownloadFile($record);
        } catch (\Throwable $e) {
            Log::error('[QH-V2][plugin-download] resource id=' . $pluginId . ' ' . $e->getMessage());
            return $this->out('4503', '插件安装包不存在或暂不可下载');
        }

        $expectedHash = strtolower((string)($record['package_hash'] ?? $record['file_hash'] ?? ''));
        $expectedSize = (int)($record['package_file_size'] ?? $record['file_size'] ?? 0);
        $actualHash = is_file($file) ? strtolower((string)hash_file('sha256', $file)) : '';
        $actualSize = is_file($file) ? (int)filesize($file) : 0;
        if (!preg_match('/^[a-f0-9]{64}$/D', $expectedHash)
            || !hash_equals($expectedHash, $actualHash)
            || ($expectedSize > 0 && $expectedSize !== $actualSize)) {
            $storage->deleteTemporaryDownloadFile($file);
            Log::error('[QH-V2][plugin-download] package integrity mismatch id=' . $pluginId);
            return $this->out('4503', '插件安装包完整性校验失败');
        }

        try {
            Db::startTrans();
            Db::name('plugin_download')->insert([
                'plugin_id' => $pluginId,
                'plugin_version' => $version,
                'user_id' => 0,
                'app_id' => (int)$license['appid'],
                'order_id' => (int)$entitlement['order_id'],
                'ip' => $this->ip(),
                'created_at' => datetime(),
            ]);
            Db::name('plugin')->where('id', $pluginId)->inc('download_count')->update();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('[QH-V2][plugin-download] statistic id=' . $pluginId . ' ' . $e->getMessage());
        }

        $fileName = $this->safePackageName(
            (string)($record['package_file_name'] ?? ''),
            (string)$plugin['slug'],
            $version
        );
        $response = download($file, $fileName);
        if (($record['storage_driver'] ?? 'local') === 'oss'
            && method_exists($response, 'deleteFileAfterSend')) {
            $response->deleteFileAfterSend(true);
        }
        return $response;
    }

    private function guard(string $scope, int $limit, int $period): array
    {
        $auth = LicenseAuthService::authenticate();
        if (!$auth['ok']) {
            return ['ok' => false, 'code' => $auth['code'], 'msg' => $auth['msg'], 'data' => []];
        }
        $license = $auth['license'];
        if (LicenseService::effectiveStatus($license) !== LicenseService::STATUS_ACTIVE) {
            return ['ok' => false, 'code' => '4201', 'msg' => '授权当前不可用', 'data' => []];
        }
        $rl = RateLimitService::hit($scope, (string)$license['license_id'], $limit, $period);
        if (!$rl['ok']) {
            return [
                'ok' => false,
                'code' => '4290',
                'msg' => '请求过于频繁',
                'data' => ['retry_after' => (int)$rl['retry_after']],
            ];
        }
        return ['ok' => true, 'auth' => $auth];
    }

    private function normalizePlugin(array &$plugin, bool $detail = false): void
    {
        $plugin['plugin_dir'] = PluginPackageIdentityService::resolveRecord($plugin);
        unset($plugin['user_id'], $plugin['publish_type'], $plugin['publish_time']);
        foreach (['id', 'download_count', 'rating_count', 'comment_count', 'is_hot', 'is_recommend'] as $field) {
            if (array_key_exists($field, $plugin)) {
                $plugin[$field] = (int)$plugin[$field];
            }
        }
        if (isset($plugin['price'])) {
            $plugin['price'] = number_format((float)$plugin['price'], 2, '.', '');
            $plugin['is_free'] = (float)$plugin['price'] <= 0;
        }
        if (isset($plugin['rating_avg'])) {
            // 签名规范拒绝浮点数，统一使用固定两位小数字符串，避免跨平台表示歧义。
            $plugin['rating_avg'] = number_format((float)$plugin['rating_avg'], 2, '.', '');
        }
        foreach (['icon', 'cover', 'iconUrl', 'coverUrl'] as $field) {
            if (isset($plugin[$field])) {
                $plugin[$field] = $this->absoluteUrl((string)$plugin[$field]);
            }
        }
        if (!empty($plugin['images']) && is_array($plugin['images'])) {
            $plugin['images'] = array_values(array_filter(array_map(function ($url) {
                return $this->absoluteUrl((string)$url);
            }, $plugin['images'])));
        }
        if ($detail && isset($plugin['content'])) {
            $plugin['content'] = $this->absoluteHtmlUrls((string)$plugin['content']);
        }
    }

    /** 批量查询当前主题授权账号已经支付的插件，避免市场列表逐项查询。 */
    private function purchasedPluginIds(array $license, array $pluginIds): array
    {
        $userId = (int)($license['user_id'] ?? 0);
        $appId = (int)($license['appid'] ?? 0);
        $pluginIds = array_values(array_unique(array_filter(array_map('intval', $pluginIds))));
        if ($userId <= 0 || $appId <= 0 || empty($pluginIds)) {
            return [];
        }
        $ids = Db::name('plugin_order')
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->where('status', 1)
            ->whereIn('plugin_id', $pluginIds)
            ->column('plugin_id');
        $result = [];
        foreach ($ids as $id) { $result[(int)$id] = true; }
        return $result;
    }

    /** 付费包只授权给与主题授权同一账号、同一应用下的已支付订单。 */
    private function downloadEntitlement(array $plugin, array $license): array
    {
        if ((float)($plugin['price'] ?? 0) <= 0) {
            return ['allowed' => true, 'order_id' => 0];
        }
        $userId = (int)($license['user_id'] ?? 0);
        $appId = (int)($license['appid'] ?? 0);
        $pluginId = (int)($plugin['id'] ?? 0);
        if ($userId <= 0 || $appId <= 0 || $pluginId <= 0) {
            return ['allowed' => false, 'order_id' => 0];
        }
        $orderId = (int)Db::name('plugin_order')
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->where('status', 1)
            ->order('id', 'desc')
            ->value('id');
        return ['allowed' => $orderId > 0, 'order_id' => max(0, $orderId)];
    }

    private function absoluteUrl(string $url): string
    {
        $url = qh_safe_url($url, true);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https://#i', $url)) {
            return $url;
        }
        if (preg_match('#^http://#i', $url)) {
            return '';
        }
        return rtrim((string)SITE_URL, '/') . '/' . ltrim($url, '/');
    }

    private function absoluteHtmlUrls(string $html): string
    {
        return (string)preg_replace_callback(
            '/\b(src|href)\s*=\s*(["\'])(.*?)\2/i',
            function (array $match) {
                $url = trim((string)$match[3]);
                if ($url === '' || $url[0] === '#' || preg_match('#^(?:https://|mailto:)#i', $url)) {
                    return $match[0];
                }
                $absolute = $this->absoluteUrl($url);
                return $absolute === '' ? '' : $match[1] . '=' . $match[2] . $absolute . $match[2];
            },
            $html
        );
    }

    private function categoryOptions(): array
    {
        $out = [];
        foreach (self::CATEGORIES as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }
        return $out;
    }

    private function safePackageName(string $candidate, string $slug, string $version): string
    {
        $candidate = basename(str_replace('\\', '/', $candidate));
        if ($candidate === '' || strtolower((string)pathinfo($candidate, PATHINFO_EXTENSION)) !== 'zip') {
            $candidate = $slug . '_v' . $version . '.zip';
        }
        $candidate = preg_replace('/[^A-Za-z0-9._-]/', '_', $candidate);
        return $candidate !== '' ? $candidate : 'plugin.zip';
    }

    private function ip(): string
    {
        try {
            return (string)request()->ip();
        } catch (\Throwable $e) {
            return '0.0.0.0';
        }
    }

    private function out(string $code, string $msg, array $data = [])
    {
        return json(['code' => (int)$code, 'msg' => $msg, 'data' => $data]);
    }

    private function signedOut(array $data, array $auth, string $msg)
    {
        try {
            $envelope = LicenseSignatureService::signResponse($data, [
                'license_id' => (string)$auth['license']['license_id'],
                'site_id' => (string)($auth['body']['site_id'] ?? ''),
                'product_id' => (string)$auth['license']['product_id'],
                'nonce' => (string)$auth['nonce'],
            ]);
        } catch (\Throwable $e) {
            return json(ApiErrorService::fail($e, 'common.server_error'));
        }

        return json([
            'code' => 0,
            'msg' => $msg,
            'data' => $envelope['data'],
            'sig' => $envelope['sig'],
            'key_id' => $envelope['key_id'],
        ]);
    }
}
