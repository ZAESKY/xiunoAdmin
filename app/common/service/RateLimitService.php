<?php

namespace app\common\service;

use think\facade\Cache;

/**
 * 频率限制（固定窗口计数）
 *
 * 用于授权激活、状态查询、下载凭证等接口，抬高批量枚举与盗用成本。
 *
 * 说明：固定窗口在窗口边界存在最多 2 倍突发的已知特性，
 * 对本场景（防批量枚举，而非精确配额）是可接受的取舍；
 * 若将来需要平滑限流，可在 P5 换成 Redis 滑动窗口。
 *
 * @since 2026-08-16 P2
 */
class RateLimitService
{
    /**
     * @param string $bucket 限流分组，如 'activate'
     * @param string $key    限流主体，如 IP 或 license_id（内部会哈希）
     * @param int    $limit  窗口内最大次数
     * @param int    $window 窗口长度（秒）
     * @return array ['ok'=>bool,'remaining'=>int,'retry_after'=>int]
     */
    public static function hit(string $bucket, string $key, int $limit, int $window): array
    {
        $window = max(1, $window);
        $slot = (int)floor(time() / $window);
        $cacheKey = 'sf_rl_' . $bucket . '_' . substr(hash('sha256', $key), 0, 24) . '_' . $slot;

        $count = (int)Cache::get($cacheKey, 0);
        if ($count >= $limit) {
            return [
                'ok'          => false,
                'remaining'   => 0,
                'retry_after' => ($slot + 1) * $window - time(),
            ];
        }

        Cache::set($cacheKey, $count + 1, $window + 5);

        return [
            'ok'          => true,
            'remaining'   => max(0, $limit - $count - 1),
            'retry_after' => 0,
        ];
    }

    /**
     * 只读检查，不计数
     */
    public static function peek(string $bucket, string $key, int $limit, int $window): bool
    {
        $window = max(1, $window);
        $slot = (int)floor(time() / $window);
        $cacheKey = 'sf_rl_' . $bucket . '_' . substr(hash('sha256', $key), 0, 24) . '_' . $slot;
        return (int)Cache::get($cacheKey, 0) < $limit;
    }
}
