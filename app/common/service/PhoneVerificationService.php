<?php

namespace app\common\service;

use think\facade\Cache;
use think\facade\Db;

final class PhoneVerificationService
{
    private const MAX_ATTEMPTS = 5;
    private const SCENES = ['bind', 'withdraw', 'rebate', 'plugin_reward', 'password_reset'];

    public static function requiredFor(string $scene): bool
    {
        $keys = [
            'withdraw' => 'sms_require_withdraw',
            'rebate' => 'sms_require_rebate',
            'plugin_reward' => 'sms_require_plugin_reward',
        ];
        return AliyunSmsService::enabled()
            && isset($keys[$scene])
            && (string)conf($keys[$scene]) === '1';
    }

    public static function status(int $userId): array
    {
        $user = Db::name('user')->where('id', $userId)->field('phone,phone_verified_at')->find();
        $phone = self::normalizePhone($user['phone'] ?? '');
        $verified = $phone !== '' && !empty($user['phone_verified_at'])
            && Db::name('user_phone_identity')->where('user_id', $userId)->where('phone_hash', self::phoneHash($phone))->find();
        return [
            'sms_enabled' => AliyunSmsService::enabled(),
            'sms_configured' => AliyunSmsService::configured(),
            'verified' => (bool)$verified,
            'phone' => $verified ? $phone : '',
            'phone_masked' => $verified ? self::maskPhone($phone) : '',
        ];
    }

    public static function sendCode(int $userId, string $scene, string $requestedPhone = ''): array
    {
        if (!in_array($scene, self::SCENES, true)) {
            return ['ok' => false, 'message' => '不支持的短信验证场景'];
        }
        if (!AliyunSmsService::enabled()) {
            return ['ok' => false, 'message' => '短信认证尚未启用'];
        }
        $purpose = self::purposeForScene($scene);
        if (!AliyunSmsService::configured($purpose)) {
            return ['ok' => false, 'message' => '短信服务配置不完整，请联系管理员'];
        }

        $status = self::status($userId);
        if ($scene === 'bind') {
            if ($status['verified']) {
                return ['ok' => false, 'message' => '当前账号已经绑定并验证手机号，如需更换请联系管理员'];
            }
            $phone = self::normalizePhone($requestedPhone);
            if ($phone === '') {
                return ['ok' => false, 'message' => '请输入正确的中国大陆手机号'];
            }
            if (Db::name('user_phone_identity')->where('phone_hash', self::phoneHash($phone))->find()) {
                return ['ok' => false, 'message' => '该手机号已绑定其他账号'];
            }
        } else {
            if (!$status['verified']) {
                return ['ok' => false, 'message' => '请先在个人中心绑定并验证手机号'];
            }
            $phone = $status['phone'];
        }

        $cooldownKey = self::cooldownKey($userId, $scene, $phone);
        if (Cache::get($cooldownKey)) {
            return ['ok' => false, 'message' => '请勿重复发送，60秒后再试'];
        }

        $ip = (string)get_client_ip();
        $dailyLimit = max(1, min(30, intval(conf('sms_daily_limit') ?: 10)));
        $limits = [
            RateLimitService::hit('sms_ip_hour', $ip, 20, 3600),
            RateLimitService::hit('sms_user_hour', (string)$userId, 10, 3600),
            RateLimitService::hit('sms_phone_day', self::phoneHash($phone), $dailyLimit, 86400),
        ];
        foreach ($limits as $limit) {
            if (!$limit['ok']) {
                self::audit(
                    $userId,
                    $scene,
                    $phone,
                    'limited',
                    '',
                    'LOCAL_RATE_LIMIT',
                    AliyunSmsService::templateCodeForPurpose($purpose)
                );
                return ['ok' => false, 'message' => '验证码发送过于频繁，请稍后再试'];
            }
        }
        $code = sprintf('%06d', random_int(0, 999999));
        $sent = AliyunSmsService::sendVerificationCode($phone, $code, $purpose);
        self::audit(
            $userId,
            $scene,
            $phone,
            $sent['ok'] ? 'sent' : 'failed',
            (string)$sent['request_id'],
            (string)$sent['error_code'],
            (string)($sent['template_code'] ?? AliyunSmsService::templateCodeForPurpose($purpose))
        );
        if (!$sent['ok']) {
            return ['ok' => false, 'message' => $sent['message']];
        }

        $ttl = max(120, min(600, intval(conf('sms_code_ttl') ?: 300)));
        $key = self::codeKey($userId, $scene, $phone);
        Cache::set($key, [
            'hash' => self::codeHash($userId, $scene, $phone, $code),
            'created_at' => time(),
            'attempts' => 0,
        ], $ttl);
        Cache::set($cooldownKey, 1, 60);
        return ['ok' => true, 'message' => '验证码已发送至 ' . self::maskPhone($phone), 'expires_in' => $ttl];
    }

    public static function bindPhone(int $userId, string $phone, string $code): array
    {
        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            return ['ok' => false, 'message' => '请输入正确的中国大陆手机号'];
        }
        $verified = self::verifyCode($userId, 'bind', $phone, $code, false);
        if (!$verified['ok']) {
            return $verified;
        }
        Db::startTrans();
        try {
            $user = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$user) {
                throw new \RuntimeException('账号不存在');
            }
            if (!empty($user['phone_verified_at'])) {
                Db::rollback();
                return ['ok' => false, 'message' => '当前账号已经绑定并验证手机号'];
            }
            $phoneHash = self::phoneHash($phone);
            if (Db::name('user_phone_identity')->where('phone_hash', $phoneHash)->lock(true)->find()) {
                Db::rollback();
                return ['ok' => false, 'message' => '该手机号已绑定其他账号'];
            }
            $now = datetime();
            Db::name('user_phone_identity')->insert([
                'user_id' => $userId,
                'phone_hash' => $phoneHash,
                'phone_last4' => substr($phone, -4),
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $updated = Db::name('user')->where('id', $userId)->whereNull('phone_verified_at')->update([
                'phone' => $phone,
                'phone_verified_at' => $now,
                'phone_verified_source' => 'aliyun_sms',
            ]);
            if ($updated !== 1) {
                throw new \RuntimeException('手机号绑定状态已变化');
            }
            Db::commit();
            self::consumeCode($userId, 'bind', $phone);
            self::audit($userId, 'bind', $phone, 'verified', '', '');
            return ['ok' => true, 'message' => '手机号绑定成功', 'phone_masked' => self::maskPhone($phone)];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['ok' => false, 'message' => strpos(strtolower($e->getMessage()), 'duplicate') !== false
                ? '该手机号已绑定其他账号'
                : '手机号绑定失败，请稍后重试'];
        }
    }

    /**
     * Synchronize a phone number edited by an administrator.
     * The caller must own the surrounding database transaction.
     */
    public static function syncAdminPhone(int $userId, string $requestedPhone, bool $verified): array
    {
        $rawPhone = trim($requestedPhone);
        $phone = $rawPhone === '' ? '' : self::normalizePhone($rawPhone);
        if ($rawPhone !== '' && $phone === '') {
            return ['ok' => false, 'message' => '请输入正确的中国大陆手机号'];
        }
        if ($verified && $phone === '') {
            return ['ok' => false, 'message' => '标记为已验证时必须填写手机号'];
        }

        $user = Db::name('user')
            ->where('id', $userId)
            ->field('id,phone,phone_verified_at,phone_verified_source')
            ->lock(true)
            ->find();
        if (!$user) {
            return ['ok' => false, 'message' => '用户不存在'];
        }

        if ($phone !== '') {
            $duplicateUser = Db::name('user')
                ->where('phone', $phone)
                ->where('id', '<>', $userId)
                ->lock(true)
                ->find();
            if ($duplicateUser) {
                return ['ok' => false, 'message' => '该手机号已被其他账号使用'];
            }
        }

        $identity = Db::name('user_phone_identity')->where('user_id', $userId)->lock(true)->find();
        $currentPhone = self::normalizePhone($user['phone'] ?? '');
        $currentVerified = $currentPhone !== ''
            && !empty($user['phone_verified_at'])
            && $identity
            && hash_equals((string)$identity['phone_hash'], self::phoneHash($currentPhone));
        if ($currentPhone === $phone && $currentVerified === $verified) {
            if ((string)($user['phone'] ?? '') !== $phone) {
                Db::name('user')->where('id', $userId)->update(['phone' => $phone]);
            }
            return ['ok' => true, 'message' => '手机号未变化'];
        }

        $phoneHash = $phone !== '' ? self::phoneHash($phone) : '';
        if ($verified) {
            $duplicateIdentity = Db::name('user_phone_identity')
                ->where('phone_hash', $phoneHash)
                ->where('user_id', '<>', $userId)
                ->lock(true)
                ->find();
            if ($duplicateIdentity) {
                return ['ok' => false, 'message' => '该手机号已绑定其他账号'];
            }
        }

        Db::name('user_phone_identity')->where('user_id', $userId)->delete();
        $now = datetime();
        Db::name('user')->where('id', $userId)->update([
            'phone' => $phone,
            'phone_verified_at' => $verified ? $now : null,
            'phone_verified_source' => $verified ? 'admin' : '',
        ]);
        if ($verified) {
            try {
                Db::name('user_phone_identity')->insert([
                    'user_id' => $userId,
                    'phone_hash' => $phoneHash,
                    'phone_last4' => substr($phone, -4),
                    'verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                return ['ok' => false, 'message' => '该手机号已绑定其他账号'];
            }
        }
        return ['ok' => true, 'message' => $phone === '' ? '手机号已解绑' : '手机号已更新'];
    }

    public static function verifySensitiveCode(int $userId, string $scene, string $code): array
    {
        $status = self::status($userId);
        if (!$status['verified']) {
            return ['ok' => false, 'message' => '请先在个人中心绑定并验证手机号'];
        }
        $result = self::verifyCode($userId, $scene, $status['phone'], $code, true);
        if ($result['ok']) {
            self::audit($userId, $scene, $status['phone'], 'verified', '', '');
        }
        return $result;
    }

    public static function normalizePhone($phone): string
    {
        $phone = preg_replace('/\s+/', '', trim((string)$phone));
        if (strpos($phone, '+86') === 0) {
            $phone = substr($phone, 3);
        } elseif (strpos($phone, '86') === 0 && strlen($phone) === 13) {
            $phone = substr($phone, 2);
        }
        return preg_match('/^1[3-9][0-9]{9}$/D', $phone) ? $phone : '';
    }

    public static function maskPhone(string $phone): string
    {
        return strlen($phone) === 11 ? substr($phone, 0, 3) . '****' . substr($phone, -4) : '';
    }

    private static function verifyCode(int $userId, string $scene, string $phone, string $code, bool $consume): array
    {
        $key = self::codeKey($userId, $scene, $phone);
        $entry = Cache::get($key);
        if (!is_array($entry) || empty($entry['hash'])) {
            return ['ok' => false, 'message' => '验证码已过期，请重新获取'];
        }
        $attempts = intval($entry['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            self::consumeCode($userId, $scene, $phone);
            return ['ok' => false, 'message' => '验证码尝试次数过多，请重新获取'];
        }
        $submittedHash = self::codeHash($userId, $scene, $phone, trim($code));
        if (!hash_equals((string)$entry['hash'], $submittedHash)) {
            $entry['attempts'] = $attempts + 1;
            $ttl = max(120, min(600, intval(conf('sms_code_ttl') ?: 300)));
            $remaining = max(1, $ttl - max(0, time() - intval($entry['created_at'] ?? time())));
            Cache::set($key, $entry, $remaining);
            return ['ok' => false, 'message' => '验证码错误或已过期'];
        }
        if ($consume) {
            self::consumeCode($userId, $scene, $phone);
        }
        return ['ok' => true, 'message' => '验证成功'];
    }

    private static function consumeCode(int $userId, string $scene, string $phone): void
    {
        Cache::delete(self::codeKey($userId, $scene, $phone));
    }

    private static function codeKey(int $userId, string $scene, string $phone): string
    {
        return 'sms_code:' . $userId . ':' . $scene . ':' . substr(self::phoneHash($phone), 0, 32);
    }

    private static function cooldownKey(int $userId, string $scene, string $phone): string
    {
        return 'sms_cooldown:' . $userId . ':' . $scene . ':' . substr(self::phoneHash($phone), 0, 32);
    }

    private static function codeHash(int $userId, string $scene, string $phone, string $code): string
    {
        return hash_hmac('sha256', $userId . '|' . $scene . '|' . $phone . '|' . $code, CryptoService::pepper());
    }

    private static function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', 'phone|' . $phone, CryptoService::pepper());
    }

    private static function purposeForScene(string $scene): string
    {
        if ($scene === 'bind') {
            return 'phone_bind';
        }
        if ($scene === 'password_reset') {
            return 'password_reset';
        }
        return 'phone_verify';
    }

    private static function audit(
        int $userId,
        string $scene,
        string $phone,
        string $status,
        string $requestId,
        string $errorCode,
        string $templateCode = ''
    ): void
    {
        try {
            Db::name('sms_audit')->insert([
                'user_id' => max(0, $userId),
                'scene' => sf_plain_text($scene, 32),
                'phone_hash' => self::phoneHash($phone),
                'phone_masked' => self::maskPhone($phone),
                'status' => sf_plain_text($status, 20),
                'provider_request_id' => sf_plain_text($requestId, 128),
                'error_code' => sf_plain_text($errorCode, 64),
                'template_code' => sf_plain_text($templateCode, 32),
                'ip_hash' => hash_hmac('sha256', (string)get_client_ip(), CryptoService::pepper()),
                'created_at' => datetime(),
            ]);
        } catch (\Throwable $e) {
            // SMS delivery must not expose audit storage failures to users.
        }
    }
}
