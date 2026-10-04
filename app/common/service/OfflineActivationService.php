<?php

namespace app\common\service;

use think\facade\Db;

/**
 * 离线激活（用户需求 ⑤）
 *
 * 适用场景：内网站点、未解析域名、防火墙拦截反向探测、本地开发环境
 * （ServerBay / 宝塔内网机 等）—— 这些站点无法通过域名归属证明。
 *
 * 流程：
 *   1. 主题后台生成【申请码】(request code)  —— 明文可读的 base64url，含站点信息
 *   2. 用户登录授权站，粘贴申请码
 *   3. 授权站校验授权码归属与名额，生成【激活凭证】(activation code)
 *      —— Ed25519 签名，绑定 site_id，单次使用，72 小时有效
 *   4. 用户粘回主题后台，主题用内置公钥验签后写入本地凭据
 *
 * 安全性：
 *   - 凭证绑定 site_id，换个站点粘贴无效
 *   - 服务端记账，同一凭证只能核销一次
 *   - 身份验证由「登录购买账号」承担，替代域名归属证明
 *
 * @since 2026-08-16 P2
 */
class OfflineActivationService
{
    /** 激活凭证有效期 */
    public const CODE_TTL = 259200; // 72 小时

    /**
     * 解析主题生成的申请码
     *
     * @return array|null 解析失败返回 null
     */
    public static function parseRequest(string $requestCode): ?array
    {
        $raw = CryptoService::b64uDecode(trim($requestCode));
        if ($raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        foreach (['product_id', 'host', 'install_uuid', 'authcode_hint', 'ts'] as $k) {
            if (!array_key_exists($k, $data)) {
                return null;
            }
        }

        // 申请码本身也有时效，避免陈旧申请被反复提交
        if (abs(time() - (int)$data['ts']) > 604800) {
            return null;
        }

        $data['host'] = LicenseService::normalizeHost((string)$data['host']);
        $data['site_id'] = LicenseService::siteId($data['host'], (string)$data['install_uuid']);
        return $data;
    }

    /**
     * 生成申请码（主题侧同款实现，服务端保留一份用于测试与客服复现）
     */
    public static function buildRequest(string $productId, string $host, string $installUuid, string $authcodeLast4, array $env = []): string
    {
        return CryptoService::b64uEncode(json_encode([
            'v'             => 2,
            'product_id'    => $productId,
            'host'          => LicenseService::normalizeHost($host),
            'install_uuid'  => $installUuid,
            'authcode_hint' => $authcodeLast4,
            'bbs_version'   => (string)($env['bbs_version'] ?? ''),
            'php_version'   => (string)($env['php_version'] ?? ''),
            'ts'            => time(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 签发激活凭证
     *
     * @param array $license 已校验过归属与名额的授权行
     * @param array $req     parseRequest() 的结果
     */
    public static function issue(array $license, array $req): array
    {
        if (!CryptoService::configured(CryptoService::PURPOSE_LICENSE)) {
            return ['ok' => false, 'code' => '5000', 'msg' => '服务端签名密钥未配置', 'data' => []];
        }

        $credential = LicenseAuthService::issueOrReuseSecret((string)$license['license_id']);
        if (!$credential['ok']) {
            return [
                'ok' => false,
                'code' => (string)$credential['code'],
                'msg' => (string)$credential['msg'],
                'data' => [],
            ];
        }
        $clientSecret = (string)$credential['client_secret'];
        $nonce  = bin2hex(random_bytes(16));

        $effectiveStatus = LicenseService::effectiveStatus($license);
        // 首次离线激活会在本方法返回前建立绑定并把 pending 转为 active，
        // 因而签名授权文件也必须反映激活后的最终状态。
        if ($effectiveStatus === LicenseService::STATUS_PENDING) {
            $effectiveStatus = LicenseService::STATUS_ACTIVE;
        }

        $payload = [
            'v'              => 2,
            'license_id'     => (string)$license['license_id'],
            'license_secret' => $clientSecret,
            'site_id'        => (string)$req['site_id'],
            'host'           => (string)$req['host'],
            'product_id'     => (string)$req['product_id'],
            'status'         => $effectiveStatus,
            'permanent'      => (int)$license['permanent'] === 1,
            'expires_at'     => !empty($license['expires_at']) ? strtotime((string)$license['expires_at']) : 0,
            'channel'        => (string)$license['channel'],
            'grace_period'   => LicenseService::gracePeriod($license),
            'failure_mode'   => LicenseService::failureMode(),
            'bound_host'     => (string)$req['host'],
            'nonce'          => $nonce,
            'issued_at'      => time(),
            'code_expires_at'=> time() + self::CODE_TTL,
        ];

        $payload['license_file'] = LicenseSignatureService::signLicenseFile($payload, [
            'license_id' => (string)$license['license_id'],
            'site_id'    => (string)$req['site_id'],
            'product_id' => (string)$req['product_id'],
            'host'       => (string)$req['host'],
        ]);

        $signed = CryptoService::sign($payload, CryptoService::PURPOSE_LICENSE);

        // 记账：同一凭证只能核销一次
        Db::name('offline_activation')->insert([
            'license_id' => (string)$license['license_id'],
            'site_id'    => (string)$req['site_id'],
            'nonce'      => $nonce,
            'host'       => (string)$req['host'],
            'used'       => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + self::CODE_TTL),
            'created_at' => datetime(),
        ]);

        LicenseService::event((string)$license['license_id'], 'offline_issue', 'success', [
            'site_id' => (string)$req['site_id'],
            'credential_reused' => !empty($credential['reused']) ? 1 : 0,
        ]);

        $envelope = CryptoService::b64uEncode(json_encode([
            'data'   => $payload,
            'sig'    => $signed['sig'],
            'key_id' => $signed['key_id'],
        ], JSON_UNESCAPED_UNICODE));

        return [
            'ok'   => true,
            'code' => '0',
            'msg'  => '激活凭证已生成',
            'data' => [
                'activation_code' => $envelope,
                'expires_in'      => self::CODE_TTL,
                'site_id'         => (string)$req['site_id'],
            ],
        ];
    }

    /**
     * 核销：主题激活成功后回调（可选，用于服务端记账）
     */
    public static function redeemNonce(string $licenseId, string $nonce): bool
    {
        $affected = Db::name('offline_activation')
            ->where('license_id', $licenseId)
            ->where('nonce', $nonce)
            ->where('used', 0)
            ->where('expires_at', '>', datetime())
            ->update(['used' => 1, 'used_at' => datetime()]);
        return (int)$affected === 1;
    }

}
