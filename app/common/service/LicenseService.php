<?php

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 授权核心业务（v2）
 *
 * 覆盖：激活、旧码兑换、状态查询、换绑、测试通道、单站绑定、试用、审计。
 *
 * 设计要点：
 *   - 每授权独立密钥（license_secret），分发包内不含任何共享主密钥（A-08）
 *   - 授权码只存哈希（A-13）
 *   - 站点身份 = SHA-256(归一化域名 | install_uuid)，绑定需域名归属证明（A-10）
 *   - 一个授权仅绑定一个规范化域名；www 与裸域视为同一站点
 *   - 审计不落完整授权码与明文 IP（需求 ⑩）
 *
 * @since 2026-08-16 P2
 */
class LicenseService
{
    /** Xiuno 轻鸿主题在授权系统中的稳定应用 ID；产品别名可以独立改名。 */
    public const THEME_APPLICATION_ID = 1;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REVOKED   = 'revoked';
    public const STATUS_EXPIRED   = 'expired';

    public const ROLE_PRIMARY   = 'primary';
    /** 仅用于兼容历史绑定数据；新绑定不会再创建 secondary 记录。 */
    public const ROLE_SECONDARY = 'secondary';

    /** 产品业务规则：每个授权仅允许一个规范化域名。 */
    public const MAX_SITES_PER_LICENSE = 1;

    public const CHANNEL_STABLE = 'stable';
    public const CHANNEL_BETA   = 'beta';

    /** 默认失效宽限期：24 小时。可由服务端显式策略覆盖。 */
    public const GRACE_DEFAULT = 86400;
    /** 盗版/未激活站点的试用期：7 天（用户需求 ③） */
    public const TRIAL_SECONDS = 604800;

    /* ==================== 站点身份 ==================== */

    /**
     * 域名归一化：小写、去端口、去尾点、去 www. 前缀
     *
     * www 与裸域视为同一站点，不会触发换绑。
     */
    public static function normalizeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }

        // parse_url 能正确处理协议、端口、用户信息、子目录与 [IPv6]:port。
        // 无协议输入先补一个虚拟协议，仅用于解析，最终只保留 host。
        $candidate = preg_match('#^[a-z][a-z0-9+.-]*://#i', $host) ? $host : 'http://' . ltrim($host, '/');
        $parsed = parse_url($candidate, PHP_URL_HOST);
        if (!is_string($parsed) || $parsed === '') {
            return '';
        }
        $host = strtolower(trim($parsed, '[]'));
        $host = rtrim($host, '.');
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host)) {
            $ascii = defined('INTL_IDNA_VARIANT_UTS46')
                ? @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : @idn_to_ascii($host);
            if (is_string($ascii) && $ascii !== '') {
                $host = strtolower($ascii);
            }
        }
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        return strlen($host) <= 253 ? $host : '';
    }

    /**
     * 站点指纹
     */
    public static function siteId(string $host, string $installUuid): string
    {
        return hash('sha256', self::normalizeHost($host) . '|' . trim($installUuid));
    }

    /**
     * 可注册根域（用于判断二级域名是否属于同一主域）
     *
     * 说明：这里使用精简后缀表而非完整 Public Suffix List。
     * 覆盖常见的多段后缀（com.cn / co.uk 等），未覆盖的按「最后两段」处理。
     * 如果将来出现误判，扩充 MULTI_SUFFIX 即可，不影响其它逻辑。
     */
    private const MULTI_SUFFIX = [
        'com.cn', 'net.cn', 'org.cn', 'gov.cn', 'edu.cn', 'ac.cn', 'mil.cn',
        'co.uk', 'org.uk', 'me.uk', 'ac.uk', 'gov.uk',
        'com.hk', 'com.tw', 'com.au', 'net.au', 'org.au',
        'co.jp', 'or.jp', 'ne.jp', 'com.sg', 'com.my', 'co.kr',
        'com.br', 'com.mx', 'co.in', 'com.tr', 'co.za',
    ];

    public static function registrableRoot(string $host): string
    {
        $host = self::normalizeHost($host);
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $parts = explode('.', $host);
        $n = count($parts);
        if ($n <= 2) {
            return $host;
        }
        $lastTwo = $parts[$n - 2] . '.' . $parts[$n - 1];
        if (in_array($lastTwo, self::MULTI_SUFFIX, true) && $n >= 3) {
            return $parts[$n - 3] . '.' . $lastTwo;
        }
        return $lastTwo;
    }

    /* ==================== 查询 ==================== */

    public static function findByLicenseId(string $licenseId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $licenseId)) {
            return null;
        }
        $row = Db::name('license')->where('license_id', $licenseId)->find();
        return is_array($row) ? self::syncSourceAuth($row) : null;
    }

    public static function findByAuthcode(string $authcode, string $productId): ?array
    {
        $hash = AuthcodeService::hash($authcode);
        $rows = Db::name('license')
            ->where('authcode_hash', $hash)
            ->where('product_id', $productId)
            ->limit(2)
            ->select()
            ->toArray();
        // 历史系统可能给两条独立购买记录生成同一个授权码。未提供来源授权
        // ID、登录账号或精确域名时，绝不能猜一条返回。
        return count($rows) === 1 ? self::syncSourceAuth($rows[0]) : null;
    }

    /**
     * 服务端产品标识到旧授权应用的明确映射。
     * 格式：license_product_app_map=product_a:1,product_b:2
     */
    public static function productAppId(string $productId): int
    {
        $productId = trim($productId);
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $productId)) {
            return 0;
        }
        $raw = trim((string)env('license_product_app_map', ''));
        foreach (explode(',', $raw) as $entry) {
            $parts = array_map('trim', explode(':', $entry, 2));
            if (count($parts) !== 2 || !hash_equals($productId, $parts[0])) {
                continue;
            }
            return ctype_digit($parts[1]) ? max(0, (int)$parts[1]) : 0;
        }
        return 0;
    }

    /**
     * 旧应用 ID 反查 v2 产品标识。映射必须一一对应；若同一应用配置了多个
     * 产品则拒绝自动发布，避免把安装包签给错误产品。
     */
    public static function productIdForApp(int $appId): string
    {
        if ($appId <= 0) {
            return '';
        }
        $matches = [];
        $raw = trim((string)env('license_product_app_map', ''));
        foreach (explode(',', $raw) as $entry) {
            $parts = array_map('trim', explode(':', $entry, 2));
            if (count($parts) !== 2
                || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $parts[0])
                || !ctype_digit($parts[1])
                || (int)$parts[1] !== $appId) {
                continue;
            }
            $matches[$parts[0]] = true;
        }
        return count($matches) === 1 ? (string)array_key_first($matches) : '';
    }

    /**
     * 解析 v2 授权；若授权仍只存在于 SF_auth，则在严格产品/账号边界内按需迁移。
     *
     * 在线激活没有登录账号，必须让申请站点与旧授权域名一致；离线激活由登录
     * 管理账号或当前绑定账号提供归属证明，允许本地开发域名与旧授权域名不同。
     */
    public static function resolveForActivation(
        string $authcode,
        string $productId,
        string $host,
        int $operatorUserId = 0
    ): array {
        $authcode = AuthcodeService::normalize($authcode);
        $productId = trim($productId);
        $host = self::normalizeHost($host);
        if (!AuthcodeService::looksValid($authcode)) {
            return self::err('4001', '授权码格式错误');
        }

        $appId = self::productAppId($productId);
        if ($appId <= 0) {
            return self::err('4006', '该产品尚未在授权站配置');
        }

        $hash = AuthcodeService::hash($authcode);
        $query = Db::name('auth')
            ->where('appid', $appId)
            ->where(function ($query) use ($hash, $authcode) {
                $query->where('authcode_hash', $hash)->whereOr('authcode', $authcode);
            });
        if ($operatorUserId > 0) {
            $query->where(function ($query) use ($operatorUserId) {
                $query->where('userid', $operatorUserId)->whereOr('bindingid', $operatorUserId);
            });
        }
        $rows = $query
            ->field('id,appid,userid,bindingid,auth_info,authcode,authcode_hash,pepper_version,status,permanent_switch,endtime,beta')
            ->order('id', 'desc')
            ->limit(50)
            ->select()
            ->toArray();

        $matches = [];
        foreach ($rows as $row) {
            if (self::authRowMatchesCode($row, $authcode)) {
                $matches[] = $row;
            }
        }
        if (!$matches) {
            // v2 原生授权（例如旧快照兑换）没有 source_auth_id。只有在候选
            // 唯一时才允许按授权码直接命中，避免历史重复授权码串到别的记录。
            $native = Db::name('license')
                ->where('authcode_hash', $hash)
                ->where('product_id', $productId)
                ->where(function ($query) {
                    $query->whereNull('source_auth_id')->whereOr('source_auth_id', 0);
                })
                ->limit(2)
                ->select()
                ->toArray();
            if (count($native) === 1) {
                $license = self::syncSourceAuth($native[0]);
                if ($operatorUserId > 0 && (int)($license['user_id'] ?? 0) > 0
                    && (int)$license['user_id'] !== $operatorUserId) {
                    return self::err('4003', '该授权不属于当前账号');
                }
                return self::resolved($license);
            }
            return self::err('4001', '授权码无效');
        }

        $source = self::selectActivationSource($matches, $host, $operatorUserId);
        if ($source === null) {
            return self::err('4005', '授权记录中的站点与当前站点不匹配，请使用离线激活');
        }

        Db::startTrans();
        try {
            $locked = Db::name('auth')->where('id', (int)$source['id'])->lock(true)->find();
            if (!is_array($locked)
                || (int)$locked['appid'] !== $appId
                || ($operatorUserId > 0
                    && (int)$locked['userid'] !== $operatorUserId
                    && (int)$locked['bindingid'] !== $operatorUserId)
                || !self::authRowMatchesCode($locked, $authcode)
            ) {
                Db::rollback();
                return self::err('4001', '授权码无效');
            }

            $sourceExisting = Db::name('license')
                ->where('product_id', $productId)
                ->where('source_auth_id', (int)$locked['id'])
                ->find();
            if (is_array($sourceExisting)) {
                Db::commit();
                if (AuthcodeService::verify($authcode, (string)$sourceExisting['authcode_hash'], (int)$sourceExisting['pepper_version'])) {
                    return self::resolved(self::syncSourceAuth($sourceExisting));
                }
                return self::err('4003', '该旧授权已经升级，请使用升级后的授权码');
            }

            $licenseId = bin2hex(random_bytes(16));
            Db::name('license')->insert([
                'license_id'       => $licenseId,
                'product_id'       => $productId,
                'appid'            => $appId,
                'user_id'          => (int)$locked['userid'],
                'authcode_hash'    => $hash,
                'authcode_last4'   => AuthcodeService::last4($authcode),
                'pepper_version'   => AuthcodeService::PEPPER_VERSION,
                'secret_hash'      => '',
                'status'           => (int)$locked['status'] === 1 ? self::STATUS_PENDING : self::STATUS_SUSPENDED,
                'permanent'        => (int)$locked['permanent_switch'] === 1 ? 1 : 0,
                'expires_at'       => (int)$locked['permanent_switch'] === 1 ? null : self::legacyExpiry($locked['endtime'] ?? null),
                'channel'          => (int)$locked['beta'] === 1 ? self::CHANNEL_BETA : self::CHANNEL_STABLE,
                'max_sites'        => self::MAX_SITES_PER_LICENSE,
                'allow_cross_root' => 0,
                'rebind_limit'     => 3,
                'rebind_count'     => 0,
                'migrated_from'    => 0,
                'source_auth_id'   => (int)$locked['id'],
                'created_at'       => datetime(),
                'updated_at'       => datetime(),
            ]);
            Db::name('auth')->where('id', (int)$locked['id'])->update([
                'authcode_hash'  => $hash,
                'authcode_last4' => AuthcodeService::last4($authcode),
                'must_rotate'    => 0,
                'pepper_version' => AuthcodeService::PEPPER_VERSION,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $retry = Db::name('license')
                ->where('product_id', $productId)
                ->where('source_auth_id', (int)($source['id'] ?? 0))
                ->find();
            if (is_array($retry)
                && AuthcodeService::verify($authcode, (string)$retry['authcode_hash'], (int)$retry['pepper_version'])) {
                return self::resolved(self::syncSourceAuth($retry));
            }
            Log::error('[SF-LIC] legacy authorization bridge failed', [
                'product_id' => $productId,
                'source_auth_id' => (int)($source['id'] ?? 0),
                'exception_type' => get_class($e),
            ]);
            return self::err('5000', '授权迁移失败，请稍后重试');
        }

        $license = self::findByLicenseId($licenseId);
        if ($license === null) {
            return self::err('5000', '授权迁移失败，请稍后重试');
        }
        self::event($licenseId, 'legacy_bridge', 'success', [
            'source_auth_id' => (int)$source['id'],
        ]);
        return self::resolved($license);
    }

    /**
     * 从可能使用同一历史授权码的多条旧授权中选择唯一实例。
     * 在线流程必须精确匹配原授权域名；离线流程由管理/绑定账号隔离，账号内
     * 仍优先匹配域名，允许本地开发域名变化时使用该账号最近的授权记录。
     */
    private static function selectActivationSource(array $matches, string $host, int $operatorUserId): ?array
    {
        $eligible = [];
        $hostMatches = [];
        foreach ($matches as $row) {
            if ($operatorUserId > 0
                && (int)($row['userid'] ?? 0) !== $operatorUserId
                && (int)($row['bindingid'] ?? 0) !== $operatorUserId) {
                continue;
            }
            $eligible[] = $row;
            if ($host !== '' && hash_equals(self::normalizeHost((string)($row['auth_info'] ?? '')), $host)) {
                $hostMatches[] = $row;
            }
        }
        if (count($hostMatches) === 1) {
            return $hostMatches[0];
        }
        if (count($hostMatches) > 1) {
            return null;
        }
        if ($operatorUserId > 0 && count($eligible) === 1) {
            return $eligible[0];
        }
        // 同一账号中若仍有多条重复码且域名也无法区分，拒绝猜测。
        return null;
    }

    /**
     * 登录用户是否仍是该授权的管理者或最终绑定者。
     *
     * 对旧 SF_auth 桥接授权实时读取关系，解绑后立即失去离线签发权限；原生
     * v2 授权则继续以 user_id 为唯一账号归属。不能仅凭授权码通过此检查。
     */
    public static function operatorCanUseLicense(array $license, int $operatorUserId): bool
    {
        if ($operatorUserId <= 0) {
            return false;
        }
        $sourceAuthId = (int)($license['source_auth_id'] ?? 0);
        if ($sourceAuthId > 0) {
            $source = Db::name('auth')
                ->where('id', $sourceAuthId)
                ->field('userid,bindingid')
                ->find();
            return is_array($source)
                && ((int)$source['userid'] === $operatorUserId
                    || (int)$source['bindingid'] === $operatorUserId);
        }
        return (int)($license['user_id'] ?? 0) === $operatorUserId;
    }

    private static function resolved(array $license): array
    {
        return ['ok' => true, 'code' => '0', 'msg' => 'ok', 'data' => ['license' => $license]];
    }

    private static function authRowMatchesCode(array $row, string $authcode): bool
    {
        $storedHash = (string)($row['authcode_hash'] ?? '');
        if ($storedHash !== '') {
            try {
                if (AuthcodeService::verify($authcode, $storedHash, (int)($row['pepper_version'] ?? 1))) {
                    return true;
                }
            } catch (\Throwable $e) {
                // 过渡期继续尝试仍保留的旧明文列。
            }
        }
        $plain = AuthcodeService::normalize((string)($row['authcode'] ?? ''));
        return $plain !== '' && hash_equals($plain, $authcode);
    }

    private static function legacyExpiry($value): ?string
    {
        $timestamp = strtotime((string)$value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 让旧后台里的续期、停用和测试通道设置继续约束已经迁移的 v2 授权。
     */
    private static function syncSourceAuth(array $license): array
    {
        $sourceAuthId = (int)($license['source_auth_id'] ?? 0);
        if ($sourceAuthId <= 0) {
            return $license;
        }
        $source = Db::name('auth')->where('id', $sourceAuthId)->find();
        $updates = [];
        if (!is_array($source)) {
            if ((string)$license['status'] !== self::STATUS_REVOKED) {
                $updates['status'] = self::STATUS_REVOKED;
            }
        } else {
            $sourceHash = (string)($source['authcode_hash'] ?? '');
            if ($sourceHash === '' && !empty($source['authcode'])) {
                $sourceHash = AuthcodeService::hash((string)$source['authcode']);
            }
            $configuredAppId = self::productAppId((string)$license['product_id']);
            $sourceMatches = $sourceHash !== ''
                && hash_equals((string)$license['authcode_hash'], $sourceHash)
                && ($configuredAppId <= 0 || (int)$source['appid'] === $configuredAppId);
            if (!$sourceMatches) {
                $updates['status'] = self::STATUS_SUSPENDED;
            } elseif ((string)$license['status'] !== self::STATUS_REVOKED) {
                $updates['status'] = (int)$source['status'] === 1
                    ? ((string)$license['status'] === self::STATUS_PENDING ? self::STATUS_PENDING : self::STATUS_ACTIVE)
                    : self::STATUS_SUSPENDED;
                $updates['user_id'] = (int)$source['userid'];
                $updates['permanent'] = (int)$source['permanent_switch'] === 1 ? 1 : 0;
                $updates['expires_at'] = (int)$source['permanent_switch'] === 1
                    ? null
                    : self::legacyExpiry($source['endtime'] ?? null);
                $updates['channel'] = (int)$source['beta'] === 1 ? self::CHANNEL_BETA : self::CHANNEL_STABLE;
            }
        }

        $changed = [];
        foreach ($updates as $key => $value) {
            if (($license[$key] ?? null) != $value) {
                $changed[$key] = $value;
                $license[$key] = $value;
            }
        }
        if ($changed) {
            $changed['updated_at'] = datetime();
            Db::name('license')->where('license_id', $license['license_id'])->update($changed);
        }
        return $license;
    }

    public static function sites(string $licenseId): array
    {
        $rows = Db::name('license_site')
            ->where('license_id', $licenseId)
            ->where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return self::uniqueSiteRows($rows);
    }

    public static function findSite(string $licenseId, string $siteId): ?array
    {
        $row = Db::name('license_site')
            ->where('license_id', $licenseId)
            ->where('site_id', $siteId)
            ->where('status', 1)
            ->find();
        return is_array($row) ? $row : null;
    }

    /**
     * 建立或恢复一个已经完成域名/账号归属证明的站点绑定。
     * 精确 site_id 命中时幂等刷新；域名相同但 install_uuid 改变时视为
     * 同站点重装，并把历史重复行标记为已解绑。
     */
    public static function bindVerifiedSite(
        array $license,
        string $host,
        string $installUuid,
        string $verifyMethod
    ): array {
        $licenseId = (string)($license['license_id'] ?? '');
        $host = self::normalizeHost($host);
        $installUuid = trim($installUuid);
        $siteId = self::siteId($host, $installUuid);
        $verifyMethod = trim($verifyMethod);
        if (!preg_match('/^[a-f0-9]{32}$/D', $licenseId)
            || $host === '' || $installUuid === '' || strlen($installUuid) > 64
            || !preg_match('/^[a-f0-9]{64}$/D', $siteId)) {
            return self::err('4001', '站点信息不完整');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,16}$/D', $verifyMethod)) {
            $verifyMethod = 'verified';
        }

        Db::startTrans();
        try {
            $lockedLicense = Db::name('license')
                ->where('license_id', $licenseId)
                ->lock(true)
                ->find();
            if (!is_array($lockedLicense)) {
                Db::rollback();
                return self::err('4001', '授权记录不存在');
            }

            $rows = Db::name('license_site')
                ->where('license_id', $licenseId)
                ->lock(true)
                ->order('id', 'asc')
                ->select()
                ->toArray();
            $activeRows = [];
            $sameHost = [];
            foreach ($rows as $row) {
                if ((int)($row['status'] ?? 0) !== 1) {
                    continue;
                }
                $activeRows[] = $row;
                if (self::normalizeHost((string)($row['host'] ?? '')) === $host) {
                    $sameHost[] = $row;
                }
            }

            // site_id 是全表唯一键。允许恢复本授权以前解绑的同站点记录，
            // 但绝不允许覆盖属于其它授权的记录。
            $collision = Db::name('license_site')->where('site_id', $siteId)->lock(true)->find();
            if (is_array($collision) && (string)$collision['license_id'] !== $licenseId) {
                Db::rollback();
                return self::err('4002', '该站点已绑定其它授权，请先在原授权中解绑');
            }

            if (!empty($sameHost) || is_array($collision)) {
                $keeper = is_array($collision) ? $collision : null;
                if ($keeper === null) {
                    foreach ($sameHost as $row) {
                        if ((string)($row['site_id'] ?? '') === $siteId) {
                            $keeper = $row;
                            break;
                        }
                    }
                }
                if ($keeper === null) {
                    foreach ($sameHost as $row) {
                        if ((string)($row['role'] ?? '') === self::ROLE_PRIMARY) {
                            $keeper = $row;
                            break;
                        }
                    }
                }
                if ($keeper === null) {
                    $keeper = $sameHost[0];
                }

                // 单站点授权中，唯一有效绑定始终是 primary。历史 secondary
                // 记录在同域名恢复时自动归一，避免旧数据延续多站语义。
                $role = self::ROLE_PRIMARY;

                $disableIds = [];
                foreach ($sameHost as $row) {
                    if ((int)$row['id'] !== (int)$keeper['id']) {
                        $disableIds[] = (int)$row['id'];
                    }
                }
                if ($disableIds) {
                    Db::name('license_site')->whereIn('id', $disableIds)->update([
                        'status' => 0,
                        'last_seen_at' => datetime(),
                    ]);
                }

                $identityChanged = (string)($keeper['site_id'] ?? '') !== $siteId
                    || (string)($keeper['install_uuid'] ?? '') !== $installUuid
                    || (int)($keeper['status'] ?? 0) !== 1
                    || !empty($disableIds);
                Db::name('license_site')->where('id', (int)$keeper['id'])->update([
                    'site_id'       => $siteId,
                    'host'          => $host,
                    'role'          => $role,
                    'install_uuid'  => $installUuid,
                    'verify_method' => $verifyMethod,
                    'verified_at'   => datetime(),
                    'status'        => 1,
                    'last_seen_at'  => datetime(),
                ]);

                $licenseUpdate = ['last_seen_at' => datetime(), 'updated_at' => datetime()];
                if ($role === self::ROLE_PRIMARY) {
                    $licenseUpdate['bound_host'] = $host;
                    if (empty($lockedLicense['bound_at'])) {
                        $licenseUpdate['bound_at'] = datetime();
                    }
                    if ((string)$lockedLicense['status'] === self::STATUS_PENDING) {
                        $licenseUpdate['status'] = self::STATUS_ACTIVE;
                    }
                }
                Db::name('license')->where('license_id', $licenseId)->update($licenseUpdate);
                Db::commit();
                return ['ok' => true, 'code' => '0', 'msg' => $identityChanged ? '同一站点绑定已恢复' : '幂等激活', 'data' => [
                    'site_id' => $siteId,
                    'host' => $host,
                    'role' => $role,
                    'new' => false,
                    'recovered' => $identityChanged,
                ]];
            }

            $uniqueSites = self::uniqueSiteRows($activeRows);
            if (!empty($uniqueSites)) {
                Db::rollback();
                return self::err('4002', '该授权已绑定其它域名，请先解绑后再绑定当前域名');
            }

            $role = self::ROLE_PRIMARY;

            Db::name('license_site')->insert([
                'license_id'    => $licenseId,
                'site_id'       => $siteId,
                'host'          => $host,
                'role'          => $role,
                'install_uuid'  => $installUuid,
                'verify_method' => $verifyMethod,
                'verified_at'   => datetime(),
                'status'        => 1,
                'first_seen_at' => datetime(),
                'last_seen_at'  => datetime(),
            ]);
            $licenseUpdate = ['last_seen_at' => datetime(), 'updated_at' => datetime()];
            if ($role === self::ROLE_PRIMARY) {
                $licenseUpdate['bound_host'] = $host;
                $licenseUpdate['bound_at'] = datetime();
                if ((string)$lockedLicense['status'] === self::STATUS_PENDING) {
                    $licenseUpdate['status'] = self::STATUS_ACTIVE;
                }
            }
            Db::name('license')->where('license_id', $licenseId)->update($licenseUpdate);
            Db::commit();
            return ['ok' => true, 'code' => '0', 'msg' => '激活成功', 'data' => [
                'site_id' => $siteId,
                'host' => $host,
                'role' => $role,
                'new' => true,
                'recovered' => false,
            ]];
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('[SF-LIC] bind verified site failed: ' . $e->getMessage());
            return self::err('5000', '站点绑定失败，请稍后重试');
        }
    }

    private static function uniqueSiteRows(array $rows): array
    {
        $unique = [];
        $order = [];
        foreach ($rows as $row) {
            $host = self::normalizeHost((string)($row['host'] ?? ''));
            $key = $host !== '' ? 'host:' . $host : 'site:' . (string)($row['site_id'] ?? '');
            if (!isset($unique[$key])) {
                $unique[$key] = $row;
                $order[] = $key;
                continue;
            }
            if ((string)($unique[$key]['role'] ?? '') !== self::ROLE_PRIMARY
                && (string)($row['role'] ?? '') === self::ROLE_PRIMARY) {
                $unique[$key] = $row;
            }
        }
        $result = [];
        foreach ($order as $key) {
            $result[] = $unique[$key];
        }
        return $result;
    }

    /* ==================== 状态计算 ==================== */

    /**
     * 计算授权对外状态。永久授权不因时间失效，只因吊销/停用失效。
     */
    public static function effectiveStatus(array $license): string
    {
        $status = (string)$license['status'];
        if ($status === self::STATUS_REVOKED || $status === self::STATUS_SUSPENDED) {
            return $status;
        }
        if ((int)$license['permanent'] === 1) {
            return $status === self::STATUS_ACTIVE ? self::STATUS_ACTIVE : $status;
        }
        if (!empty($license['expires_at']) && strtotime((string)$license['expires_at']) <= time()) {
            return self::STATUS_EXPIRED;
        }
        return $status;
    }

    public static function gracePeriod(array $license): int
    {
        $configured = (int)env('license_grace_seconds', self::GRACE_DEFAULT);
        return max(0, min($configured, 2592000));
    }

    public static function failureMode(): string
    {
        $mode = strtolower(trim((string)env('license_failure_mode', 'grace')));
        return in_array($mode, ['grace', 'immediate'], true) ? $mode : 'grace';
    }

    /**
     * 组装下发给客户端的状态载荷（会被 Ed25519 签名）
     */
    public static function statusPayload(array $license, string $siteId): array
    {
        $status = self::effectiveStatus($license);
        return [
            'status'        => $status,
            'permanent'     => (int)$license['permanent'] === 1,
            'expires_at'    => !empty($license['expires_at']) ? strtotime((string)$license['expires_at']) : 0,
            'channel'       => (string)$license['channel'],
            'grace_period'  => self::gracePeriod($license),
            'failure_mode'  => self::failureMode(),
            'bound_host'    => (string)($license['bound_host'] ?? ''),
            'max_sites'     => self::MAX_SITES_PER_LICENSE,
            'site_count'    => count(self::sites((string)$license['license_id'])),
            'rebind_remain' => max(0, (int)$license['rebind_limit'] - (int)$license['rebind_count']),
            'authcode_last4'=> (string)($license['authcode_last4'] ?? ''),
        ];
    }

    /* ==================== 激活 ==================== */

    /**
     * 激活授权
     *
     * @param array $in ['authcode','product_id','host','install_uuid','verified'(bool),'verify_method']
     * @return array ['ok'=>bool,'code'=>string,'msg'=>string,'data'=>array]
     */
    public static function activate(array $in): array
    {
        $authcode = AuthcodeService::normalize((string)($in['authcode'] ?? ''));
        $productId = (string)($in['product_id'] ?? '');
        $host = self::normalizeHost((string)($in['host'] ?? ''));
        $installUuid = trim((string)($in['install_uuid'] ?? ''));

        if ($host === '' || $installUuid === '') {
            return self::err('4001', '站点信息不完整');
        }

        /*
         * 允许调用方传入已解析的 license（兑换后补绑站点的场景）。
         * 该分支不接受外部输入，只由 redeem() 内部使用。
         */
        if (!empty($in['_license']) && is_array($in['_license'])) {
            $license = $in['_license'];
        } else {
            if (!AuthcodeService::looksValid($authcode)) {
                return self::err('4001', '授权码格式错误');
            }
            $resolved = self::resolveForActivation($authcode, $productId, $host, 0);
            if (!$resolved['ok']) {
                return $resolved;
            }
            $license = $resolved['data']['license'];
        }

        $status = self::effectiveStatus($license);
        if ($status === self::STATUS_REVOKED) {
            return self::err('4003', '该授权已被吊销');
        }
        if ($status === self::STATUS_SUSPENDED) {
            return self::err('4003', '该授权已被停用');
        }
        if ($status === self::STATUS_EXPIRED) {
            return self::err('4004', '该授权已过期');
        }

        $siteId = self::siteId($host, $installUuid);

        // 域名归属证明
        $verified = !empty($in['verified']);
        if (!$verified) {
            return self::err('4005', '域名归属证明未通过，请改用离线激活');
        }
        $binding = self::bindVerifiedSite(
            $license,
            $host,
            $installUuid,
            (string)($in['verify_method'] ?? 'http')
        );
        if (!$binding['ok']) {
            return $binding;
        }
        if ((string)($binding['data']['role'] ?? '') === self::ROLE_PRIMARY
            && (string)$license['status'] === self::STATUS_PENDING) {
            $license['status'] = self::STATUS_ACTIVE;
        }
        return self::issueSecret(
            $license,
            $siteId,
            $host,
            (string)$binding['msg'],
            !empty($binding['data']['new'])
        );
    }

    /**
     * 生成并保存 license_secret。
     * 明文只在本次响应中出现一次，服务端只留哈希。
     */
    private static function issueSecret(array $license, string $siteId, string $host, string $msg, bool $isNew): array
    {
        $credential = LicenseAuthService::issueOrReuseSecret((string)$license['license_id']);
        if (!$credential['ok']) {
            return self::err((string)$credential['code'], (string)$credential['msg']);
        }
        $clientSecret = (string)$credential['client_secret'];

        self::event((string)$license['license_id'], 'activate', 'success', [
            'site_id' => $siteId,
            'new'     => $isNew ? 1 : 0,
            'credential_reused' => !empty($credential['reused']) ? 1 : 0,
        ]);

        $license['status'] = $license['status'] ?? self::STATUS_ACTIVE;

        return [
            'ok'   => true,
            'code' => '0',
            'msg'  => $msg,
            'data' => array_merge(self::statusPayload($license, $siteId), [
                'license_id'     => (string)$license['license_id'],
                'license_secret' => $clientSecret,
                'site_id'        => $siteId,
                'host'           => $host,
            ]),
        ];
    }

    /**
     * 校验 license_secret（请求签名用）
     */
    public static function verifySecret(array $license, string $secret): bool
    {
        if ($secret === '' || empty($license['secret_hash'])) {
            return false;
        }
        $stored = (string)$license['secret_hash'];
        return hash_equals($stored, hash('sha256', $secret))
            || (empty($license['secret_enc']) && hash_equals($stored, $secret));
    }

    /* ==================== 旧码兑换（用户需求 ①） ==================== */

    /**
     * 旧授权码换新
     *
     * 从冻结快照 SF_auth_legacy 读取，继承原有权益，签发新 license。
     * 旧码本身不作废，但只能兑换一次（幂等：重复兑换返回同一 license）。
     */
    public static function redeem(array $in): array
    {
        $legacyCode = AuthcodeService::normalize((string)($in['legacy_authcode'] ?? ''));
        $productId  = (string)($in['product_id'] ?? '');
        $host       = self::normalizeHost((string)($in['host'] ?? ''));
        $installUuid= trim((string)($in['install_uuid'] ?? ''));

        if (!AuthcodeService::looksValid($legacyCode)) {
            return self::err('4001', '旧授权码格式错误');
        }
        if ($host === '' || $installUuid === '') {
            return self::err('4001', '站点信息不完整');
        }

        $legacy = Db::name('auth_legacy')->where('authcode', $legacyCode)->find();
        if (empty($legacy)) {
            return self::err('4001', '未找到该旧授权码对应的记录');
        }
        if ((int)$legacy['status'] !== 1) {
            return self::err('4003', '该旧授权处于非正常状态，请联系站长');
        }

        // 已兑换 → 幂等返回同一 license
        if (!empty($legacy['redeemed_at']) && !empty($legacy['redeemed_license_id'])) {
            $license = self::findByLicenseId((string)$legacy['redeemed_license_id']);
            if ($license === null) {
                return self::err('5000', '兑换记录异常，请联系站长');
            }
            $siteId = self::siteId($host, $installUuid);
            $site = self::findSite((string)$license['license_id'], $siteId);
            if ($site === null) {
                // 换机重装：走正常激活流程补绑站点
                return self::activate([
                    'authcode'      => self::plainAuthcodeUnavailable(),
                    'product_id'    => $productId,
                    'host'          => $host,
                    'install_uuid'  => $installUuid,
                    'verified'      => !empty($in['verified']),
                    'verify_method' => $in['verify_method'] ?? 'http',
                    '_license'      => $license,
                ]);
            }
            return self::issueSecret($license, $siteId, $host, '该旧授权码此前已兑换，已返回原授权', false);
        }

        // 归属证明（与激活同一要求）
        if (empty($in['verified'])) {
            return self::err('4005', '域名归属证明未通过，请改用离线兑换');
        }

        $newCode = sf_generate_authcode();
        $licenseId = bin2hex(random_bytes(16));
        $permanent = (int)($legacy['permanent_switch'] ?? 0) === 1;
        $expiresAt = $permanent ? null : ($legacy['endtime'] ?? null);

        Db::startTrans();
        try {
            Db::name('license')->insert([
                'license_id'      => $licenseId,
                'product_id'      => $productId,
                'appid'           => (int)($legacy['appid'] ?? 0),
                'authcode_hash'   => AuthcodeService::hash($newCode),
                'authcode_last4'  => AuthcodeService::last4($newCode),
                'pepper_version'  => AuthcodeService::PEPPER_VERSION,
                'secret_hash'     => '',
                'status'          => self::STATUS_ACTIVE,
                'permanent'       => $permanent ? 1 : 0,
                'expires_at'      => $expiresAt,
                'channel'         => ((int)($legacy['beta'] ?? 0) === 1) ? self::CHANNEL_BETA : self::CHANNEL_STABLE,
                'max_sites'       => self::MAX_SITES_PER_LICENSE,
                'allow_cross_root'=> 0,
                'rebind_limit'    => 3,
                'rebind_count'    => 0,
                'migrated_from'   => (int)$legacy['id'],
                'user_id'         => (int)($legacy['userid'] ?? 0),
                'created_at'      => datetime(),
                'updated_at'      => datetime(),
            ]);

            Db::name('auth_legacy')
                ->where('id', (int)$legacy['id'])
                ->whereNull('redeemed_at')
                ->update([
                    'redeemed_at'         => datetime(),
                    'redeemed_license_id' => $licenseId,
                    'redeem_note'         => 'auto redeem from ' . self::maskHost($host),
                ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('[SF-LIC] redeem failed: ' . $e->getMessage());
            return self::err('5000', '兑换失败，请稍后重试');
        }

        $license = self::findByLicenseId($licenseId);
        if ($license === null) {
            return self::err('5000', '兑换失败，请稍后重试');
        }

        $result = self::activate([
            'authcode'      => $newCode,
            'product_id'    => $productId,
            'host'          => $host,
            'install_uuid'  => $installUuid,
            'verified'      => true,
            'verify_method' => $in['verify_method'] ?? 'http',
        ]);

        if ($result['ok']) {
            // 新授权码只在兑换成功这一次返回，客户端需自行留存
            $result['data']['new_authcode'] = $newCode;
            $result['msg'] = '兑换成功，已升级为新版授权';
            self::event($licenseId, 'redeem', 'success', ['legacy_id' => (int)$legacy['id']]);
        }
        return $result;
    }

    private static function plainAuthcodeUnavailable(): string
    {
        // 兑换后台不再持有明文新码，补绑走 _license 直通分支
        return str_repeat('0', 32);
    }

    /* ==================== 换绑 ==================== */

    public static function rebind(array $license, array $in): array
    {
        $licenseId = (string)$license['license_id'];
        $newHost = self::normalizeHost((string)($in['new_host'] ?? ''));
        $newUuid = trim((string)($in['new_install_uuid'] ?? ''));
        $requestId = (string)($in['request_id'] ?? '');
        // 单站点授权不接受客户端指定 secondary；换绑始终替换唯一主站点。
        $role = self::ROLE_PRIMARY;

        if ($newHost === '' || $newUuid === '') {
            return self::err('4001', '新站点信息不完整');
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) {
            return self::err('4001', 'request_id 格式错误');
        }

        // 幂等：24 小时内相同 request_id 返回首次结果
        $dup = Db::name('license_event')
            ->where('license_id', $licenseId)
            ->where('event', 'rebind')
            ->where('request_id', $requestId)
            ->find();
        if (!empty($dup)) {
            return ['ok' => true, 'code' => '0', 'msg' => '换绑请求已处理', 'data' => [
                'site_id' => self::siteId($newHost, $newUuid),
                'rebind_remain' => max(0, (int)$license['rebind_limit'] - (int)$license['rebind_count']),
            ]];
        }

        if ((int)$license['rebind_count'] >= (int)$license['rebind_limit']) {
            return self::err('4101', '换绑次数已用尽，请联系站长');
        }
        if (!empty($license['rebind_cooldown_until'])
            && strtotime((string)$license['rebind_cooldown_until']) > time()) {
            return self::err('4102', '换绑冷却中，请稍后再试');
        }
        if (empty($in['verified'])) {
            return self::err('4104', '新域名归属证明未通过');
        }

        $newSiteId = self::siteId($newHost, $newUuid);

        Db::startTrans();
        try {
            // 旧站点全部失效。不能只按 role 更新，否则历史 secondary 数据会
            // 在换绑后继续保持有效，形成第二个可用域名。
            Db::name('license_site')
                ->where('license_id', $licenseId)
                ->update(['status' => 0, 'last_seen_at' => datetime()]);

            Db::name('license_site')->insert([
                'license_id'    => $licenseId,
                'site_id'       => $newSiteId,
                'host'          => $newHost,
                'role'          => $role,
                'install_uuid'  => $newUuid,
                'verify_method' => (string)($in['verify_method'] ?? 'http'),
                'verified_at'   => datetime(),
                'status'        => 1,
                'first_seen_at' => datetime(),
                'last_seen_at'  => datetime(),
            ]);

            $update = [
                'rebind_count' => (int)$license['rebind_count'] + 1,
                'rebind_cooldown_until' => date('Y-m-d H:i:s', time() + 86400),
                'updated_at' => datetime(),
            ];
            if ($role === self::ROLE_PRIMARY) {
                $update['bound_host'] = $newHost;
                $update['bound_at'] = datetime();
            }
            Db::name('license')->where('license_id', $licenseId)->update($update);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('[SF-LIC] rebind failed: ' . $e->getMessage());
            return self::err('5000', '换绑失败，请稍后重试');
        }

        self::event($licenseId, 'rebind', 'success', [
            'role' => $role,
            'new_site' => $newSiteId,
        ], $requestId);

        return ['ok' => true, 'code' => '0', 'msg' => '换绑成功', 'data' => [
            'site_id' => $newSiteId,
            'host' => $newHost,
            'rebind_remain' => max(0, (int)$license['rebind_limit'] - (int)$license['rebind_count'] - 1),
            'cooldown_until' => time() + 86400,
        ]];
    }

    /* ==================== 测试通道（用户需求 ⑥） ==================== */

    public static function setChannel(array $license, string $siteId, string $channel, bool $acceptRisk): array
    {
        $licenseId = (string)$license['license_id'];

        if (!preg_match('/^[a-f0-9]{64}$/D', $siteId)) {
            return self::err('4001', '站点标识格式错误');
        }
        if (!in_array($channel, [self::CHANNEL_STABLE, self::CHANNEL_BETA], true)) {
            return self::err('4001', '通道参数错误');
        }
        if ($channel === self::CHANNEL_BETA) {
            if (!$acceptRisk) {
                return self::err('4001', '加入测试通道需确认风险提示');
            }
            if ((int)($license['beta_blocked'] ?? 0) === 1) {
                return self::err('4003', '该授权已被禁止加入测试通道');
            }
        }

        Db::startTrans();
        try {
            $locked = Db::name('license')->where('license_id', $licenseId)->lock(true)->find();
            $site = Db::name('license_site')
                ->where('license_id', $licenseId)
                ->where('site_id', $siteId)
                ->where('status', 1)
                ->lock(true)
                ->find();
            if (!is_array($locked) || !is_array($site)) {
                Db::rollback();
                return self::err('4006', '该站点未绑定此授权，请重新激活');
            }
            if ($channel === self::CHANNEL_BETA && (int)($locked['beta_blocked'] ?? 0) === 1) {
                Db::rollback();
                return self::err('4003', '该授权已被禁止加入测试通道');
            }

            // 旧后台的“内测资格”仍以 SF_auth.beta 为准。已从旧授权桥接的记录
            // 必须在同一事务中同步，否则下次状态查询会把新版 channel 覆盖回去。
            $sourceAuthId = (int)($locked['source_auth_id'] ?? 0);
            if ($sourceAuthId > 0) {
                $source = Db::name('auth')->where('id', $sourceAuthId)->lock(true)->find();
                if (!is_array($source) || (int)$source['appid'] !== (int)$locked['appid']) {
                    Db::rollback();
                    return self::err('4003', '授权来源记录异常，无法修改测试资格');
                }
                Db::name('auth')->where('id', $sourceAuthId)->update([
                    'beta' => $channel === self::CHANNEL_BETA ? 1 : 0,
                ]);
            }

            Db::name('license')->where('license_id', $licenseId)->update([
                'channel' => $channel,
                'channel_changed_at' => datetime(),
                'updated_at' => datetime(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('[SF-LIC] channel switch failed', [
                'license' => substr($licenseId, 0, 8),
                'exception_type' => get_class($e),
            ]);
            return self::err('5000', '测试资格修改失败，请稍后重试');
        }

        self::event($licenseId, 'channel', 'success', [
            'channel' => $channel,
            'site_id' => $siteId,
        ]);

        return ['ok' => true, 'code' => '0', 'msg' => $channel === self::CHANNEL_BETA ? '已加入测试通道' : '已切回稳定通道', 'data' => [
            'channel' => $channel,
            'site_id' => $siteId,
            'beta_eligible' => $channel === self::CHANNEL_BETA,
            'requires_file_revert' => $channel === self::CHANNEL_STABLE,
            'note' => $channel === self::CHANNEL_STABLE
                ? '测试资格已关闭，客户端将恢复最新正式版文件'
                : '测试资格已开启，可以接收正式版与测试版更新',
        ]];
    }

    /* ==================== 试用（用户需求 ③） ==================== */

    /**
     * 记录/查询站点试用状态。试用起始时间以服务端首次见到为准，
     * 客户端本地时间被修改不影响判定。
     */
    public static function trial(string $siteId, string $host, string $productId): array
    {
        $row = Db::name('trial')->where('site_id', $siteId)->find();
        $now = time();

        if (empty($row)) {
            // 同一注册根域重复试用直接判为已过期（不按 IP 判重，避免误伤同机房不同客户）
            $root = self::registrableRoot($host);
            $usedByRoot = Db::name('trial')
                ->where('root_domain', $root)
                ->where('product_id', $productId)
                ->count();

            $startAt = $now;
            $expired = false;
            if ($usedByRoot > 0) {
                $expired = true;
                $startAt = $now - self::TRIAL_SECONDS - 1;
            }

            Db::name('trial')->insert([
                'site_id'     => $siteId,
                'product_id'  => $productId,
                'root_domain' => $root,
                'host_hash'   => hash('sha256', self::normalizeHost($host)),
                'started_at'  => date('Y-m-d H:i:s', $startAt),
                'expires_at'  => date('Y-m-d H:i:s', $startAt + self::TRIAL_SECONDS),
                'created_at'  => datetime(),
            ]);

            return [
                'state'      => $expired ? 'trial_expired' : 'trial',
                'expires_at' => $startAt + self::TRIAL_SECONDS,
                'remain'     => $expired ? 0 : self::TRIAL_SECONDS,
            ];
        }

        $expiresAt = strtotime((string)$row['expires_at']);
        return [
            'state'      => $expiresAt > $now ? 'trial' : 'trial_expired',
            'expires_at' => $expiresAt,
            'remain'     => max(0, $expiresAt - $now),
        ];
    }

    /* ==================== 审计 ==================== */

    /**
     * 写审计事件
     *
     * 约束（需求 ⑩）：不落完整授权码、不落明文 IP、detail 走字段白名单。
     */
    public static function event(string $licenseId, string $event, string $result, array $detail = [], string $requestId = ''): void
    {
        try {
            Db::name('license_event')->insert([
                'license_id' => $licenseId,
                'event'      => $event,
                'result'     => $result,
                'ip_hash'    => self::hashIp(self::clientIp()),
                'ua_hash'    => self::hashUa(),
                'site_id'    => (string)($detail['site_id'] ?? ''),
                'request_id' => $requestId,
                'detail'     => json_encode(ApiErrorService::scrub($detail), JSON_UNESCAPED_UNICODE),
                'created_at' => datetime(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SF-LIC] audit write failed: ' . $e->getMessage());
        }
    }

    private static function clientIp(): string
    {
        try {
            return (string)request()->ip();
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function hashIp(string $ip): string
    {
        return $ip === '' ? '' : hash_hmac('sha256', $ip, CryptoService::pepper());
    }

    private static function hashUa(): string
    {
        try {
            $ua = (string)request()->header('user-agent');
        } catch (\Throwable $e) {
            $ua = '';
        }
        return $ua === '' ? '' : substr(hash('sha256', $ua), 0, 32);
    }

    public static function maskHost(string $host): string
    {
        return $host === '' ? '' : substr(hash('sha256', self::normalizeHost($host)), 0, 12);
    }

    private static function err(string $code, string $msg): array
    {
        return ['ok' => false, 'code' => $code, 'msg' => $msg, 'data' => []];
    }
}
