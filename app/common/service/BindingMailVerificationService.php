<?php

namespace app\common\service;

use think\facade\Cache;

/**
 * 邮箱换绑验证码状态。
 *
 * 验证码同时绑定用户、验证阶段和邮箱地址，避免同一验证码被用于其他邮箱。
 */
final class BindingMailVerificationService
{
    public const CODE_TTL = 300;
    public const OLD_VERIFIED_TTL = 600;
    public const MAX_ATTEMPTS = 5;

    public static function normalizeEmail($email): string
    {
        return strtolower(trim((string)$email));
    }

    public static function codeKey(int $userId, string $stage, string $email): string
    {
        return 'change_binding_mail:code:' . $userId . ':' . $stage . ':' . hash('sha256', self::normalizeEmail($email));
    }

    public static function cooldownKey(int $userId, string $stage, string $email): string
    {
        return 'change_binding_mail:cooldown:' . $userId . ':' . $stage . ':' . hash('sha256', self::normalizeEmail($email));
    }

    public static function oldVerifiedKey(int $userId, string $email): string
    {
        return 'change_binding_mail:old_verified:' . $userId . ':' . hash('sha256', self::normalizeEmail($email));
    }

    public static function storeCode(int $userId, string $stage, string $email, string $code): void
    {
        $key = self::codeKey($userId, $stage, $email);
        Cache::set($key, $code, self::CODE_TTL);
        Cache::delete($key . ':attempts');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function verifyCode(int $userId, string $stage, string $email, string $submittedCode): array
    {
        $key = self::codeKey($userId, $stage, $email);
        $cachedCode = (string)Cache::get($key, '');
        if ($cachedCode === '') {
            return ['ok' => false, 'message' => '验证码已过期，请重新获取'];
        }

        $attemptKey = $key . ':attempts';
        $attempts = (int)Cache::get($attemptKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::delete($key);
            Cache::delete($attemptKey);
            return ['ok' => false, 'message' => '验证码尝试次数过多，请重新获取'];
        }

        if ($submittedCode === '' || !hash_equals($cachedCode, $submittedCode)) {
            $attempts++;
            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::delete($key);
                Cache::delete($attemptKey);
                return ['ok' => false, 'message' => '验证码尝试次数过多，请重新获取'];
            }
            Cache::set($attemptKey, $attempts, self::CODE_TTL);
            return ['ok' => false, 'message' => '验证码错误或已过期'];
        }

        return ['ok' => true, 'message' => ''];
    }

    public static function consumeCode(int $userId, string $stage, string $email): void
    {
        $key = self::codeKey($userId, $stage, $email);
        Cache::delete($key);
        Cache::delete($key . ':attempts');
    }

    public static function markOldVerified(int $userId, string $email): void
    {
        Cache::set(self::oldVerifiedKey($userId, $email), 1, self::OLD_VERIFIED_TTL);
    }

    public static function isOldVerified(int $userId, string $email): bool
    {
        return (bool)Cache::get(self::oldVerifiedKey($userId, $email));
    }

    public static function clearOldVerified(int $userId, string $email): void
    {
        Cache::delete(self::oldVerifiedKey($userId, $email));
    }

    public static function clearCompletedFlow(int $userId, string $oldEmail, string $newEmail): void
    {
        if ($oldEmail !== '') {
            self::consumeCode($userId, 'old', $oldEmail);
            self::clearOldVerified($userId, $oldEmail);
        }
        self::consumeCode($userId, 'new', $newEmail);
    }
}
