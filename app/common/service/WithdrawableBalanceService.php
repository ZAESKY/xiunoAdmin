<?php

namespace app\common\service;

use app\common\model\BalanceLogModel;
use think\facade\Db;

/**
 * Maintain the withdrawable subset of a user's total balance.
 *
 * Recharge, gifts and card exchanges only increase balance. Legitimate cash
 * earnings increase both balance and withdrawable_balance. A database trigger
 * keeps withdrawable_balance <= balance when normal purchases reduce balance.
 */
class WithdrawableBalanceService
{
    public static function credit(
        int $userId,
        $amount,
        string $type,
        string $description,
        string $sourceType = '',
        $sourceNo = ''
    ): bool {
        $amount = sf_money_format($amount);
        if ($userId <= 0 || sf_money_to_cents($amount) <= 0) {
            return false;
        }

        $updated = Db::name('user')
            ->where('id', $userId)
            ->inc('balance', $amount)
            ->inc('withdrawable_balance', $amount)
            ->update();
        if ($updated !== 1) {
            return false;
        }

        return BalanceLogModel::add(
            $userId,
            $type,
            $amount,
            $description,
            $sourceType,
            (string)$sourceNo
        ) !== false;
    }

    public static function restoreWithdrawal(int $userId, $amount, string $type, string $description): bool
    {
        return self::credit($userId, $amount, $type, $description, 'withdraw_restore');
    }
}
