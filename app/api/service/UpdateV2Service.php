<?php

namespace app\api\service;

use app\common\service\ApiErrorService;
use app\common\service\BaseService;
use app\common\service\CryptoService;
use app\common\service\LicenseAuthService;
use app\common\service\LicenseService;
use app\common\service\LicenseSignatureService;
use app\common\service\ManifestService;
use app\common\service\PluginStorageService;
use app\common\service\RateLimitService;
use app\common\service\SecureTicketService;
use think\facade\Db;
use think\facade\Log;

/**
 * 更新与补丁接口 v2
 *
 *   POST /api/v2/update/check     查询可用更新（不含下载地址）
 *   POST /api/v2/update/ticket    换取一次性下载凭证
 *   GET  /api/v2/update/download  下载更新包（凭证认证）
 *   POST /api/v2/update/report    上报升级结果
 *   POST /api/v2/patch/check      查询适用的 Xiuno 核心兼容补丁（用户需求 ⑧⑨）
 *   POST /api/v2/patch/ticket     换取补丁下载凭证
 *   GET  /api/v2/patch/download   下载补丁包
 *
 * 关键安全属性：
 *   - check 不下发任何下载地址，凭证必须单独换取（A-07）
 *   - 清单与包哈希由 Ed25519 签名，客户端安装前验签（A-02）
 *   - 凭证单次、300 秒、绑定 license/site/IP
 *
 * @since 2026-08-16 P2/P3
 */
class UpdateV2Service extends BaseService
{
    private const TICKET_TTL = 300;

    /* ==================== 更新 ==================== */

    public function check()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out($auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        $rl = RateLimitService::hit('update_check', (string)$license['license_id'], 30, 3600);
        if (!$rl['ok']) {
            return $this->out('4290', '请求过于频繁', ['retry_after' => $rl['retry_after']]);
        }

        $status = LicenseService::effectiveStatus($license);
        if ($status !== LicenseService::STATUS_ACTIVE) {
            return $this->out('4201', '授权当前不可用：' . $status);
        }

        $siteId = (string)($in['site_id'] ?? '');
        if (LicenseService::findSite((string)$license['license_id'], $siteId) === null) {
            return $this->out('4006', '该站点未绑定此授权，请重新激活');
        }

        $currentBuild = (int)($in['current_build'] ?? 0);
        $channel = (string)$license['channel'];
        $bbsVersion = (string)($in['bbs_version'] ?? '');
        $phpVersion = (string)($in['php_version'] ?? '');

        $latest = $this->latestRelease((string)$license['product_id'], $channel, $currentBuild, $bbsVersion, $phpVersion);

        $latestManifest = $latest === null ? [] : (json_decode((string)$latest['manifest_json'], true) ?: []);
        $data = [
            'has_update'    => $latest !== null,
            'current_build' => $currentBuild,
            'channel'       => $channel,
            'latest'        => $latest === null ? null : [
                'build_no'         => (int)$latest['build_no'],
                'edition'          => (string)$latest['edition'],
                'release_note'     => (string)$latest['release_note'],
                'package_size'     => (int)$latest['package_size'],
                'package_sha256'   => (string)$latest['package_sha256'],
                'min_bbs_version'  => (string)$latest['min_bbs_version'],
                'max_bbs_version'  => (string)$latest['max_bbs_version'],
                'min_php_version'  => (string)$latest['min_php_version'],
                'is_security'      => (int)$latest['is_security'] === 1,
                'published_at'     => strtotime((string)$latest['published_at']),
                'package_type'     => (string)($latestManifest['package_type'] ?? 'full'),
                'migration_count'  => count((array)($latestManifest['migrations'] ?? [])),
            ],
        ];

        return $this->signedOut($data, $auth, '');
    }

    /**
     * 返回当前产品最新的正式完整包。该接口只允许已经关闭测试资格的绑定站点调用，
     * 且不要求目标 build 高于客户端，用于把测试版安全恢复到最新正式版。
     */
    public function stable()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out($auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        $rl = RateLimitService::hit('stable_target', (string)$license['license_id'], 10, 3600);
        if (!$rl['ok']) {
            return $this->out('4290', '正式版恢复请求过于频繁', ['retry_after' => $rl['retry_after']]);
        }
        if (LicenseService::effectiveStatus($license) !== LicenseService::STATUS_ACTIVE) {
            return $this->out('4201', '授权当前不可用');
        }
        if ((string)$license['channel'] !== LicenseService::CHANNEL_STABLE) {
            return $this->out('4203', '请先关闭测试通道，再恢复正式版文件');
        }

        $siteId = (string)($in['site_id'] ?? '');
        if (LicenseService::findSite((string)$license['license_id'], $siteId) === null) {
            return $this->out('4006', '该站点未绑定此授权，请重新激活');
        }

        $currentBuild = max(0, (int)($in['current_build'] ?? 0));
        $latest = $this->latestStableRelease(
            (string)$license['product_id'],
            (string)($in['bbs_version'] ?? ''),
            (string)($in['php_version'] ?? '')
        );
        if ($latest === null) {
            return $this->out('4202', '授权服务器尚未发布可用的正式完整包');
        }
        $manifest = $this->releaseManifest($latest);
        $data = [
            'current_build' => $currentBuild,
            'channel' => LicenseService::CHANNEL_STABLE,
            'requires_revert' => $currentBuild !== (int)$latest['build_no'],
            'latest' => [
                'build_no' => (int)$latest['build_no'],
                'edition' => (string)$latest['edition'],
                'release_note' => (string)$latest['release_note'],
                'package_size' => (int)$latest['package_size'],
                'package_sha256' => (string)$latest['package_sha256'],
                'package_type' => (string)($manifest['package_type'] ?? 'full'),
                'migration_count' => count((array)($manifest['migrations'] ?? [])),
            ],
        ];
        return $this->signedOut($data, $auth, '');
    }

    public function ticket()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out($auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        $rl = RateLimitService::hit('update_ticket', (string)$license['license_id'], 10, 3600);
        if (!$rl['ok']) {
            return $this->out('4290', '请求过于频繁', ['retry_after' => $rl['retry_after']]);
        }

        if (LicenseService::effectiveStatus($license) !== LicenseService::STATUS_ACTIVE) {
            return $this->out('4201', '授权当前不可用');
        }

        $siteId = (string)($in['site_id'] ?? '');
        if (LicenseService::findSite((string)$license['license_id'], $siteId) === null) {
            return $this->out('4006', '该站点未绑定此授权，请重新激活');
        }

        $buildNo = (int)($in['build_no'] ?? 0);
        $release = Db::name('release')
            ->where('product_id', $license['product_id'])
            ->where('build_no', $buildNo)
            ->where('status', 1)
            ->find();

        if (empty($release)) {
            return $this->out('4202', '该版本不存在或未发布');
        }
        if ((string)$release['channel'] === LicenseService::CHANNEL_BETA
            && (string)$license['channel'] !== LicenseService::CHANNEL_BETA) {
            return $this->out('4203', '当前未加入测试通道');
        }
        if (empty($release['manifest_sig'])) {
            return $this->out('4204', '该版本尚未完成签名，暂不可下载');
        }
        $releaseManifest = $this->releaseManifest($release);
        if ($releaseManifest === null) {
            return $this->out('4204', '该版本完整包清单无效，暂不可下载');
        }

        $ticket = SecureTicketService::issue(
            ['release_id' => (int)$release['id'], 'kind' => 'release'],
            [
                'appid'   => (int)$license['appid'],
                'auth_id' => 0,
                'ip'      => $this->ip(),
            ],
            self::TICKET_TTL
        );

        Db::name('download_ticket')
            ->where('ticket_hash', hash('sha256', $ticket))
            ->update(['license_id' => (string)$license['license_id']]);

        LicenseService::event((string)$license['license_id'], 'ticket', 'success', [
            'build_no' => $buildNo,
            'kind'     => 'release',
        ]);

        $data = [
            'ticket'         => $ticket,
            'expires_in'     => self::TICKET_TTL,
            'download_path'  => '/api/v2/update/download',
            'package_sha256' => (string)$release['package_sha256'],
            'manifest'       => $releaseManifest,
            'manifest_sig'   => (string)$release['manifest_sig'],
            'sig_key_id'     => (string)$release['sig_key_id'],
        ];

        return $this->signedOut($data, $auth, '');
    }

    public function download()
    {
        $ticket = (string)request()->param('ticket', '');
        if ($ticket === '') {
            return $this->out('4301', '缺少下载凭证');
        }

        SecureTicketService::purgeExpired();
        $payload = SecureTicketService::consume($ticket, $this->ip());
        if ($payload === null) {
            return $this->out('4301', '下载凭证无效、已使用或已过期');
        }

        $kind = (string)($payload['kind'] ?? 'release');
        $table = $kind === 'patch' ? 'patch' : 'release';
        $id = (int)($payload[$kind === 'patch' ? 'patch_id' : 'release_id'] ?? 0);
        if ($id <= 0) {
            return $this->out('4301', '凭证载荷异常');
        }

        $row = Db::name($table)->where('id', $id)->where('status', 1)->find();
        if (empty($row)) {
            return $this->out('4302', '资源不存在或已下架');
        }

        $file = $this->resolveFile($kind, $row);
        if ($file === '') {
            return $this->out('4303', '资源文件缺失');
        }

        Db::name($table)->where('id', $id)->inc('download_count')->update();

        Log::info(sprintf(
            '[QH-V2][download] kind=%s id=%d license=%s',
            $kind,
            $id,
            substr((string)($payload['_license_id'] ?? ''), 0, 8)
        ));

        $response = download($file, basename((string)($row['package_file'] ?? 'package.zip')));
        if (($row['storage_driver'] ?? 'local') === 'oss' && method_exists($response, 'deleteFileAfterSend')) {
            $response->deleteFileAfterSend(true);
        }
        return $response;
    }

    public function report()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out($auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        $requestId = (string)($in['request_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) {
            return $this->out('4001', 'request_id 格式错误');
        }

        // 幂等
        $dup = Db::name('license_event')
            ->where('license_id', $license['license_id'])
            ->where('event', 'upgrade')
            ->where('request_id', $requestId)
            ->find();
        if (!empty($dup)) {
            return $this->signedOut(['accepted' => true, 'duplicated' => true], $auth, '');
        }

        // 错误码必须是枚举，避免客户服务器内部信息外泄到本服务器
        $allowedResults = ['success', 'failed', 'rolled_back'];
        $allowedStages  = ['download', 'verify', 'backup', 'extract', 'swap', 'migrate', 'healthcheck', ''];
        $result = (string)($in['result'] ?? '');
        $stage  = (string)($in['stage'] ?? '');

        if (!in_array($result, $allowedResults, true)) {
            return $this->out('4001', 'result 取值非法');
        }
        if (!in_array($stage, $allowedStages, true)) {
            $stage = '';
        }

        LicenseService::event((string)$license['license_id'], 'upgrade', $result, [
            'from_build'  => (int)($in['from_build'] ?? 0),
            'to_build'    => (int)($in['to_build'] ?? 0),
            'stage'       => $stage,
            'error_code'  => substr(preg_replace('/[^A-Za-z0-9_.-]/', '', (string)($in['error_code'] ?? '')), 0, 64),
            'duration_ms' => (int)($in['duration_ms'] ?? 0),
        ], $requestId);

        return $this->signedOut(['accepted' => true], $auth, '');
    }

    /* ==================== 核心兼容补丁（用户需求 ⑧⑨） ==================== */

    public function patchCheck()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out($auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        if (LicenseService::effectiveStatus($license) !== LicenseService::STATUS_ACTIVE) {
            return $this->out('4201', '授权当前不可用');
        }

        $bbsVersion = (string)($in['bbs_version'] ?? '');
        $phpVersion = (string)($in['php_version'] ?? '');
        $themeBuild = (int)($in['theme_build'] ?? 0);
        $themeEdition = (string)($in['theme_edition'] ?? '');
        $applied    = is_array($in['applied'] ?? null) ? $in['applied'] : [];

        $siteId = (string)($in['site_id'] ?? '');
        if ($siteId === '' || LicenseService::findSite((string)$license['license_id'], $siteId) === null) {
            return $this->out('4006', '该站点未绑定此授权，请重新激活');
        }
        if ($themeBuild <= 0 || $themeEdition === '' || strlen($themeEdition) > 64) {
            return $this->out('4205', '主题版本或 build 无效');
        }

        $rows = Db::name('patch')
            ->where('product_id', $license['product_id'])
            ->where('theme_build', $themeBuild)
            ->where('theme_edition', $themeEdition)
            ->where('status', 1)
            ->order('patch_id asc, revision desc, id desc')
            ->select()
            ->toArray();

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['patch_id']])) {
                continue;
            }
            if (!$this->versionInRange($bbsVersion, (string)$row['min_bbs_version'], (string)$row['max_bbs_version'])) {
                continue;
            }
            if ((string)$row['min_php_version'] !== ''
                && version_compare($phpVersion, (string)$row['min_php_version'], '<')) {
                continue;
            }
            $alreadyAt = $applied[$row['patch_id']] ?? null;
            if ($alreadyAt !== null && (int)$alreadyAt >= (int)$row['revision']) {
                continue;
            }
            if (empty($row['manifest_sig'])) {
                continue; // 未签名的补丁一律不下发
            }
            $seen[$row['patch_id']] = true;

            $out[] = [
                'patch_id'        => (string)$row['patch_id'],
                'revision'        => (int)$row['revision'],
                'level'           => (int)$row['level'],
                'title'           => (string)$row['title'],
                'description'     => (string)$row['description'],
                'min_bbs_version' => (string)$row['min_bbs_version'],
                'max_bbs_version' => (string)$row['max_bbs_version'],
                'min_php_version' => (string)$row['min_php_version'],
                'package_sha256'  => (string)$row['package_sha256'],
                'package_size'    => (int)$row['package_size'],
                'requires_confirm'=> (int)$row['level'] >= 2,
            ];
        }

        return $this->signedOut(['patches' => $out, 'count' => count($out)], $auth, '');
    }

    public function patchTicket()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out($auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        $rl = RateLimitService::hit('patch_ticket', (string)$license['license_id'], 20, 3600);
        if (!$rl['ok']) {
            return $this->out('4290', '请求过于频繁', ['retry_after' => $rl['retry_after']]);
        }

        $patchId = (string)($in['patch_id'] ?? '');
        $revision = (int)($in['revision'] ?? 0);
        $themeBuild = (int)($in['theme_build'] ?? 0);
        $themeEdition = (string)($in['theme_edition'] ?? '');
        $siteId = (string)($in['site_id'] ?? '');
        if (LicenseService::effectiveStatus($license) !== LicenseService::STATUS_ACTIVE) {
            return $this->out('4201', '授权当前不可用');
        }
        if ($siteId === '' || LicenseService::findSite((string)$license['license_id'], $siteId) === null) {
            return $this->out('4006', '该站点未绑定此授权，请重新激活');
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $patchId) || $revision <= 0 || $themeBuild <= 0
            || $themeEdition === '' || strlen($themeEdition) > 64) {
            return $this->out('4202', '补丁参数无效');
        }
        $row = Db::name('patch')
            ->where('product_id', $license['product_id'])
            ->where('patch_id', $patchId)
            ->where('revision', $revision)
            ->where('theme_build', $themeBuild)
            ->where('theme_edition', $themeEdition)
            ->where('status', 1)
            ->find();

        if (empty($row)) {
            return $this->out('4202', '补丁不存在');
        }
        if (empty($row['manifest_sig'])) {
            return $this->out('4204', '该补丁尚未完成签名');
        }

        $ticket = SecureTicketService::issue(
            ['patch_id' => (int)$row['id'], 'kind' => 'patch'],
            ['appid' => (int)$license['appid'], 'auth_id' => 0, 'ip' => $this->ip()],
            self::TICKET_TTL
        );

        Db::name('download_ticket')
            ->where('ticket_hash', hash('sha256', $ticket))
            ->update(['license_id' => (string)$license['license_id']]);

        LicenseService::event((string)$license['license_id'], 'ticket', 'success', [
            'patch_id' => $patchId,
            'revision' => $revision,
            'theme_build' => $themeBuild,
            'theme_edition' => $themeEdition,
            'kind'     => 'patch',
        ]);

        return $this->signedOut([
            'ticket'         => $ticket,
            'expires_in'     => self::TICKET_TTL,
            'download_path'  => '/api/v2/patch/download',
            'package_sha256' => (string)$row['package_sha256'],
            'manifest'       => json_decode((string)$row['manifest_json'], true) ?: [],
            'manifest_sig'   => (string)$row['manifest_sig'],
            'sig_key_id'     => (string)$row['sig_key_id'],
        ], $auth, '');
    }

    /* ==================== 内部 ==================== */

    private function latestRelease(string $productId, string $channel, int $currentBuild, string $bbsVersion, string $phpVersion): ?array
    {
        $channels = $channel === LicenseService::CHANNEL_BETA
            ? [LicenseService::CHANNEL_STABLE, LicenseService::CHANNEL_BETA]
            : [LicenseService::CHANNEL_STABLE];

        $rows = Db::name('release')
            ->where('product_id', $productId)
            ->where('status', 1)
            ->whereIn('channel', $channels)
            ->where('build_no', '>', $currentBuild)
            ->whereNotNull('manifest_sig')
            ->order('build_no', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            if (!$this->versionInRange($bbsVersion, (string)$row['min_bbs_version'], (string)$row['max_bbs_version'])) {
                continue;
            }
            if ((string)$row['min_php_version'] !== ''
                && $phpVersion !== ''
                && version_compare($phpVersion, (string)$row['min_php_version'], '<')) {
                continue;
            }
            if ((string)$row['manifest_sig'] === '') {
                continue;
            }
            if ($this->releaseManifest($row) === null) {
                continue;
            }
            return $row;
        }
        return null;
    }

    private function latestStableRelease(string $productId, string $bbsVersion, string $phpVersion): ?array
    {
        $rows = Db::name('release')
            ->where('product_id', $productId)
            ->where('channel', LicenseService::CHANNEL_STABLE)
            ->where('status', 1)
            ->whereNotNull('manifest_sig')
            ->order('build_no', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            if (!$this->versionInRange($bbsVersion, (string)$row['min_bbs_version'], (string)$row['max_bbs_version'])) {
                continue;
            }
            if ((string)$row['min_php_version'] !== ''
                && $phpVersion !== ''
                && version_compare($phpVersion, (string)$row['min_php_version'], '<')) {
                continue;
            }
            if ($this->releaseManifest($row) !== null) {
                return $row;
            }
        }
        return null;
    }

    /** 发布记录必须是可由当前发布公钥验证的 schema 2 完整包。 */
    private function releaseManifest(array $row): ?array
    {
        $manifest = json_decode((string)($row['manifest_json'] ?? ''), true);
        if (!is_array($manifest)
            || (int)($manifest['schema_version'] ?? 0) !== 2
            || (string)($manifest['kind'] ?? '') !== 'release'
            || (string)($manifest['package_type'] ?? '') !== 'full'
            || !isset($manifest['migrations']) || !is_array($manifest['migrations'])
            || (string)($manifest['product_id'] ?? '') !== (string)($row['product_id'] ?? '')
            || (int)($manifest['build_no'] ?? 0) !== (int)($row['build_no'] ?? 0)
            || !hash_equals((string)($manifest['package_sha256'] ?? ''), (string)($row['package_sha256'] ?? ''))) {
            return null;
        }
        if (LicenseService::productAppId((string)$row['product_id']) === LicenseService::THEME_APPLICATION_ID) {
            foreach (ManifestService::themeRequiredFiles() as $requiredFile) {
                if (empty($manifest['files'][$requiredFile])) { return null; }
            }
            foreach (ManifestService::themeLegacyCoreFiles() as $legacyFile) {
                if (!empty($manifest['files'][$legacyFile])) { return null; }
            }
            if (!$manifest['migrations']) { return null; }
        }
        $keyId = (string)($row['sig_key_id'] ?? '');
        $keys = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
        if ($keyId === '' || empty($keys[$keyId])
            || !ManifestService::verify($manifest, (string)($row['manifest_sig'] ?? ''), (string)$keys[$keyId])) {
            return null;
        }
        return $manifest;
    }

    /**
     * 版本区间判断。空值表示不限制。
     */
    private function versionInRange(string $version, string $min, string $max): bool
    {
        if ($version === '') {
            return $min === '' && $max === '';
        }
        if ($min !== '' && version_compare($version, $min, '<')) {
            return false;
        }
        if ($max !== '' && version_compare($version, $max, '>')) {
            return false;
        }
        return true;
    }

    private function resolveFile(string $kind, array $row): string
    {
        if (($row['storage_driver'] ?? 'local') === 'oss') {
            $key = (string)($row['package_object_key'] ?? '');
            $hash = strtolower((string)($row['package_sha256'] ?? ''));
            $size = (int)($row['package_size'] ?? 0);
            if ($key === '' || !preg_match('/^[a-f0-9]{64}$/D', $hash) || $size <= 0) {
                return '';
            }
            try {
                return (new PluginStorageService())->downloadToTemporaryFile($key, $hash, $size);
            } catch (\Throwable $e) {
                return '';
            }
        }
        if (($row['storage_driver'] ?? 'local') !== 'local') {
            return '';
        }
        $base = APP_PATH . DS . 'common' . DS . 'download' . DS . ($kind === 'patch' ? 'patch' : 'v2') . DS;
        $name = (string)$row['package_file'];
        if (!preg_match('/^[A-Za-z0-9_.-]{1,160}$/', $name)) {
            return '';
        }
        $file = $base . $name;
        if (!is_file($file)) {
            return '';
        }
        // 实际哈希必须与登记值一致，避免磁盘上的包被替换后仍被下发
        $actual = @hash_file('sha256', $file);
        if (!is_string($actual) || !hash_equals((string)$row['package_sha256'], $actual)) {
            Log::error('[QH-V2] package hash mismatch on disk: ' . $name);
            return '';
        }
        return $file;
    }

    private function authenticate(): array
    {
        return LicenseAuthService::authenticate();
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
                'site_id'    => (string)($auth['body']['site_id'] ?? ''),
                'product_id' => (string)$auth['license']['product_id'],
                'nonce'      => (string)$auth['nonce'],
            ]);
        } catch (\Throwable $e) {
            return json(ApiErrorService::fail($e, 'common.server_error'));
        }

        return json([
            'code'   => 0,
            'msg'    => $msg,
            'data'   => $envelope['data'],
            'sig'    => $envelope['sig'],
            'key_id' => $envelope['key_id'],
        ]);
    }
}
