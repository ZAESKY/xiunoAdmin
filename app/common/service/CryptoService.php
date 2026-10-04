<?php

namespace app\common\service;

/**
 * 密码学基座
 *
 * 职责：
 *   - Ed25519 签名 / 验签（响应签名、更新清单签名、离线激活凭证签名）
 *   - 确定性 JSON 序列化（签名前的规范化，两端必须逐字节一致）
 *   - 密钥装载与轮换（key_id 多密钥并存）
 *   - HMAC 工具（请求签名、pepper 派生）
 *
 * 密钥来源一律为环境变量，绝不写入代码或数据库。
 * 生成方式见： php think sf:keygen
 *
 * @since 2026-08-16 P2
 */
class CryptoService
{
    /** 用途标识：授权响应签名 */
    public const PURPOSE_LICENSE = 'license';
    /** 用途标识：发布物（更新包 / 补丁清单）签名 */
    public const PURPOSE_RELEASE = 'release';

    /**
     * 确定性 JSON 序列化
     *
     * 规则（客户端必须实现完全相同的规则）：
     *   1. 对象键按 UTF-8 码点升序排列，递归生效
     *   2. 不含任何多余空白
     *   3. 斜杠与 Unicode 不转义
     *   4. 只允许 string / int / bool / null / array —— 浮点数在跨语言下
     *      存在表示歧义，一律拒绝，调用方需自行转成字符串
     *
     * @throws \InvalidArgumentException 载荷含浮点数或对象
     */
    public static function canonicalJson($data): string
    {
        return json_encode(
            self::canonicalize($data),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private static function canonicalize($data)
    {
        if (is_float($data)) {
            throw new \InvalidArgumentException('canonicalJson: 载荷不允许出现浮点数，请转为字符串');
        }
        if (is_object($data)) {
            throw new \InvalidArgumentException('canonicalJson: 载荷不允许出现对象，请转为数组');
        }
        if (!is_array($data)) {
            return $data;
        }

        // 列表保持顺序，关联数组按键排序
        $isList = array_keys($data) === range(0, count($data) - 1);
        if ($isList) {
            return array_map([self::class, 'canonicalize'], $data);
        }

        ksort($data, SORT_STRING);
        $out = [];
        foreach ($data as $k => $v) {
            $out[(string)$k] = self::canonicalize($v);
        }
        return $out;
    }

    /* ==================== Ed25519 ==================== */

    /**
     * 生成一对 Ed25519 密钥（base64）
     *
     * @return array ['public' => base64, 'secret' => base64, 'key_id' => string]
     */
    public static function generateKeypair(string $purpose): array
    {
        self::assertSodium();
        $pair   = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($pair);
        $public = sodium_crypto_sign_publickey($pair);

        return [
            'purpose' => $purpose,
            'key_id'  => $purpose . '-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(4)), 0, 8),
            'public'  => base64_encode($public),
            'secret'  => base64_encode($secret),
        ];
    }

    /**
     * 用私钥对规范化载荷签名
     *
     * @param array  $payload 待签名载荷
     * @param string $purpose PURPOSE_LICENSE / PURPOSE_RELEASE
     * @return array ['sig' => base64url, 'key_id' => string]
     */
    public static function sign(array $payload, string $purpose): array
    {
        self::assertSodium();
        [$secret, $keyId] = self::secretKey($purpose);

        $message = self::canonicalJson($payload);
        $sig     = sodium_crypto_sign_detached($message, $secret);

        return [
            'sig'    => self::b64uEncode($sig),
            'key_id' => $keyId,
        ];
    }

    /**
     * 用公钥验签（服务端自检 / 单元测试用；客户端有自己的轻量实现）
     */
    public static function verify(array $payload, string $signature, string $publicKeyB64): bool
    {
        self::assertSodium();
        $public = base64_decode($publicKeyB64, true);
        if ($public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }
        $sig = self::b64uDecode($signature);
        if ($sig === '' || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($sig, self::canonicalJson($payload), $public);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 取当前生效的公钥列表（含历史密钥，供轮换过渡期分发给客户端）
     *
     * 环境变量格式： <purpose>_sign_public_keys = keyid1:base64,keyid2:base64
     * 未配置时回退到单密钥 <purpose>_sign_public_key / <purpose>_sign_key_id
     *
     * @return array [key_id => base64_public]
     */
    public static function publicKeys(string $purpose): array
    {
        $multi = trim((string)env($purpose . '_sign_public_keys', ''));
        $keys  = [];

        if ($multi !== '') {
            foreach (explode(',', $multi) as $entry) {
                $entry = trim($entry);
                if ($entry === '' || strpos($entry, ':') === false) {
                    continue;
                }
                [$id, $pub] = explode(':', $entry, 2);
                $id = trim($id);
                $pub = trim($pub);
                if ($id !== '' && $pub !== '') {
                    $keys[$id] = $pub;
                }
            }
        }

        $single = trim((string)env($purpose . '_sign_public_key', ''));
        if ($single !== '') {
            $keys[self::keyId($purpose)] = $single;
        }

        return $keys;
    }

    public static function keyId(string $purpose): string
    {
        $id = trim((string)env($purpose . '_sign_key_id', ''));
        return $id !== '' ? $id : $purpose . '-default';
    }

    /**
     * 是否已完成密钥配置。未配置时上层应拒绝签发而不是降级为不签名。
     */
    public static function configured(string $purpose): bool
    {
        return trim((string)env($purpose . '_sign_secret_key', '')) !== '';
    }

    /**
     * @return array [binary_secret_key, key_id]
     */
    private static function secretKey(string $purpose): array
    {
        $b64 = trim((string)env($purpose . '_sign_secret_key', ''));
        if ($b64 === '') {
            throw new \RuntimeException(sprintf(
                '未配置 %s_sign_secret_key。请先执行 php think sf:keygen %s 并写入 .env',
                $purpose,
                $purpose
            ));
        }
        $secret = base64_decode($b64, true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \RuntimeException(sprintf('%s_sign_secret_key 格式错误', $purpose));
        }
        return [$secret, self::keyId($purpose)];
    }

    /* ==================== HMAC / 通用工具 ==================== */

    /**
     * 服务端 pepper：用于授权码哈希、IP 哈希等不可逆变换
     */
    public static function pepper(): string
    {
        $pepper = trim((string)env('security_pepper', ''));
        if ($pepper === '') {
            // 未配置时退回既有全局常量，保证功能可用；生产环境应显式配置
            $pepper = function_exists('sf_password_hash') ? sf_password_hash() : 'SF_DEFAULT_PEPPER';
        }
        return $pepper;
    }

    public static function hmac(string $message, string $key): string
    {
        return hash_hmac('sha256', $message, $key);
    }

    /* ==================== base64url ==================== */

    public static function b64uEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($s, true);
        return $out === false ? '' : $out;
    }

    private static function assertSodium(): void
    {
        if (!function_exists('sodium_crypto_sign_detached')) {
            throw new \RuntimeException('缺少 sodium 扩展，无法进行 Ed25519 签名');
        }
    }
}
