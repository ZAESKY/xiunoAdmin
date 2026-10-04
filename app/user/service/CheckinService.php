<?php
declare(strict_types=1);

namespace app\user\service;

use app\common\service\CheckinConfigService;
use think\facade\Db;

class CheckinService
{
    private $userId;
    private $username;

    public function __construct($userId, string $username = '')
    {
        $this->userId = (int)$userId;
        $this->username = $username;
    }

    /**
     * 获取打卡状态（今日是否已打卡、连续天数等）
     */
    public function getStatus(): array
    {
        $default = [
            'checked_today' => false,
            'consecutive_days' => 0,
            'today_points' => 0,
            'checkin_time' => null,
        ];

        try {
            $today = date('Y-m-d');
            $todayRecord = Db::name('checkin_record')
                ->where('user_id', $this->userId)
                ->where('checkin_date', $today)
                ->find();

            if ($todayRecord) {
                return [
                    'checked_today' => true,
                    'consecutive_days' => (int)$todayRecord['consecutive_days'],
                    'today_points' => (int)$todayRecord['points_earned'],
                    'checkin_time' => $todayRecord['created_at'],
                ];
            }

            // 计算连续打卡天数：查找最近一次打卡
            $lastRecord = Db::name('checkin_record')
                ->where('user_id', $this->userId)
                ->order('checkin_date', 'desc')
                ->find();

            $consecutiveDays = 0;
            if ($lastRecord) {
                $lastDate = $lastRecord['checkin_date'];
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                if ($lastDate === $yesterday) {
                    $consecutiveDays = (int)$lastRecord['consecutive_days'];
                }
            }

            return [
                'checked_today' => false,
                'consecutive_days' => $consecutiveDays,
                'today_points' => 0,
                'checkin_time' => null,
            ];
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * 执行打卡
     */
    public function doCheckin(string $ip = ''): array
    {
        try {
            return $this->handleDoCheckin($ip);
        } catch (\Throwable $e) {
            return ['success' => false, 'msg' => t('checkin_ui.failed_retry')];
        }
    }

    private function handleDoCheckin(string $ip): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'msg' => t('checkin_ui.disabled')];
        }

        $today = date('Y-m-d');

        // 检查今天是否已打卡
        $exists = Db::name('checkin_record')
            ->where('user_id', $this->userId)
            ->where('checkin_date', $today)
            ->find();

        if ($exists) {
            return ['success' => false, 'msg' => t('checkin_ui.already_today')];
        }

        // 计算连续天数
        $consecutiveDays = 1;
        $lastRecord = Db::name('checkin_record')
            ->where('user_id', $this->userId)
            ->order('checkin_date', 'desc')
            ->find();

        if ($lastRecord) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($lastRecord['checkin_date'] === $yesterday) {
                $consecutiveDays = (int)$lastRecord['consecutive_days'] + 1;
            }
        }

        // 计算获得积分
        $basePoints = (int)conf('checkin_base_points') ?: 5;
        $bonusPoints = $this->calcBonusPoints($consecutiveDays);
        $totalPoints = $basePoints + $bonusPoints;

        // 开启事务
        Db::startTrans();
        try {
            // 写入打卡记录
            Db::name('checkin_record')->insert([
                'user_id' => $this->userId,
                'checkin_date' => $today,
                'consecutive_days' => $consecutiveDays,
                'points_earned' => $totalPoints,
                'ip' => $ip,
                'created_at' => datetime(),
            ]);

            // 增加用户积分
            Db::name('user')
                ->where('id', $this->userId)
                ->inc('integral', $totalPoints)
                ->update();

            // 记录积分日志
            $desc = t('checkin_ui.daily_log') . ($bonusPoints > 0
                ? t('checkin_ui.streak_bonus_log', ['days' => $consecutiveDays, 'points' => $bonusPoints])
                : '');
            $sourceNo = 'checkin_' . $this->userId . '_' . $today;
            \app\common\model\PointLogModel::add(
                $this->userId,
                'checkin',
                $totalPoints,
                $desc,
                'checkin',
                $sourceNo,
                null,
                'valid'
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            if (strpos($e->getMessage(), '1062') !== false) {
                return ['success' => false, 'msg' => t('checkin_ui.already_today')];
            }
            return ['success' => false, 'msg' => t('checkin_ui.failed_retry')];
        }

        return [
            'success' => true,
            'msg' => t('checkin_ui.success'),
            'data' => [
                'consecutive_days' => $consecutiveDays,
                'base_points' => $basePoints,
                'bonus_points' => $bonusPoints,
                'total_points' => $totalPoints,
            ],
        ];
    }

    /**
     * 计算连续打卡额外奖励
     */
    private function calcBonusPoints(int $consecutiveDays): int
    {
        try {
            [$days, $bonuses] = CheckinConfigService::normalizeMilestones(
                conf('checkin_consecutive_days'),
                conf('checkin_consecutive_bonus')
            );
        } catch (\InvalidArgumentException $e) {
            return 0;
        }

        $bonus = 0;
        foreach ($days as $i => $threshold) {
            $t = (int)$threshold;
            if ($t > 0 && $consecutiveDays >= $t) {
                $b = isset($bonuses[$i]) ? (int)$bonuses[$i] : 0;
                if ($b > $bonus) {
                    $bonus = $b;
                }
            }
        }

        return $bonus;
    }

    private function isEnabled(): bool
    {
        return conf('checkin_enabled') !== '0';
    }
}
