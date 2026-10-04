<?php

namespace app\common\service;

use think\facade\Cache;
use think\facade\Db;
use think\facade\Event;
use think\facade\Log;

/**
 * Password recovery by the administrator-selected verification channel.
 *
 * Verification codes are never stored in plaintext. Account mismatches use a
 * uniform success response on the send endpoint to avoid account enumeration.
 */
final class PasswordRecoveryService
{
    private const MAX_ATTEMPTS = 5;

    public static function channel(): string
    {
        $channel = strtolower(trim((string)conf('password_recovery_channel')));
        return in_array($channel, ['email', 'sms'], true) ? $channel : 'email';
    }

    public static function send(string $username, string $contact): array
    {
        $channel = self::channel();
        $username = self::normalizeUsername($username);
        $contact = self::normalizeContact($channel, $contact);
        if ($username === '' || $contact === '') {
            return message($channel === 'sms'
                ? t('password_recovery.invalid_phone')
                : t('password_recovery.invalid_email'), false);
        }

        $ip = (string)get_client_ip();
        $subject = strtolower($username) . '|' . $channel . '|' . $contact;
        foreach ([
            RateLimitService::hit('password_recovery_ip', $ip, 20, 3600),
            RateLimitService::hit('password_recovery_subject', $subject, 5, 900),
        ] as $limit) {
            if (!$limit['ok']) {
                return message(t('password_recovery.too_many_requests'), false);
            }
        }

        $user = self::findUser($username, $contact, $channel);
        if (!$user) {
            return message(t('password_recovery.sent_if_matched'), true);
        }

        if ($channel === 'sms') {
            $status = PhoneVerificationService::status(intval($user['id']));
            if (empty($status['verified']) || !hash_equals((string)$status['phone'], $contact)) {
                return message(t('password_recovery.sent_if_matched'), true);
            }
            $result = PhoneVerificationService::sendCode(intval($user['id']), 'password_reset');
            return message(
                $result['ok'] ? t('password_recovery.code_sent') : (string)$result['message'],
                (bool)$result['ok']
            );
        }

        $cooldownKey = self::cooldownKey(intval($user['id']), $channel, $contact);
        if (Cache::get($cooldownKey)) {
            return message(t('password_recovery.cooldown'), false);
        }

        $code = sprintf('%06d', random_int(0, 999999));
        $param = [
            'to' => $contact,
            'title' => conf('title') . ' - ' . t('password_recovery.mail_title'),
            'from_name' => conf('title'),
            'content' => t('password_recovery.mail_content', ['code' => $code]),
        ];
        try {
            $results = Event::trigger('ChangeBindingMailNotice', $param);
            $provider = $results[0] ?? null;
            if (is_string($provider)) {
                $decoded = json_decode($provider, true);
                $provider = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($provider) || intval($provider['code'] ?? -1) !== 0) {
                Log::warning('Password recovery email dispatch failed', [
                    'user_id' => intval($user['id']),
                    'provider_message' => is_array($provider) ? sf_plain_text($provider['msg'] ?? '', 160) : 'invalid response',
                ]);
                return message(t('password_recovery.mail_failed'), false);
            }

            self::storeEmailCode(intval($user['id']), $contact, $code);
            Cache::set($cooldownKey, 1, 60);
            return message(t('password_recovery.code_sent'), true);
        } catch (\Throwable $e) {
            Log::error('Password recovery email dispatch exception: ' . $e->getMessage(), [
                'user_id' => intval($user['id']),
            ]);
            return message(t('password_recovery.mail_failed'), false);
        }
    }

    public static function reset(string $username, string $contact, string $code, string $newPassword): array
    {
        $channel = self::channel();
        $username = self::normalizeUsername($username);
        $contact = self::normalizeContact($channel, $contact);
        $code = trim($code);

        if ($username === '' || $contact === '' || !preg_match('/^[0-9]{6}$/D', $code)) {
            return message(t('password_recovery.invalid_account_or_code'), false);
        }
        $passwordLength = strlen($newPassword);
        if ($passwordLength < 6 || $passwordLength > 72) {
            return message(t('password_recovery.password_length'), false);
        }

        $user = self::findUser($username, $contact, $channel);
        if (!$user) {
            return message(t('password_recovery.invalid_account_or_code'), false);
        }
        foreach ([
            RateLimitService::hit('password_recovery_verify_ip', (string)get_client_ip(), 30, 3600),
            RateLimitService::hit('password_recovery_verify_user', (string)$user['id'], 10, 900),
        ] as $limit) {
            if (!$limit['ok']) {
                return message(t('password_recovery.too_many_attempts'), false);
            }
        }

        if ($channel === 'sms') {
            $status = PhoneVerificationService::status(intval($user['id']));
            if (empty($status['verified']) || !hash_equals((string)$status['phone'], $contact)) {
                return message(t('password_recovery.invalid_account_or_code'), false);
            }
            $verified = PhoneVerificationService::verifySensitiveCode(intval($user['id']), 'password_reset', $code);
        } else {
            $verified = self::verifyEmailCode(intval($user['id']), $contact, $code);
        }
        if (!$verified['ok']) {
            return message((string)$verified['message'], false);
        }

        try {
            $updated = Db::name('user')->where('id', intval($user['id']))->update([
                'password' => sf_password_make($newPassword),
            ]);
            if ($updated === false) {
                throw new \RuntimeException('password update failed');
            }
            if ($channel === 'email') {
                Cache::delete(self::codeKey(intval($user['id']), $channel, $contact));
            }
            Log::notice('User password reset completed', [
                'user_id' => intval($user['id']),
                'channel' => $channel,
                'ip_hash' => hash_hmac('sha256', (string)get_client_ip(), CryptoService::pepper()),
            ]);
            return message(t('password_recovery.reset_success'), true);
        } catch (\Throwable $e) {
            Log::error('User password reset failed: ' . $e->getMessage(), [
                'user_id' => intval($user['id']),
                'channel' => $channel,
            ]);
            return message(t('password_recovery.reset_failed'), false);
        }
    }

    private static function findUser(string $username, string $contact, string $channel): ?array
    {
        $query = Db::name('user')->where('username', $username);
        if ($channel === 'sms') {
            $query->where('phone', $contact)->whereNotNull('phone_verified_at');
        } else {
            $query->where('email', $contact);
        }
        $user = $query->field('id,username,email,phone,phone_verified_at')->find();
        return $user ?: null;
    }

    private static function normalizeUsername(string $username): string
    {
        $username = trim($username);
        return mb_strlen($username, 'UTF-8') <= 150 ? $username : '';
    }

    private static function normalizeContact(string $channel, string $contact): string
    {
        if ($channel === 'sms') {
            return PhoneVerificationService::normalizePhone($contact);
        }
        $contact = strtolower(trim($contact));
        return filter_var($contact, FILTER_VALIDATE_EMAIL) && strlen($contact) <= 255 ? $contact : '';
    }

    private static function storeEmailCode(int $userId, string $email, string $code): void
    {
        Cache::set(self::codeKey($userId, 'email', $email), [
            'hash' => self::codeHash($userId, 'email', $email, $code),
            'attempts' => 0,
            'created_at' => time(),
        ], self::ttl());
    }

    private static function verifyEmailCode(int $userId, string $email, string $code): array
    {
        $key = self::codeKey($userId, 'email', $email);
        $entry = Cache::get($key);
        if (!is_array($entry) || empty($entry['hash'])) {
            return ['ok' => false, 'message' => t('password_recovery.code_expired')];
        }
        $attempts = intval($entry['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::delete($key);
            return ['ok' => false, 'message' => t('password_recovery.too_many_attempts')];
        }
        if (!hash_equals((string)$entry['hash'], self::codeHash($userId, 'email', $email, $code))) {
            $entry['attempts'] = $attempts + 1;
            $remaining = max(1, self::ttl() - max(0, time() - intval($entry['created_at'] ?? time())));
            Cache::set($key, $entry, $remaining);
            return ['ok' => false, 'message' => t('password_recovery.code_invalid')];
        }
        return ['ok' => true, 'message' => t('password_recovery.verified')];
    }

    private static function codeKey(int $userId, string $channel, string $contact): string
    {
        return 'password_recovery:' . $userId . ':' . $channel . ':'
            . substr(hash_hmac('sha256', $contact, CryptoService::pepper()), 0, 32);
    }

    private static function cooldownKey(int $userId, string $channel, string $contact): string
    {
        return self::codeKey($userId, $channel, $contact) . ':cooldown';
    }

    private static function codeHash(int $userId, string $channel, string $contact, string $code): string
    {
        return hash_hmac('sha256', $userId . '|' . $channel . '|' . $contact . '|' . $code, CryptoService::pepper());
    }

    private static function ttl(): int
    {
        return max(120, min(600, intval(conf('sms_code_ttl') ?: 300)));
    }
}
