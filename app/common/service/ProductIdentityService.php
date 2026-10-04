<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * 为客户端签发“稳定应用 ID -> 可变产品别名”的独立身份文件。
 *
 * 客户端只在加密核心中固定稳定的应用 ID；产品别名由本文件签名保护。
 * 因此产品改名不需要重新加密客户端核心，同时也不能通过替换另一个产品的
 * 元数据来复用授权。
 */
class ProductIdentityService
{
    public const SCHEMA_VERSION = 1;

    public static function issue(int $appId, string $productId, array $aliases = []): array
    {
        $productId = trim($productId);
        if ($appId <= 0 || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $productId)) {
            return ['ok' => false, 'msg' => '应用 ID 或产品标识格式无效', 'envelope' => []];
        }
        if (LicenseService::productAppId($productId) !== $appId) {
            return ['ok' => false, 'msg' => '产品标识与授权系统应用 ID 映射不一致', 'envelope' => []];
        }
        if (!CryptoService::configured(CryptoService::PURPOSE_RELEASE)) {
            return ['ok' => false, 'msg' => '发布签名密钥未配置', 'envelope' => []];
        }

        $normalizedAliases = [];
        foreach ($aliases as $alias) {
            $alias = trim((string)$alias);
            if ($alias === '' || hash_equals($productId, $alias)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $alias)) {
                return ['ok' => false, 'msg' => '历史产品标识格式无效', 'envelope' => []];
            }
            $normalizedAliases[$alias] = true;
        }
        if (count($normalizedAliases) > 10) {
            return ['ok' => false, 'msg' => '历史产品标识最多保留 10 个', 'envelope' => []];
        }

        $data = [
            'schema_version' => self::SCHEMA_VERSION,
            'kind'           => 'product_identity',
            'app_id'         => $appId,
            'product_id'     => $productId,
            'aliases'        => array_keys($normalizedAliases),
            'issued_at'      => time(),
            'document_id'    => bin2hex(random_bytes(16)),
        ];

        try {
            $signed = CryptoService::sign($data, CryptoService::PURPOSE_RELEASE);
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => '产品身份签名失败', 'envelope' => []];
        }
        $envelope = [
            'data'   => $data,
            'sig'    => (string)$signed['sig'],
            'key_id' => (string)$signed['key_id'],
        ];
        if (!self::verify($envelope, $appId, $productId)) {
            return ['ok' => false, 'msg' => '产品身份签名自检失败', 'envelope' => []];
        }
        return ['ok' => true, 'msg' => '', 'envelope' => $envelope];
    }

    public static function verify(array $envelope, int $expectedAppId = 0, string $expectedProductId = ''): bool
    {
        $data = $envelope['data'] ?? null;
        $signature = (string)($envelope['sig'] ?? '');
        $keyId = (string)($envelope['key_id'] ?? '');
        if (!is_array($data)
            || (int)($data['schema_version'] ?? 0) !== self::SCHEMA_VERSION
            || (string)($data['kind'] ?? '') !== 'product_identity'
            || (int)($data['app_id'] ?? 0) <= 0
            || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', (string)($data['product_id'] ?? ''))
            || (int)($data['issued_at'] ?? 0) <= 0
            || (int)$data['issued_at'] > time() + 300
            || !preg_match('/^[a-f0-9]{32}$/D', (string)($data['document_id'] ?? ''))
            || strpos($keyId, 'release-') !== 0
            || $signature === '') {
            return false;
        }
        $aliases = $data['aliases'] ?? [];
        if (!is_array($aliases) || count($aliases) > 10) {
            return false;
        }
        $seenAliases = [];
        foreach ($aliases as $alias) {
            if (!is_string($alias) || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $alias)
                || hash_equals((string)$data['product_id'], $alias) || isset($seenAliases[$alias])) {
                return false;
            }
            $seenAliases[$alias] = true;
        }
        if ($expectedAppId > 0 && (int)$data['app_id'] !== $expectedAppId) {
            return false;
        }
        if ($expectedProductId !== '' && !hash_equals($expectedProductId, (string)$data['product_id'])) {
            return false;
        }
        if (LicenseService::productAppId((string)$data['product_id']) !== (int)$data['app_id']) {
            return false;
        }
        $publicKeys = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
        return isset($publicKeys[$keyId])
            && CryptoService::verify($data, $signature, (string)$publicKeys[$keyId]);
    }
}
