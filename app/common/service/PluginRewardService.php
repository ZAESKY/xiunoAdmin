<?php

namespace app\common\service;

use app\common\model\BalanceLogModel;
use app\common\model\NotificationModel;
use app\common\model\PointLogModel;
use think\facade\Db;

/**
 * Issues the one-time reward for a user plugin's first approval.
 *
 * The caller must already be inside the same database transaction that
 * changes the plugin status. The plugin row must also be locked first. This
 * keeps approval and reward settlement atomic.
 */
class PluginRewardService
{
    public const SCENE_FIRST_APPROVAL = 'first_approval';

    public static function config(): array
    {
        return self::normalizeConfig([
            'enabled' => conf('plugin_reward_enabled'),
            'points' => conf('plugin_reward_points'),
            'balance' => conf('plugin_reward_balance'),
            'original_only' => conf('plugin_reward_original_only'),
            'monthly_limit' => conf('plugin_reward_monthly_limit'),
            'min_account_days' => conf('plugin_reward_min_account_days'),
            'duplicate_hash_block' => conf('plugin_reward_duplicate_hash'),
        ]);
    }

    public static function normalizeConfig(array $config): array
    {
        $enabled = self::boolValue($config['enabled'] ?? null, true);
        $originalOnly = self::boolValue($config['original_only'] ?? null, true);
        $duplicateHashBlock = self::boolValue($config['duplicate_hash_block'] ?? null, true);
        $points = max(0, min(1000000, intval($config['points'] ?? 100)));
        $monthlyLimit = max(0, min(1000, intval($config['monthly_limit'] ?? 3)));
        $minAccountDays = max(0, min(3650, intval($config['min_account_days'] ?? 7)));

        try {
            $balance = qh_money_format($config['balance'] ?? '0.00');
        } catch (\Throwable $e) {
            $balance = '0.00';
        }
        if (qh_money_to_cents($balance) < 0) {
            $balance = '0.00';
        } elseif (qh_money_to_cents($balance) > 100000000) {
            $balance = '1000000.00';
        }

        return [
            'enabled' => $enabled,
            'points' => $points,
            'balance' => $balance,
            'original_only' => $originalOnly,
            'monthly_limit' => $monthlyLimit,
            'min_account_days' => $minAccountDays,
            'duplicate_hash_block' => $duplicateHashBlock,
        ];
    }

    /**
     * Create an immutable first-approval decision and issue the reward.
     * A skipped decision is also stored so that later configuration changes or
     * repeated reviews cannot turn the same plugin into another reward claim.
     */
    public static function issueFirstApproval(array $plugin, int $adminId, bool $allowReward = true): array
    {
        $pluginId = intval($plugin['id'] ?? 0);
        $userId = intval($plugin['user_id'] ?? 0);
        if ($pluginId <= 0) {
            throw new \RuntimeException('插件奖励缺少插件ID');
        }

        $existing = Db::name('plugin_reward')
            ->where('plugin_id', $pluginId)
            ->where('scene', self::SCENE_FIRST_APPROVAL)
            ->lock(true)
            ->find();
        if ($existing) {
            return self::resultFromRow($existing, true);
        }

        $config = self::config();
        $user = $userId > 0
            ? Db::name('user')->where('id', $userId)->lock(true)->find()
            : null;
        $reason = self::ineligibleReason($plugin, $user, $config, $allowReward);
        if ($reason === '' && $config['duplicate_hash_block']) {
            $reason = self::claimPackageHash($plugin);
        }
        $status = $reason === '' ? 'issued' : 'skipped';
        $points = $status === 'issued' ? intval($config['points']) : 0;
        $balance = $status === 'issued' ? qh_money_format($config['balance']) : '0.00';
        $now = datetime();
        $fileHash = strtolower(trim((string)($plugin['file_hash'] ?? '')));

        $rewardId = Db::name('plugin_reward')->insertGetId([
            'plugin_id' => $pluginId,
            'user_id' => $userId,
            'plugin_name' => qh_plain_text($plugin['name'] ?? '', 255),
            'scene' => self::SCENE_FIRST_APPROVAL,
            'status' => $status,
            'points' => $points,
            'balance' => $balance,
            'file_hash' => $fileHash,
            'reason' => $reason,
            'config_snapshot' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'approved_by' => max(0, $adminId),
            'approved_at' => $now,
            'issued_at' => $status === 'issued' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($status === 'issued') {
            self::creditUser($userId, $pluginId, (string)($plugin['name'] ?? ''), $points, $balance, $rewardId);
        }

        return [
            'id' => intval($rewardId),
            'status' => $status,
            'points' => $points,
            'balance' => $balance,
            'reason' => $reason,
            'duplicate' => false,
        ];
    }

    public static function rewardText(array $reward): string
    {
        if (($reward['status'] ?? '') !== 'issued') {
            return '';
        }
        $parts = [];
        if (intval($reward['points'] ?? 0) > 0) {
            $parts[] = intval($reward['points']) . ' 积分';
        }
        if (qh_money_to_cents($reward['balance'] ?? 0) > 0) {
            $parts[] = qh_money_format($reward['balance']) . ' 元平台余额';
        }
        return implode(' + ', $parts);
    }

    private static function ineligibleReason(array $plugin, ?array $user, array $config, bool $allowReward): string
    {
        if (!$config['enabled']) {
            return '插件发布奖励未开启';
        }
        if (!$allowReward) {
            return '管理员本次审核选择不发放奖励';
        }
        if (intval($plugin['user_id'] ?? 0) <= 0 || !$user) {
            return '该插件不是用户发布';
        }
        if (PhoneVerificationService::requiredFor('plugin_reward')) {
            $phoneStatus = PhoneVerificationService::status(intval($plugin['user_id']));
            if (empty($phoneStatus['verified'])) {
                return '账号未完成手机号验证';
            }
        }
        if ($config['original_only'] && intval($plugin['origin_type'] ?? 1) !== 1) {
            return '转载插件不参与发布奖励';
        }
        if (intval($config['points']) <= 0 && qh_money_to_cents($config['balance']) <= 0) {
            return '奖励积分和金额均为零';
        }

        $minDays = intval($config['min_account_days']);
        if ($minDays > 0) {
            $registeredAt = trim((string)($user['created_at'] ?? ''));
            if ($registeredAt === '') {
                $registeredAt = trim((string)($user['addtime'] ?? ''));
            }
            $registeredTimestamp = $registeredAt !== '' ? strtotime($registeredAt) : false;
            if ($registeredTimestamp === false || $registeredTimestamp > strtotime('-' . $minDays . ' days')) {
                return '账号注册未满 ' . $minDays . ' 天';
            }
        }

        $monthlyLimit = intval($config['monthly_limit']);
        if ($monthlyLimit > 0) {
            $monthStart = date('Y-m-01 00:00:00');
            $monthEnd = date('Y-m-01 00:00:00', strtotime('+1 month'));
            $issuedThisMonth = Db::name('plugin_reward')
                ->where('user_id', intval($plugin['user_id']))
                ->where('scene', self::SCENE_FIRST_APPROVAL)
                ->where('status', 'issued')
                ->where('issued_at', '>=', $monthStart)
                ->where('issued_at', '<', $monthEnd)
                ->count();
            if ($issuedThisMonth >= $monthlyLimit) {
                return '该用户本月插件奖励已达到 ' . $monthlyLimit . ' 次上限';
            }
        }

        if ($config['duplicate_hash_block']) {
            $fileHash = strtolower(trim((string)($plugin['file_hash'] ?? '')));
            if ($fileHash === '' || !preg_match('/^[a-f0-9]{32,64}$/', $fileHash)) {
                return '插件包缺少有效文件哈希';
            }
        }

        return '';
    }

    private static function creditUser(int $userId, int $pluginId, string $pluginName, int $points, string $balance, int $rewardId): void
    {
        if ($userId <= 0) {
            throw new \RuntimeException('插件奖励用户无效');
        }
        $sourceNo = 'plugin_reward_' . $pluginId;
        $description = '插件首次审核通过奖励：' . qh_plain_text($pluginName, 150);

        if ($points > 0) {
            $updated = Db::name('user')->where('id', $userId)->inc('integral', $points)->update();
            if ($updated !== 1) {
                throw new \RuntimeException('插件积分奖励入账失败');
            }
            PointLogModel::add(
                $userId,
                'plugin_reward',
                $points,
                $description,
                'plugin_publish_reward',
                $sourceNo . '_points',
                $rewardId
            );
        }

        if (qh_money_to_cents($balance) > 0) {
            $updated = Db::name('user')->where('id', $userId)->inc('balance', $balance)->update();
            if ($updated !== 1) {
                throw new \RuntimeException('插件余额奖励入账失败');
            }
            if (BalanceLogModel::add(
                $userId,
                'plugin_reward',
                $balance,
                $description . '（平台余额，不可提现）',
                'plugin_publish_reward',
                $sourceNo . '_balance'
            ) === false) {
                throw new \RuntimeException('插件余额奖励流水写入失败');
            }
        }

        $rewardText = self::rewardText(['status' => 'issued', 'points' => $points, 'balance' => $balance]);
        $balanceNotice = qh_money_to_cents($balance) > 0 ? '。金额奖励为平台余额，不可提现。' : '。';
        NotificationModel::add([
            'user_id' => $userId,
            'title' => '插件发布奖励到账',
            'content' => '您的插件「' . qh_plain_text($pluginName, 150) . '」首次审核通过，获得 ' . $rewardText . $balanceNotice,
            'type' => 'plugin_reward',
            'link' => '/UserPlugin/list.html',
            'variables' => ['plugin_name' => $pluginName, 'reward' => $rewardText],
            'is_read' => 0,
            'created_at' => datetime(),
        ]);
    }

    private static function resultFromRow(array $row, bool $duplicate): array
    {
        return [
            'id' => intval($row['id'] ?? 0),
            'status' => (string)($row['status'] ?? ''),
            'points' => intval($row['points'] ?? 0),
            'balance' => qh_money_format($row['balance'] ?? 0),
            'reason' => (string)($row['reason'] ?? ''),
            'duplicate' => $duplicate,
        ];
    }

    /**
     * Atomically claim a package hash. The unique primary key closes the race
     * where different users approve an identical package at the same moment.
     */
    private static function claimPackageHash(array $plugin): string
    {
        $fileHash = strtolower(trim((string)($plugin['file_hash'] ?? '')));
        try {
            Db::name('plugin_reward_hash_claim')->insert([
                'file_hash' => $fileHash,
                'plugin_id' => intval($plugin['id'] ?? 0),
                'user_id' => intval($plugin['user_id'] ?? 0),
                'claimed_at' => datetime(),
            ]);
            return '';
        } catch (\Throwable $e) {
            $existing = Db::name('plugin_reward_hash_claim')
                ->where('file_hash', $fileHash)
                ->lock(true)
                ->find();
            if ($existing && intval($existing['plugin_id'] ?? 0) !== intval($plugin['id'] ?? 0)) {
                return '相同插件包已获得过发布奖励';
            }
            if ($existing) {
                return '';
            }
            throw $e;
        }
    }

    private static function boolValue($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}
