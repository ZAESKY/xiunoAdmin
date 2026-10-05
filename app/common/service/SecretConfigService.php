<?php

namespace app\common\service;

/**
 * Encrypts administrator-entered provider credentials before they are stored
 * in QH_config. The encryption key stays in server environment configuration.
 */
final class SecretConfigService
{
    private const PREFIX = 'enc:v1:';

    public static function encrypt(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }
        self::assertSodium();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, self::key());
        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (strpos($stored, self::PREFIX) !== 0) {
            throw new \RuntimeException('敏感配置不是受支持的加密格式，请在后台重新填写');
        }
        self::assertSodium();
        $blob = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($blob === false || strlen($blob) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('敏感配置已损坏，请在后台重新填写');
        }
        $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        foreach (self::keyCandidates() as $key) {
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            if ($plain !== false) {
                return $plain;
            }
        }
        throw new \RuntimeException('敏感配置无法解密，请检查服务器加密密钥是否发生变化');
    }

    public static function isEncrypted(string $stored): bool
    {
        return strpos(trim($stored), self::PREFIX) === 0;
    }

    private static function key(): string
    {
        $keys = self::keyCandidates();
        return $keys[0];
    }

    /**
     * Try all configured server-side roots when decrypting so adding a
     * dedicated config_secret_key later does not invalidate older ciphertext.
     */
    private static function keyCandidates(): array
    {
        $sources = [
            (string)env('config_secret_key', ''),
            (string)env('security_pepper', ''),
            (string)env('license_secret_key', ''),
            (string)env('database_password', ''),
        ];
        $keys = [];
        foreach ($sources as $source) {
            $source = trim($source);
            if ($source !== '') {
                $key = hash('sha256', "QH_CONFIG_SECRET_V1\0" . $source, true);
                $keys[bin2hex($key)] = $key;
            }
        }
        if (empty($keys)) {
            throw new \RuntimeException('服务器缺少敏感配置加密密钥，请先配置 config_secret_key 或 security_pepper');
        }
        return array_values($keys);
    }

    private static function assertSodium(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new \RuntimeException('服务器缺少 sodium 扩展，不能安全保存短信密钥');
        }
    }
}
