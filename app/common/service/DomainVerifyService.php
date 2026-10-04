<?php

namespace app\common\service;

use think\facade\Cache;
use think\facade\Log;

/**
 * 域名归属证明（A-10）
 *
 * 原实现中绑定域名完全由客户端自报（getenv('HTTP_HOST')），服务端直接拿它查表，
 * 授权与真实站点之间不存在可信绑定。
 *
 * 客户端先在目标 Xiuno 站点保存一次性 challenge，本服务再由服务端反向
 * 发起 HTTPS 请求读取它。目标站点只回答已登记且未过期的 challenge，
 * 因此仅仅知道授权码或看到一个装有主题的站点不足以伪造归属证明。
 *
 * 安全约束：
 *   - 这是一个「服务端按用户输入发起请求」的动作，必须防 SSRF：
 *     解析后的 IP 必须是公网地址，且拒绝跳转到内网
 *   - 只读取响应体前若干字节，避免大响应拖垮进程
 *   - 超时与重试次数严格受限，避免被用作放大器
 *
 * @since 2026-08-16 P2
 */
class DomainVerifyService
{
    /** challenge 有效期 */
    public const CHALLENGE_TTL = 300;
    /** 单次请求超时 */
    private const TIMEOUT = 5;
    /** 只读取响应体前 N 字节 */
    private const MAX_BODY = 256;

    /**
     * 签发 challenge
     */
    public static function issue(string $host, string $productId): array
    {
        $challenge = bin2hex(random_bytes(16));
        Cache::set(self::key($challenge), [
            'host'       => LicenseService::normalizeHost($host),
            'product_id' => $productId,
            'created_at' => time(),
        ], self::CHALLENGE_TTL);

        return [
            'challenge'  => $challenge,
            'expires_in' => self::CHALLENGE_TTL,
            'expect'     => self::expectedAnswer($challenge, $productId),
            'path'       => '/?sflicense-verify-' . $challenge,
        ];
    }

    /**
     * 客户端应当返回的内容
     */
    public static function expectedAnswer(string $challenge, string $productId): string
    {
        return hash('sha256', $challenge . '|' . $productId);
    }

    /**
     * 执行归属证明
     *
     * @return array ['ok'=>bool,'method'=>string,'reason'=>string]
     */
    public static function verify(string $host, string $productId, string $challenge): array
    {
        $host = LicenseService::normalizeHost($host);
        if ($host === '') {
            return ['ok' => false, 'method' => '', 'reason' => 'empty_host'];
        }
        $challenge = strtolower(trim($challenge));
        if (!preg_match('/^[a-f0-9]{32}$/', $challenge)) {
            return ['ok' => false, 'method' => '', 'reason' => 'challenge_missing'];
        }
        $publicIps = self::resolvePublicIps($host);
        if (!$publicIps) {
            // 内网 / 保留地址无法反向访问，直接引导到离线激活
            return ['ok' => false, 'method' => '', 'reason' => 'not_public'];
        }

        $expect = self::expectedAnswer($challenge, $productId);
        $path = '/?sflicense-verify-' . $challenge;

        // 默认只使用 HTTPS。确需兼容无证书旧站时必须由服务端显式开启，
        // 不能让客户端自行降级安全策略。
        $schemes = ['https'];
        if (filter_var(env('license_domain_verify_allow_http', false), FILTER_VALIDATE_BOOLEAN)) {
            $schemes[] = 'http';
        }
        foreach ($schemes as $scheme) {
            $url = $scheme . '://' . $host . $path;
            foreach ($publicIps as $ip) {
                $body = self::fetch($url, $host, $ip);
                if ($body !== null && hash_equals($expect, trim($body))) {
                    return ['ok' => true, 'method' => $scheme, 'reason' => ''];
                }
            }
        }

        return ['ok' => false, 'method' => '', 'reason' => 'challenge_mismatch'];
    }

    /**
     * 受限 HTTP 抓取
     */
    private static function fetch(string $url, string $host, string $ip): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $port = $scheme === 'https' ? 443 : 80;
        $resolveIp = str_contains($ip, ':') ? '[' . trim($ip, '[]') . ']' : $ip;
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            // 不跟随跳转 —— 跳转是 SSRF 绕过内网限制的常见手段
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'SF-License-Verifier/2.0',
            CURLOPT_BUFFERSIZE     => 128,
            CURLOPT_NOPROGRESS     => false,
            // 超出体积上限立即中断
            CURLOPT_PROGRESSFUNCTION => function ($res, $dlTotal, $dlNow) {
                return ($dlNow > self::MAX_BODY * 8) ? 1 : 0;
            },
        ];
        // 将已经验证为公网的解析结果固定给 cURL，防止校验后 DNS
        // 再次解析到内网地址（DNS Rebinding）。IP 字面量无需设置。
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $resolveIp];
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            if ($err !== '') {
                Log::info('[SF-LIC] domain verify fetch failed: ' . $err);
            }
            return null;
        }
        return substr((string)$body, 0, self::MAX_BODY);
    }

    /**
     * 判断主机是否为可反向访问的公网地址（SSRF 防护）
     *
     * 拒绝：私有网段、回环、链路本地、保留地址、以及无法解析的主机。
     */
    public static function isPublicHost(string $host): bool
    {
        return self::resolvePublicIps($host) !== [];
    }

    /** @return string[] */
    private static function resolvePublicIps(string $host): array
    {
        $host = LicenseService::normalizeHost($host);
        if ($host === '' || $host === 'localhost') {
            return [];
        }
        // 必须是合法域名或 IP
        if (!filter_var($host, FILTER_VALIDATE_IP)
            && !preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $host)) {
            return [];
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            foreach ($records ?: [] as $r) {
                if (!empty($r['ip']))   { $ips[] = $r['ip']; }
                if (!empty($r['ipv6'])) { $ips[] = $r['ipv6']; }
            }
            if (!$ips) {
                $resolved = @gethostbyname($host);
                if ($resolved !== $host) {
                    $ips[] = $resolved;
                }
            }
        }

        if (!$ips) {
            return [];
        }

        foreach ($ips as $ip) {
            if (!filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                return []; // 只要有一条解析落在内网就整体拒绝
            }
        }
        return array_values(array_unique($ips));
    }

    private static function key(string $challenge): string
    {
        return 'sf_domain_challenge_' . $challenge;
    }
}
