<?php

namespace app\common\service;

use think\facade\Db;

/**
 * 授权请求认证（HMAC）
 *
 * 被 LicenseV2Service 与 UpdateV2Service 共用，保证两侧认证口径完全一致。
 *
 * @since 2026-08-16 P2
 */
class LicenseAuthService
{
    /**
     * @return array [
     *   'ok'=>bool, 'code'=>string, 'msg'=>string,
     *   'license'=>array, 'body'=>array, 'nonce'=>string
     * ]
     */
    public static function authenticate(): array
    {
        $request = request();
        $headers = LicenseSignatureService::extractHeaders($request);
        $raw = (string)$request->getContent();

        $licenseId = $headers['license_id'];
        if ($licenseId === '') {
            return self::fail('4400', '缺少 License 标识');
        }

        $license = LicenseService::findByLicenseId($licenseId);
        if ($license === null) {
            return self::fail('4001', '授权不存在');
        }
        if (empty($license['secret_hash'])) {
            return self::fail('4001', '授权尚未完成激活');
        }

        $hmacKey = self::hmacKey($license);
        if ($hmacKey === '') {
            return self::fail('5000', '服务端密钥状态异常');
        }

        $path = '/' . ltrim((string)$request->pathinfo(), '/');
        $verify = LicenseSignatureService::verifyRequest(
            $headers,
            $request->method(),
            $path,
            $raw,
            $hmacKey
        );

        if (!$verify['ok']) {
            LicenseService::event($licenseId, 'verify', 'fail', ['reason' => $verify['msg']]);
            return self::fail($verify['code'], $verify['msg']);
        }

        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : [];
        $siteId = (string)($body['site_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $siteId)
            || LicenseService::findSite($licenseId, $siteId) === null) {
            LicenseService::event($licenseId, 'verify', 'fail', ['reason' => 'site_not_bound']);
            return self::fail('4006', '该站点未绑定此授权，请重新激活');
        }

        return [
            'ok'      => true,
            'code'    => '0',
            'msg'     => '',
            'license' => $license,
            'body'    => $body,
            'nonce'   => $headers['nonce'],
        ];
    }

    /**
     * 取该授权用于 HMAC 的密钥
     *
     * license_secret 明文只在激活时下发一次。服务端有两种保存形态：
     *   1. secret_enc —— XChaCha20-Poly1305 加密保存（推荐，需配置 license_secret_key）
     *   2. secret_hash —— 仅保存 SHA-256
     *
     * 形态 2 下两端约定以 secret_hash 作为 HMAC key。它仍然是「每授权独立」的，
     * 单个授权泄露不波及其它授权，与 A-08 所指的「全局共享主密钥」有本质区别；
     * 但服务端数据库泄露时该值可直接用于伪造请求签名，
     * 因此生产环境应配置 license_secret_key 启用形态 1。
     */
    public static function hmacKey(array $license): string
    {
        return self::storedClientSecret(
            (string)($license['secret_hash'] ?? ''),
            (string)($license['secret_enc'] ?? '')
        );
    }

    /**
     * 幂等取得站点通信凭据：已有健康凭据时原样复用，仅在从未签发时创建。
     *
     * 单站点授权的通信密钥保存在授权主记录。事务行锁避免两个并发签发
     * 请求各自生成密钥并互相覆盖。
     */
    public static function issueOrReuseSecret(string $licenseId): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $licenseId)) {
            return ['ok' => false, 'code' => '4001', 'msg' => '授权记录不存在', 'client_secret' => '', 'reused' => false];
        }

        Db::startTrans();
        try {
            $license = Db::name('license')->where('license_id', $licenseId)->lock(true)->find();
            if (!is_array($license)) {
                Db::rollback();
                return ['ok' => false, 'code' => '4001', 'msg' => '授权记录不存在', 'client_secret' => '', 'reused' => false];
            }

            $existing = self::hmacKey($license);
            if ($existing !== '') {
                Db::name('license')->where('license_id', $licenseId)->update([
                    'last_seen_at' => datetime(),
                    'updated_at'   => datetime(),
                ]);
                Db::commit();
                return ['ok' => true, 'code' => '0', 'msg' => '', 'client_secret' => $existing, 'reused' => true];
            }

            // 有字段但无法解密/校验说明服务端配置或数据损坏。此时静默覆盖会
            // 立刻让已安装客户端失效，所以必须安全失败并由管理员恢复。
            if (!empty($license['secret_hash']) || !empty($license['secret_enc'])) {
                Db::rollback();
                return ['ok' => false, 'code' => '5000', 'msg' => '服务端凭据状态异常，请联系管理员', 'client_secret' => '', 'reused' => false];
            }

            $secret = bin2hex(random_bytes(32));
            $encrypted = self::encryptSecret($secret);
            $clientSecret = $encrypted !== '' ? $secret : hash('sha256', $secret);
            Db::name('license')->where('license_id', $licenseId)->update([
                'secret_hash'  => hash('sha256', $secret),
                'secret_enc'   => $encrypted,
                'last_seen_at' => datetime(),
                'updated_at'   => datetime(),
            ]);
            Db::commit();
            return ['ok' => true, 'code' => '0', 'msg' => '', 'client_secret' => $clientSecret, 'reused' => false];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['ok' => false, 'code' => '5000', 'msg' => '服务端凭据签发失败，请稍后重试', 'client_secret' => '', 'reused' => false];
        }
    }

    /**
     * 加密保存 license_secret（激活时调用）
     * 未配置密钥时返回空串，调用方退回只存哈希。
     */
    public static function encryptSecret(string $plain): string
    {
        $key = trim((string)env('license_secret_key', ''));
        if ($key === '' || !function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            return '';
        }
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $k = hash('sha256', $key, true);
            $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, '', $nonce, $k);
            return CryptoService::b64uEncode($nonce . $ct);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function storedClientSecret(string $hash, string $encrypted): string
    {
        $hash = strtolower(trim($hash));
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            return '';
        }
        if ($encrypted === '') {
            // 未配置服务端加密密钥的历史兼容模式：客户端收到的就是该摘要。
            return $hash;
        }
        $plain = self::decryptSecret($encrypted);
        if ($plain === '' || !hash_equals($hash, hash('sha256', $plain))) {
            return '';
        }
        return $plain;
    }

    private static function decryptSecret(string $blob): string
    {
        $key = trim((string)env('license_secret_key', ''));
        if ($key === '' || !function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            return '';
        }
        $raw = CryptoService::b64uDecode($blob);
        if (strlen($raw) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            return '';
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ct    = substr($raw, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $k     = hash('sha256', $key, true);
        try {
            $out = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, '', $nonce, $k);
            return $out === false ? '' : $out;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function fail(string $code, string $msg): array
    {
        return ['ok' => false, 'code' => $code, 'msg' => $msg, 'license' => [], 'body' => [], 'nonce' => ''];
    }
}
