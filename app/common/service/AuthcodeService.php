<?php

namespace app\common\service;

/**
 * 授权码哈希服务（P1）
 *
 * 修复 A-13：授权码此前在服务端 QH_auth.authcode 与客户端 authCode 表均为明文，
 * 任一侧数据库或备份泄露即导致授权码批量外泄。
 *
 * 方案：
 *   - 库中保存 HMAC-SHA256(pepper, authcode)，pepper 置于环境变量不入库
 *   - 同时保存 last4 供客服人工核对
 *   - 支持 pepper 版本号，便于将来在不停机的前提下渐进式重哈希
 *   - 过渡期双读：新库查哈希，查不到再回落明文列（明文列清空后自动失效）
 *
 * @since 2026-08-16 P1
 */
class AuthcodeService
{
    /** 当前 pepper 版本。轮换 pepper 时 +1，并保留旧版本用于渐进式重哈希。 */
    public const PEPPER_VERSION = 1;

    /**
     * 计算授权码哈希
     */
    public static function hash(string $authcode, int $version = self::PEPPER_VERSION): string
    {
        return hash_hmac('sha256', self::normalize($authcode), self::pepperFor($version));
    }

    /**
     * 常量时间比对
     */
    public static function verify(string $authcode, string $storedHash, int $version = self::PEPPER_VERSION): bool
    {
        if ($authcode === '' || $storedHash === '') {
            return false;
        }
        return hash_equals($storedHash, self::hash($authcode, $version));
    }

    /**
     * 尾 4 位，供人工核对与审计展示（需求 10：日志不得含完整授权码）
     */
    public static function last4(string $authcode): string
    {
        $authcode = self::normalize($authcode);
        return $authcode === '' ? '' : substr($authcode, -4);
    }

    /**
     * 审计展示形式
     */
    public static function mask(string $authcode): string
    {
        $last4 = self::last4($authcode);
        return $last4 === '' ? '-' : '***' . $last4;
    }

    /**
     * 授权码归一化：去空白、统一小写。
     * 客户从邮件或工单复制时常带空格或大小写差异，归一化可显著减少「明明是对的却提示错误」。
     */
    public static function normalize(string $authcode): string
    {
        return strtolower(preg_replace('/\s+/', '', trim($authcode)) ?? '');
    }

    /**
     * 格式校验：当前签发为 32 位十六进制；历史码同样是 32 位 md5，格式一致。
     */
    public static function looksValid(string $authcode): bool
    {
        return (bool)preg_match('/^[a-f0-9]{32}$/', self::normalize($authcode));
    }

    /**
     * 取指定版本的 pepper。
     * 版本 1 使用 security_pepper；后续版本使用 security_pepper_v<N>。
     */
    private static function pepperFor(int $version): string
    {
        if ($version <= 1) {
            return CryptoService::pepper();
        }
        $key = 'security_pepper_v' . $version;
        $value = trim((string)env($key, ''));
        if ($value === '') {
            throw new \RuntimeException('未配置 ' . $key);
        }
        return $value;
    }
}
