<?php

namespace app\common\service;

use think\facade\Db;

/**
 * 一次性下载票据服务
 *
 * 修复 A-07：原实现使用 md5(uniqid()) 作为下载凭证，写入缓存 43200 秒，
 * 可无限次重复使用、不绑定任何身份、且随响应经明文 HTTP 下发。
 *
 * 本实现：
 *   - CSPRNG 生成 32 字节票据（64 hex）
 *   - 库中只存 SHA-256 哈希，数据库泄露不产生可直接使用的票据
 *   - 单次消费（原子 UPDATE ... WHERE used = 0，以受影响行数为准）
 *   - 默认 600 秒过期
 *   - 绑定 appid / 授权记录 / 来源 IP（IP 绑定可配置关闭）
 *   - 载荷只存 ID 引用，不存 authcode 等敏感值（一并修复 A-25）
 *
 * @since 2026-08-16 P0 安全加固
 */
class SecureTicketService
{
    /** 默认有效期（秒）。客户端 checkUpdate 后会立即下载，600 秒足够容错。 */
    public const DEFAULT_TTL = 600;

    /** 票据格式：64 位小写十六进制 */
    private const PATTERN = '/^[a-f0-9]{64}$/';

    private const TABLE = 'download_ticket';

    /**
     * 签发票据
     *
     * @param array $payload 只允许放 ID 类引用，例如 ['version_id'=>1,'auth_id'=>2,'appid'=>3]
     * @param array $binding ['appid'=>int,'auth_id'=>int,'ip'=>string]
     * @param int   $ttl     有效期秒数
     * @return string 明文票据（仅此一次返回，服务端不再持有明文）
     */
    public static function issue(array $payload, array $binding = [], int $ttl = self::DEFAULT_TTL): string
    {
        $ticket = bin2hex(random_bytes(32));

        Db::name(self::TABLE)->insert([
            'ticket_hash' => hash('sha256', $ticket),
            'appid'       => (int)($binding['appid'] ?? 0),
            'auth_id'     => (int)($binding['auth_id'] ?? 0),
            'ip_hash'     => self::hashIp($binding['ip'] ?? ''),
            'payload'     => json_encode(self::sanitizePayload($payload), JSON_UNESCAPED_UNICODE),
            'used'        => 0,
            'expires_at'  => date('Y-m-d H:i:s', time() + max(60, $ttl)),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $ticket;
    }

    /**
     * 消费票据。成功返回载荷数组；任何一项校验不通过返回 null。
     *
     * 注意：本方法有副作用（置 used=1），调用方在同一请求内只应调用一次。
     *
     * @param string $ticket 明文票据
     * @param string $ip     当前请求来源 IP
     */
    public static function consume(string $ticket, string $ip = ''): ?array
    {
        if (!self::isWellFormed($ticket)) {
            return null;
        }

        $row = Db::name(self::TABLE)->where('ticket_hash', hash('sha256', $ticket))->find();
        if (empty($row)) {
            return null;
        }
        if ((int)$row['used'] === 1) {
            return null;
        }
        if (strtotime($row['expires_at']) < time()) {
            return null;
        }

        // IP 绑定（默认开启；多出口 IP 的客户可在后台关闭）
        if (self::ipBindingEnabled() && !empty($row['ip_hash'])) {
            $current = self::hashIp($ip);
            if ($current === '' || !hash_equals((string)$row['ip_hash'], $current)) {
                return null;
            }
        }

        // 原子消费：以受影响行数为准，杜绝并发双取
        $affected = Db::name(self::TABLE)
            ->where('id', (int)$row['id'])
            ->where('used', 0)
            ->update([
                'used'    => 1,
                'used_at' => date('Y-m-d H:i:s'),
            ]);

        if ((int)$affected !== 1) {
            return null;
        }

        $payload = json_decode((string)$row['payload'], true);
        if (!is_array($payload)) {
            return null;
        }

        $payload['_appid']   = (int)$row['appid'];
        $payload['_auth_id'] = (int)$row['auth_id'];
        $payload['_license_id'] = (string)($row['license_id'] ?? '');
        return $payload;
    }

    /**
     * 仅做格式判断，不触库。用于区分新票据与历史 md5 凭证。
     */
    public static function isWellFormed(string $ticket): bool
    {
        return $ticket !== '' && (bool)preg_match(self::PATTERN, $ticket);
    }

    /**
     * 清理过期票据。由下载流程顺带调用，避免额外定时任务。
     */
    public static function purgeExpired(int $keepHours = 24): void
    {
        try {
            Db::name(self::TABLE)
                ->where('expires_at', '<', date('Y-m-d H:i:s', time() - $keepHours * 3600))
                ->limit(500)
                ->delete();
        } catch (\Throwable $e) {
            // 清理失败不影响下载主流程
        }
    }

    /**
     * IP 以 HMAC 存储，避免审计表中出现明文 IP。
     * pepper 优先取环境变量，未配置时退回应用密钥，保证功能可用。
     */
    private static function hashIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }
        return hash_hmac('sha256', $ip, self::pepper());
    }

    private static function pepper(): string
    {
        $pepper = (string)env('security_pepper', '');
        if ($pepper === '') {
            // 退回到既有全局常量盐值，保证未配置 .env 的环境仍可运行
            $pepper = function_exists('qh_password_hash') ? qh_password_hash() : 'QH_DEFAULT_PEPPER';
        }
        return $pepper;
    }

    private static function ipBindingEnabled(): bool
    {
        $value = conf('download_ticket_bind_ip');
        return $value === null ? true : ((int)$value === 1);
    }

    /**
     * 载荷白名单：只允许 ID 类字段进入票据，杜绝 authcode 等敏感值落库。
     */
    private static function sanitizePayload(array $payload): array
    {
        $allow = [
            'version_id', 'auth_id', 'appid', 'type', 'download_catalogue', 'version', 'edition',
            'kind', 'release_id', 'patch_id', 'plugin_id', 'site_id',
        ];
        $out   = [];
        foreach ($allow as $key) {
            if (array_key_exists($key, $payload)) {
                $out[$key] = $payload[$key];
            }
        }
        return $out;
    }
}
