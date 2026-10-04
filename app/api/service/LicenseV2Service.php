<?php

namespace app\api\service;

use app\common\service\ApiErrorService;
use app\common\service\AuthcodeService;
use app\common\service\BaseService;
use app\common\service\CryptoService;
use app\common\service\DomainVerifyService;
use app\common\service\LicenseAuthService;
use app\common\service\LicenseService;
use app\common\service\LicenseSignatureService;
use app\common\service\OfflineActivationService;
use app\common\service\RateLimitService;
use think\facade\Db;
use think\facade\Cache;

/**
 * 授权接口 v2
 *
 * 路由（见 app/api/route/route.php）：
 *   POST /api/v2/license/activate     激活（无 HMAC，授权码 + 域名证明）
 *   POST /api/v2/license/redeem       旧码兑换（无 HMAC）
 *   POST /api/v2/license/status       状态查询（HMAC）
 *   POST /api/v2/license/rebind       解绑换绑（HMAC）
 *   POST /api/v2/license/channel      测试通道申请（HMAC）
 *   POST /api/v2/license/trial        试用登记（无 HMAC）
 *
 * 与 v1（/api.php/Auth/*）完全并行，v1 不受影响。
 *
 * @since 2026-08-16 P2
 */
class LicenseV2Service extends BaseService
{
    /* ==================== 激活 ==================== */

    public function activate()
    {
        return $this->activateOrRestore(false);
    }

    /**
     * 恢复授权：允许在本地签名文件损坏、丢失或宽限期结束后，凭授权码
     * 对同一站点重新签发凭据与授权文件。接口不依赖旧的 license_secret。
     */
    public function restore()
    {
        return $this->activateOrRestore(true);
    }

    private function activateOrRestore(bool $restore)
    {
        $in = $this->body();
        $ip = $this->ip();

        $fresh = $this->verifyUnsignedFreshness($in, $restore ? 'restore' : 'activate');
        if (!$fresh['ok']) {
            return $this->out(false, $fresh['code'], $fresh['msg']);
        }

        $bucket = $restore ? 'restore_ip' : 'activate_ip';
        $rl = RateLimitService::hit($bucket, $ip, 5, 3600);
        if (!$rl['ok']) {
            return $this->out(false, '4290', '请求过于频繁，请稍后再试', ['retry_after' => $rl['retry_after']]);
        }

        $authcode = AuthcodeService::normalize((string)($in['authcode'] ?? ''));
        if ($authcode !== '') {
            $rl2 = RateLimitService::hit($restore ? 'restore_code' : 'activate_code', $authcode, 10, 86400);
            if (!$rl2['ok']) {
                return $this->out(false, '4290', '该授权码今日尝试次数过多', ['retry_after' => $rl2['retry_after']]);
            }
        }

        $productId = (string)($in['product_id'] ?? '');
        $host      = (string)($in['host'] ?? '');
        $uuid      = (string)($in['install_uuid'] ?? '');
        $nonce     = (string)($in['nonce'] ?? '');

        // 域名归属证明
        $verify = DomainVerifyService::verify($host, $productId, (string)($in['domain_challenge'] ?? ''));
        if (!$verify['ok']) {
            $reason = $verify['reason'] === 'not_public'
                ? '该域名不可从公网访问，请使用离线激活'
                : '域名归属证明未通过，请确认主题已启用后重试，或使用离线激活';
            return $this->out(false, '4005', $reason, [
                'offline_available' => true,
                'reason'            => $verify['reason'],
            ]);
        }

        $result = LicenseService::activate([
            'authcode'      => $authcode,
            'product_id'    => $productId,
            'host'          => $host,
            'install_uuid'  => $uuid,
            'verified'      => true,
            'verify_method' => $verify['method'],
        ]);

        if (!$result['ok']) {
            LicenseService::event('', $restore ? 'restore' : 'activate', 'fail', [
                'reason'   => $result['code'],
                'authcode' => $authcode,   // 会被 scrub 成 ***后4位
                'host'     => LicenseService::maskHost($host),
            ]);
            return $this->out(false, $result['code'], $result['msg']);
        }

        $binding = [
            'license_id' => $result['data']['license_id'],
            'site_id'    => $result['data']['site_id'],
            'product_id' => $productId,
            'nonce'      => $nonce,
            'host'       => $host,
        ];
        $data = $this->withLicenseFile($result['data'], $binding);
        if ($restore) {
            LicenseService::event((string)$result['data']['license_id'], 'restore', 'success', [
                'site_id' => (string)$result['data']['site_id'],
            ]);
        }
        return $this->signedOut($data, $binding, $restore ? '授权恢复成功' : $result['msg']);
    }

    /* ==================== 旧码兑换（用户需求 ①） ==================== */

    public function redeem()
    {
        $in = $this->body();
        $ip = $this->ip();

        $fresh = $this->verifyUnsignedFreshness($in, 'redeem');
        if (!$fresh['ok']) {
            return $this->out(false, $fresh['code'], $fresh['msg']);
        }

        $rl = RateLimitService::hit('redeem_ip', $ip, 5, 3600);
        if (!$rl['ok']) {
            return $this->out(false, '4290', '请求过于频繁，请稍后再试', ['retry_after' => $rl['retry_after']]);
        }

        $legacy = AuthcodeService::normalize((string)($in['legacy_authcode'] ?? ''));
        if ($legacy !== '') {
            $rl2 = RateLimitService::hit('redeem_code', $legacy, 10, 86400);
            if (!$rl2['ok']) {
                return $this->out(false, '4290', '该授权码今日尝试次数过多', ['retry_after' => $rl2['retry_after']]);
            }
        }

        $productId = (string)($in['product_id'] ?? '');
        $host      = (string)($in['host'] ?? '');
        $nonce     = (string)($in['nonce'] ?? '');

        /*
         * 兑换同样要求域名归属证明。
         * 理由：旧授权码由可预测算法生成（A-01），若不加第二因子，
         * 攻击者可能抢先兑换他人授权。要求「能证明控制该站点」即可封堵。
         */
        $verify = DomainVerifyService::verify($host, $productId, (string)($in['domain_challenge'] ?? ''));
        if (!$verify['ok']) {
            return $this->out(false, '4005', '域名归属证明未通过，请使用离线兑换（需登录购买账号）', [
                'offline_available' => true,
                'reason'            => $verify['reason'],
            ]);
        }

        $result = LicenseService::redeem([
            'legacy_authcode' => $legacy,
            'product_id'      => $productId,
            'host'            => $host,
            'install_uuid'    => (string)($in['install_uuid'] ?? ''),
            'verified'        => true,
            'verify_method'   => $verify['method'],
        ]);

        if (!$result['ok']) {
            return $this->out(false, $result['code'], $result['msg']);
        }

        $binding = [
            'license_id' => $result['data']['license_id'],
            'site_id'    => $result['data']['site_id'],
            'product_id' => $productId,
            'nonce'      => $nonce,
            'host'       => $host,
        ];
        return $this->signedOut($this->withLicenseFile($result['data'], $binding), $binding, $result['msg']);
    }

    /* ==================== 状态查询 ==================== */

    public function status()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out(false, $auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        $rl = RateLimitService::hit('status', (string)$license['license_id'], 60, 3600);
        if (!$rl['ok']) {
            return $this->out(false, '4290', '请求过于频繁', ['retry_after' => $rl['retry_after']]);
        }

        $siteId = (string)($in['site_id'] ?? '');
        $site = LicenseService::findSite((string)$license['license_id'], $siteId);
        if ($site === null) {
            return $this->out(false, '4006', '该站点未绑定此授权，请重新激活');
        }

        Db::name('license_site')->where('id', $site['id'])->update(['last_seen_at' => datetime()]);
        Db::name('license')->where('license_id', $license['license_id'])->update(['last_seen_at' => datetime()]);

        LicenseService::event((string)$license['license_id'], 'verify', 'success', ['site_id' => $siteId]);

        $binding = [
                'license_id' => (string)$license['license_id'],
                'site_id'    => $siteId,
                'product_id' => (string)$license['product_id'],
                'nonce'      => $auth['nonce'],
                'host'       => (string)($site['host'] ?? ''),
            ];
        $status = LicenseService::statusPayload($license, $siteId);
        return $this->signedOut($this->withLicenseFile($status, $binding), $binding, 'ok');
    }

    /* ==================== 换绑 ==================== */

    public function rebind()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out(false, $auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];

        $rl = RateLimitService::hit('rebind', (string)$license['license_id'], 3, 86400);
        if (!$rl['ok']) {
            return $this->out(false, '4290', '换绑请求过于频繁', ['retry_after' => $rl['retry_after']]);
        }

        // 二次确认：换绑必须再次提供授权码
        $confirm = AuthcodeService::normalize((string)($in['authcode'] ?? ''));
        if (!AuthcodeService::verify($confirm, (string)$license['authcode_hash'], (int)$license['pepper_version'])) {
            return $this->out(false, '4103', '授权码二次确认失败');
        }

        $newHost = (string)($in['new_host'] ?? '');
        $verify = DomainVerifyService::verify(
            $newHost,
            (string)$license['product_id'],
            (string)($in['domain_challenge'] ?? '')
        );

        $result = LicenseService::rebind($license, [
            'new_host'         => $newHost,
            'new_install_uuid' => (string)($in['new_install_uuid'] ?? ''),
            'request_id'       => (string)($in['request_id'] ?? ''),
            'role'             => (string)($in['role'] ?? LicenseService::ROLE_PRIMARY),
            'verified'         => $verify['ok'],
            'verify_method'    => $verify['method'],
        ]);

        if (!$result['ok']) {
            return $this->out(false, $result['code'], $result['msg']);
        }

        return $this->signedOut($result['data'], [
            'license_id' => (string)$license['license_id'],
            'site_id'    => (string)$result['data']['site_id'],
            'product_id' => (string)$license['product_id'],
            'nonce'      => $auth['nonce'],
        ], $result['msg']);
    }

    /* ==================== 测试通道（用户需求 ⑥） ==================== */

    public function channel()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->out(false, $auth['code'], $auth['msg']);
        }
        $license = $auth['license'];
        $in = $auth['body'];
        $siteId = (string)($in['site_id'] ?? '');

        $rl = RateLimitService::hit('channel', (string)$license['license_id'].'|'.$siteId, 10, 3600);
        if (!$rl['ok']) {
            return $this->out(false, '4290', '测试资格修改过于频繁，请稍后再试', ['retry_after' => $rl['retry_after']]);
        }
        $site = LicenseService::findSite((string)$license['license_id'], $siteId);
        if ($site === null) {
            return $this->out(false, '4006', '该站点未绑定此授权，请重新激活');
        }

        $result = LicenseService::setChannel(
            $license,
            $siteId,
            (string)($in['channel'] ?? LicenseService::CHANNEL_STABLE),
            !empty($in['accept_risk'])
        );

        if (!$result['ok']) {
            return $this->out(false, $result['code'], $result['msg']);
        }

        $freshLicense = LicenseService::findByLicenseId((string)$license['license_id']);
        if ($freshLicense === null) {
            return $this->out(false, '5000', '授权状态刷新失败，请稍后重试');
        }
        $binding = [
            'license_id' => (string)$license['license_id'],
            'site_id'    => $siteId,
            'product_id' => (string)$license['product_id'],
            'nonce'      => $auth['nonce'],
            'host'       => (string)($site['host'] ?? ''),
        ];
        $data = array_merge(LicenseService::statusPayload($freshLicense, $siteId), $result['data']);
        return $this->signedOut($this->withLicenseFile($data, $binding), $binding, $result['msg']);
    }

    /* ==================== 试用登记（用户需求 ③） ==================== */

    public function trial()
    {
        $in = $this->body();
        $ip = $this->ip();

        $fresh = $this->verifyUnsignedFreshness($in, 'trial');
        if (!$fresh['ok']) {
            return $this->out(false, $fresh['code'], $fresh['msg']);
        }

        $rl = RateLimitService::hit('trial', $ip, 20, 3600);
        if (!$rl['ok']) {
            return $this->out(false, '4290', '请求过于频繁', ['retry_after' => $rl['retry_after']]);
        }

        $host = (string)($in['host'] ?? '');
        $uuid = (string)($in['install_uuid'] ?? '');
        $productId = (string)($in['product_id'] ?? '');
        if ($host === '' || $uuid === '') {
            return $this->out(false, '4001', '站点信息不完整');
        }

        $siteId = LicenseService::siteId($host, $uuid);
        $trial = LicenseService::trial($siteId, $host, $productId);

        return $this->signedOut($trial, [
            'license_id' => '',
            'site_id'    => $siteId,
            'product_id' => $productId,
            'nonce'      => (string)($in['nonce'] ?? ''),
        ], 'ok');
    }

    /* ==================== 离线激活 ==================== */

    /**
     * 由授权站后台（用户已登录）调用：粘贴申请码 → 生成激活凭证
     *
     * 注意：本方法不走 HMAC，身份由「后台登录态」承担，
     * 因此控制器必须放在需要登录的应用中调用，或由管理端服务转发。
     */
    public function offlineIssue(array $in, int $operatorUserId = 0)
    {
        $req = OfflineActivationService::parseRequest((string)($in['request_code'] ?? ''));
        if ($req === null) {
            return ['ok' => false, 'code' => '4001', 'msg' => '申请码无效或已过期', 'data' => []];
        }

        $authcode = AuthcodeService::normalize((string)($in['authcode'] ?? ''));
        if (!AuthcodeService::looksValid($authcode)) {
            return ['ok' => false, 'code' => '4001', 'msg' => '授权码格式错误', 'data' => []];
        }

        $resolved = LicenseService::resolveForActivation(
            $authcode,
            (string)$req['product_id'],
            (string)$req['host'],
            $operatorUserId
        );
        if (!$resolved['ok']) {
            return $resolved;
        }
        $license = $resolved['data']['license'];

        // 旧授权允许管理者或当前绑定者签发；原生 v2 授权仍只认 user_id。
        // 关系由服务端实时读取，解绑后不能继续凭旧授权码签发。
        if ($operatorUserId > 0 && !LicenseService::operatorCanUseLicense($license, $operatorUserId)) {
            return ['ok' => false, 'code' => '4003', 'msg' => '该授权不属于当前账号', 'data' => []];
        }

        $status = LicenseService::effectiveStatus($license);
        if ($status !== LicenseService::STATUS_ACTIVE && $status !== LicenseService::STATUS_PENDING) {
            return ['ok' => false, 'code' => '4003', 'msg' => '该授权当前状态不可激活：' . $status, 'data' => []];
        }

        if (!CryptoService::configured(CryptoService::PURPOSE_LICENSE)) {
            return ['ok' => false, 'code' => '5000', 'msg' => '服务端签名密钥未配置', 'data' => []];
        }

        // 离线激活的登录账号已经完成归属证明。绑定规则与在线激活走同一
        // 原子入口，同域名重装会轮换 install_uuid，而不会重复消耗名额。
        $binding = LicenseService::bindVerifiedSite(
            $license,
            (string)$req['host'],
            (string)$req['install_uuid'],
            'offline'
        );
        if (!$binding['ok']) {
            return $binding;
        }

        return OfflineActivationService::issue($license, $req);
    }

    /* ==================== 内部工具 ==================== */

    /**
     * HMAC 请求认证（实现见 LicenseAuthService，与 UpdateV2Service 共用同一口径）
     */
    private function authenticate(): array
    {
        return LicenseAuthService::authenticate();
    }

    /**
     * 激活前没有共享密钥可做 HMAC，因此用 TLS + 授权码 + 域名证明建立身份；
     * 时间戳和一次性 nonce 负责拒绝原样重放。成功响应仍由 Ed25519 签名。
     */
    private function verifyUnsignedFreshness(array $body, string $action): array
    {
        $headers = LicenseSignatureService::extractHeaders(request());
        $timestamp = (string)($headers['timestamp'] ?? '');
        $nonce = strtolower((string)($headers['nonce'] ?? ''));
        if (!preg_match('/^\d{10}$/', $timestamp) || abs(time() - (int)$timestamp) > LicenseSignatureService::TIME_WINDOW) {
            return ['ok' => false, 'code' => '4400', 'msg' => '请求时间无效或已过期'];
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $nonce)
            || empty($body['nonce']) || !hash_equals($nonce, strtolower((string)$body['nonce']))) {
            return ['ok' => false, 'code' => '4400', 'msg' => '请求随机数无效'];
        }
        $key = 'sf_unsigned_nonce_' . hash('sha256', $action.'|'.$this->ip().'|'.$nonce);
        if (Cache::has($key)) {
            return ['ok' => false, 'code' => '4401', 'msg' => '重复请求'];
        }
        Cache::set($key, 1, LicenseSignatureService::NONCE_TTL);
        return ['ok' => true, 'code' => '0', 'msg' => ''];
    }

    private function requestPath(): string
    {
        try {
            return '/' . ltrim((string)request()->pathinfo(), '/');
        } catch (\Throwable $e) {
            return '/';
        }
    }

    private function body(): array
    {
        $raw = (string)request()->getContent();
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
        return request()->post();
    }

    private function ip(): string
    {
        try {
            return (string)request()->ip();
        } catch (\Throwable $e) {
            return '0.0.0.0';
        }
    }

    /**
     * 未签名响应（错误路径）
     */
    private function out(bool $ok, string $code, string $msg, array $data = [])
    {
        return json([
            'code' => $ok ? 0 : (int)$code,
            'msg'  => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 已签名响应（成功路径）
     *
     * data 内含 license_id / site_id / nonce / product_id / issued_at / expires_at，
     * 客户端必须逐项比对 —— 这是封堵跨站重放（A-04）的关键。
     */
    private function signedOut(array $data, array $binding, string $msg)
    {
        try {
            $envelope = LicenseSignatureService::signResponse($data, $binding);
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

    private function withLicenseFile(array $data, array $binding): array
    {
        $data['license_file'] = LicenseSignatureService::signLicenseFile($data, $binding);
        return $data;
    }
}
