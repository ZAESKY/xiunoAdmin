<?php

namespace app\common\service;

use think\facade\Cache;

/**
 * 授权接口签名规范（v2）
 *
 * ── 请求签名（客户端 → 服务端，HMAC-SHA256，密钥为每授权独立的 license_secret）
 *
 * 规范串（\n 连接，无尾换行）：
 *   1  HTTP 方法，大写
 *   2  请求路径，不含 query
 *   3  SHA-256(请求体原始字节)，小写 hex
 *   4  X-QH-Timestamp   Unix 秒
 *   5  X-QH-Nonce       32 位 hex
 *   6  X-QH-License-Id
 *
 * 签名值放 X-QH-Signature，小写 hex。
     * 服务端校验顺序：时间窗(±300s) → HMAC 常量时间比对 → 占用 nonce。
 *
 * ── 响应签名（服务端 → 客户端，Ed25519，客户端只持公钥）
 *
 * 对 data 做确定性 JSON 序列化后签名，签名放 X-QH-Response-Signature，
 * 密钥编号放 X-QH-Key-Id。data 必须含 license_id / site_id / nonce /
 * product_id / issued_at / expires_at，客户端必须逐项比对 —— 这是
 * 封堵「跨站重放合法响应」(A-04) 的关键。
 *
 * @since 2026-08-16 P2
 */
class LicenseSignatureService
{
    /** 请求时间窗（秒） */
    public const TIME_WINDOW = 300;
    /** nonce 留存时长，必须 > 2 * TIME_WINDOW */
    public const NONCE_TTL = 900;

    public const H_LICENSE   = 'X-QH-License-Id';
    public const H_TIMESTAMP = 'X-QH-Timestamp';
    public const H_NONCE     = 'X-QH-Nonce';
    public const H_SIGNATURE = 'X-QH-Signature';
    public const H_PRODUCT   = 'X-QH-Product';

    /**
     * 构造签名规范串。客户端与服务端共用同一实现，杜绝拼接差异。
     */
    public static function canonicalString(
        string $method,
        string $path,
        string $body,
        string $timestamp,
        string $nonce,
        string $licenseId
    ): string {
        return implode("\n", [
            strtoupper($method),
            self::normalizePath($path),
            hash('sha256', $body),
            $timestamp,
            $nonce,
            $licenseId,
        ]);
    }

    /**
     * 计算请求签名
     */
    public static function signRequest(
        string $method,
        string $path,
        string $body,
        string $timestamp,
        string $nonce,
        string $licenseId,
        string $licenseSecret
    ): string {
        return hash_hmac(
            'sha256',
            self::canonicalString($method, $path, $body, $timestamp, $nonce, $licenseId),
            $licenseSecret
        );
    }

    /**
     * 校验请求签名
     *
     * @return array ['ok'=>bool, 'code'=>string, 'msg'=>string]
     */
    public static function verifyRequest(array $headers, string $method, string $path, string $body, string $licenseSecret): array
    {
        $timestamp = (string)($headers['timestamp'] ?? '');
        $nonce     = (string)($headers['nonce'] ?? '');
        $licenseId = (string)($headers['license_id'] ?? '');
        $signature = (string)($headers['signature'] ?? '');

        if ($timestamp === '' || $nonce === '' || $licenseId === '' || $signature === '') {
            return ['ok' => false, 'code' => '4400', 'msg' => '签名头缺失'];
        }
        if (!preg_match('/^\d{10}$/', $timestamp)) {
            return ['ok' => false, 'code' => '4400', 'msg' => '时间戳格式错误'];
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) {
            return ['ok' => false, 'code' => '4400', 'msg' => 'nonce 格式错误'];
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return ['ok' => false, 'code' => '4400', 'msg' => '签名格式错误'];
        }

        if (abs(time() - (int)$timestamp) > self::TIME_WINDOW) {
            return ['ok' => false, 'code' => '4400', 'msg' => '请求已过期'];
        }

        // 先验签再占用 nonce。否则攻击者只要猜到/截获一个 nonce，就可以用无效
        // 签名抢先把它写入缓存，造成合法请求被误判为重放。
        $nonceKey = 'qh_nonce_' . hash('sha256', $licenseId . '|' . $nonce);
        if (Cache::has($nonceKey)) {
            return ['ok' => false, 'code' => '4401', 'msg' => '重复请求'];
        }

        $expected = self::signRequest($method, $path, $body, $timestamp, $nonce, $licenseId, $licenseSecret);
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'code' => '4400', 'msg' => '签名验证失败'];
        }

        Cache::set($nonceKey, 1, self::NONCE_TTL);

        return ['ok' => true, 'code' => '0', 'msg' => ''];
    }

    /**
     * 组装并签名响应
     *
     * @param array  $data      业务数据（会被补齐防重放字段）
     * @param array  $binding   ['license_id'=>..,'site_id'=>..,'product_id'=>..,'nonce'=>..]
     * @param int    $ttl       响应有效期（秒），客户端超期即视为过期
     */
    public static function signResponse(array $data, array $binding, int $ttl = 86400): array
    {
        $now = time();

        // 防重放绑定字段：客户端必须逐项比对
        $payload = array_merge($data, [
            'license_id' => (string)($binding['license_id'] ?? ''),
            'site_id'    => (string)($binding['site_id'] ?? ''),
            'product_id' => (string)($binding['product_id'] ?? ''),
            'nonce'      => (string)($binding['nonce'] ?? ''),
            'issued_at'  => $now,
            'expires_at' => $now + $ttl,
        ]);

        $signed = CryptoService::sign($payload, CryptoService::PURPOSE_LICENSE);

        return [
            'data'   => $payload,
            'sig'    => $signed['sig'],
            'key_id' => $signed['key_id'],
        ];
    }

    /**
     * 签发可持久化到客户端的离线授权文件。
     *
     * 这个载荷与短期 API 响应签名分离：API 响应的 expires_at 是防重放
     * 有效期，而授权文件的 expires_at 是授权本身的到期时间（永久授权为 0）。
     * 载荷严禁包含授权码、license_secret 或任何私钥材料。
     */
    public static function signLicenseFile(array $status, array $binding): array
    {
        $permanent = !empty($status['permanent']);
        $licenseExpiresAt = $permanent ? 0 : (int)($status['expires_at'] ?? 0);
        $graceSeconds = max(0, (int)($status['grace_period'] ?? 86400));
        $failureMode = (string)($status['failure_mode'] ?? 'grace');
        if (!in_array($failureMode, ['grace', 'immediate'], true)) {
            $failureMode = 'grace';
        }
        // 授权文件绑定的是“本次签发所对应的站点”，不能直接使用授权记录
        // 上的主站域名。否则副站会收到主站域名，首次激活时还可能收到空值。
        $boundHost = trim((string)($binding['host'] ?? ''));
        if ($boundHost === '') {
            $boundHost = trim((string)($status['bound_host'] ?? ''));
        }

        $payload = [
            'file_version'   => 1,
            'license_version'=> 1,
            'product_id'     => (string)($binding['product_id'] ?? ''),
            'license_id'     => (string)($binding['license_id'] ?? ''),
            'site_id'        => (string)($binding['site_id'] ?? ''),
            'bound_host'     => $boundHost,
            'status'         => (string)($status['status'] ?? 'revoked'),
            'permanent'      => $permanent,
            'issued_at'      => time(),
            'refresh_after'  => time() + 604800,
            'valid_until'    => time() + 691200,
            'expires_at'     => $licenseExpiresAt,
            'channel'        => (string)($status['channel'] ?? 'stable'),
            'grace_seconds'  => $graceSeconds,
            'failure_mode'   => $failureMode,
            'document_id'    => bin2hex(random_bytes(16)),
        ];

        $signed = CryptoService::sign($payload, CryptoService::PURPOSE_LICENSE);
        return [
            'data'   => $payload,
            'sig'    => $signed['sig'],
            'key_id' => $signed['key_id'],
        ];
    }

    /**
     * 从请求对象提取签名头（兼容大小写与下划线形式）
     */
    public static function extractHeaders($request): array
    {
        $get = function ($name, $legacyEncoded = '') use ($request) {
            $v = $request->header($name);
            if ($v === null || $v === '') {
                $v = $request->header(str_replace('-', '_', $name));
            }
            // 平滑兼容尚未升级的客户端；旧请求头只以编码形式出现，避免继续扩散旧命名。
            if (($v === null || $v === '') && $legacyEncoded !== '') {
                $legacyName = base64_decode($legacyEncoded, true);
                if (is_string($legacyName) && $legacyName !== '') {
                    $v = $request->header($legacyName);
                    if ($v === null || $v === '') {
                        $v = $request->header(str_replace('-', '_', $legacyName));
                    }
                }
            }
            return (string)($v ?? '');
        };

        return [
            'license_id' => $get('x-qh-license-id', 'eC1zZi1saWNlbnNlLWlk'),
            'timestamp'  => $get('x-qh-timestamp', 'eC1zZi10aW1lc3RhbXA='),
            'nonce'      => $get('x-qh-nonce', 'eC1zZi1ub25jZQ=='),
            'signature'  => $get('x-qh-signature', 'eC1zZi1zaWduYXR1cmU='),
            'product'    => $get('x-qh-product', 'eC1zZi1wcm9kdWN0'),
        ];
    }

    /**
     * 路径归一化：去掉尾部斜杠（根路径除外），保证两端一致
     */
    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }
}
