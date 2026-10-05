<?php

namespace app\common\service;

use think\facade\Db;

class RebateRiskService
{
    public static function holdDays(): int
    {
        return max(1, min(30, intval(conf('rebate_hold_days') ?: 7)));
    }

    public static function relatedAccountReason(int $payerUserId, int $referrerUserId): string
    {
        if ($payerUserId <= 0 || $referrerUserId <= 0) {
            return '账号信息无效';
        }
        if ($payerUserId === $referrerUserId) {
            return '不能使用自己的折扣码';
        }

        $users = Db::name('user')
            ->whereIn('id', [$payerUserId, $referrerUserId])
            ->field('id,qq,email,phone,status')
            ->select()
            ->toArray();
        if (count($users) !== 2) {
            return '关联账号不存在';
        }

        $byId = [];
        foreach ($users as $user) {
            $byId[intval($user['id'])] = $user;
        }
        $payer = $byId[$payerUserId] ?? null;
        $referrer = $byId[$referrerUserId] ?? null;
        if (!$payer || !$referrer || intval($payer['status']) !== 1 || intval($referrer['status']) !== 1) {
            return '关联账号状态异常';
        }

        foreach (['qq' => 'QQ', 'email' => '邮箱', 'phone' => '手机号'] as $field => $label) {
            $left = self::normalizeIdentity($field, $payer[$field] ?? '');
            $right = self::normalizeIdentity($field, $referrer[$field] ?? '');
            if ($left !== '' && $right !== '' && hash_equals($left, $right)) {
                return '付款账号与返利账号使用相同' . $label;
            }
        }

        try {
            $sharedIdentity = Db::name('user_social_identity')
                ->whereIn('user_id', [$payerUserId, $referrerUserId])
                ->group('identity_id')
                ->havingRaw('COUNT(DISTINCT user_id) > 1')
                ->count();
            if ($sharedIdentity > 0) {
                return '付款账号与返利账号绑定同一第三方身份';
            }
        } catch (\Throwable $e) {
            // Older installations may not have OAuth identity tables yet.
        }

        if (self::sharesWithdrawalPhone($payerUserId, $referrerUserId)) {
            return '付款账号与返利账号使用相同提现手机号';
        }

        return '';
    }

    public static function limitReason(int $payerUserId, int $referrerUserId, $rebateAmount): string
    {
        $rebateAmount = qh_money_format($rebateAmount);
        $activeStatuses = ['pending', 'settled'];
        $dayStart = date('Y-m-d 00:00:00');
        $dayEnd = date('Y-m-d 23:59:59');
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        $pairDailyCount = max(1, min(20, intval(conf('rebate_pair_daily_count') ?: 3)));
        $usedPairCount = Db::name('rebate_record')
            ->where('payer_user_id', $payerUserId)
            ->where('referrer_user_id', $referrerUserId)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->count('id');
        if ($usedPairCount >= $pairDailyCount) {
            return '同一邀请关系当日返利次数已达上限';
        }

        $dailyLimit = qh_money_format(conf('rebate_daily_limit') ?: '50.00');
        $dailyUsed = qh_money_format(Db::name('rebate_record')
            ->where('referrer_user_id', $referrerUserId)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->sum('rebate_amount') ?: 0);
        if (qh_money_to_cents(qh_money_add($dailyUsed, $rebateAmount)) > qh_money_to_cents($dailyLimit)) {
            return '当日返利金额已达上限';
        }

        $monthlyLimit = qh_money_format(conf('rebate_monthly_limit') ?: '500.00');
        $monthlyUsed = qh_money_format(Db::name('rebate_record')
            ->where('referrer_user_id', $referrerUserId)
            ->whereIn('status', $activeStatuses)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('rebate_amount') ?: 0);
        if (qh_money_to_cents(qh_money_add($monthlyUsed, $rebateAmount)) > qh_money_to_cents($monthlyLimit)) {
            return '当月返利金额已达上限';
        }

        return '';
    }

    private static function normalizeIdentity(string $field, $value): string
    {
        $value = trim((string)$value);
        if ($field === 'email') {
            return strtolower($value);
        }
        return preg_replace('/\s+/', '', $value) ?: '';
    }

    private static function sharesWithdrawalPhone(int $payerUserId, int $referrerUserId): bool
    {
        try {
            $phones = Db::name('withdraw')
                ->whereIn('user_id', [$payerUserId, $referrerUserId])
                ->where('phone', '<>', '')
                ->field('user_id,phone')
                ->select()
                ->toArray();
            $owners = [];
            foreach ($phones as $row) {
                $phone = preg_replace('/\D+/', '', (string)($row['phone'] ?? ''));
                if ($phone === '') {
                    continue;
                }
                $owners[$phone][intval($row['user_id'])] = true;
                if (count($owners[$phone]) > 1) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }
}
