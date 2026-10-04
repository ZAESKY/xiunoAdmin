<?php

namespace app\common\service;

use think\facade\Log;

/**
 * API 错误脱敏服务
 *
 * 修复 A-17：异常信息(数据库错误、文件路径、类名、堆栈)此前被直接拼接进对外 JSON。
 * 统一改为：详情写服务端日志 + 对外只返回稳定文案与 trace_id。
 *
 * @since 2026-08-16 P0 安全加固
 */
class ApiErrorService
{
    /**
     * 生成一次请求的追踪号
     */
    public static function traceId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            // random_bytes 在极端环境下可能不可用，降级但不中断
            return substr(md5(uniqid('', true)), 0, 16);
        }
    }

    /**
     * 记录异常详情到服务端日志，返回对外安全的响应体
     *
     * @param \Throwable $e         原始异常（仅进日志，绝不外发）
     * @param string     $publicKey 对外文案的 i18n key
     * @param array      $context   附加上下文（仅进日志）
     * @return array     message() 结构
     */
    public static function fail(\Throwable $e, string $publicKey = 'common.server_error', array $context = []): array
    {
        $traceId = self::traceId();
        self::logDetail($traceId, $e->getMessage(), $e->getFile(), $e->getLine(), $context);
        return message(t($publicKey), false, ['trace_id' => $traceId]);
    }

    /**
     * 业务性拒绝（非异常）：同样只回稳定文案 + trace_id
     *
     * @param string $publicKey 对外文案的 i18n key
     * @param string $internal  仅写日志的内部原因
     */
    public static function reject(string $publicKey, string $internal = '', array $context = []): array
    {
        $traceId = self::traceId();
        if ($internal !== '') {
            self::logDetail($traceId, $internal, '', 0, $context);
        }
        return message(t($publicKey), false, ['trace_id' => $traceId]);
    }

    /**
     * 写日志。任何日志失败都不得影响主流程。
     */
    private static function logDetail(string $traceId, string $message, string $file, int $line, array $context): void
    {
        try {
            $location = $file !== '' ? sprintf(' | %s:%d', $file, $line) : '';
            $extra    = $context ? ' | ' . json_encode(self::scrub($context), JSON_UNESCAPED_UNICODE) : '';
            $url      = '';
            try {
                $url = ' | ' . request()->method() . ' ' . request()->baseUrl();
            } catch (\Throwable $ignore) {
            }
            Log::error(sprintf('[SF-API][%s] %s%s%s%s', $traceId, $message, $location, $url, $extra));
        } catch (\Throwable $ignore) {
            // 日志通道故障时静默，保证接口仍能返回
        }
    }

    /**
     * 上下文脱敏：审计与日志中不得出现完整授权码、密钥、密码等敏感值。
     * 采用「白名单式截断」——敏感键一律只留尾 4 位。
     */
    public static function scrub(array $context): array
    {
        $sensitive = [
            'authcode', 'auth_code', 'api_key', 'apikey', 'password', 'passwd',
            'token', 'user_token', 'secret', 'license_secret', 'private_key', 'sign',
        ];

        $out = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $out[$key] = self::scrub($value);
                continue;
            }
            $lower = strtolower((string)$key);
            $hit   = false;
            foreach ($sensitive as $needle) {
                if (strpos($lower, $needle) !== false) {
                    $hit = true;
                    break;
                }
            }
            if ($hit && is_string($value) && $value !== '') {
                $out[$key] = '***' . substr($value, -4);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
