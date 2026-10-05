<?php

namespace app\common\service;

use app\common\model\NotificationModel;
use think\facade\Db;
use think\facade\Log;

class RebateSettlementService
{
    public static function settleMaturedForUser(int $referrerUserId, int $limit = 100): array
    {
        if ($referrerUserId <= 0) {
            return ['settled' => 0, 'rejected' => 0, 'amount' => '0.00'];
        }

        $ids = Db::name('rebate_record')
            ->where('referrer_user_id', $referrerUserId)
            ->where('status', 'pending')
            ->where('settle_at', '<=', datetime())
            ->order('id', 'asc')
            ->limit(max(1, min(500, $limit)))
            ->column('id');

        return self::settleIds(array_map('intval', $ids));
    }

    public static function settleAllMatured(int $limit = 500): array
    {
        $ids = Db::name('rebate_record')
            ->where('status', 'pending')
            ->where('settle_at', '<=', datetime())
            ->order('id', 'asc')
            ->limit(max(1, min(2000, $limit)))
            ->column('id');

        return self::settleIds(array_map('intval', $ids));
    }

    private static function settleIds(array $ids): array
    {
        $summary = ['settled' => 0, 'rejected' => 0, 'amount' => '0.00'];
        foreach ($ids as $id) {
            try {
                $result = self::settleOne($id);
                if ($result['status'] === 'settled') {
                    $summary['settled']++;
                    $summary['amount'] = qh_money_add($summary['amount'], $result['amount']);
                } elseif ($result['status'] === 'rejected') {
                    $summary['rejected']++;
                }
            } catch (\Throwable $e) {
                Log::error('Rebate settlement failed: ' . $e->getMessage(), [
                    'rebate_id' => $id,
                    'exception' => $e,
                ]);
            }
        }
        return $summary;
    }

    private static function settleOne(int $id): array
    {
        return Db::transaction(function () use ($id) {
            $record = Db::name('rebate_record')->where('id', $id)->lock(true)->find();
            if (!$record || (string)$record['status'] !== 'pending') {
                return ['status' => 'skipped', 'amount' => '0.00'];
            }
            if (empty($record['settle_at']) || strtotime((string)$record['settle_at']) > time()) {
                return ['status' => 'skipped', 'amount' => '0.00'];
            }

            $pay = Db::name('pay')->where('trade_no', $record['pay_trade_no'])->lock(true)->find();
            if (!$pay || intval($pay['status']) !== 1 || (string)$pay['buy_type'] !== 'recharge') {
                return self::reject($record, '原充值订单状态异常');
            }

            $riskReason = RebateRiskService::relatedAccountReason(
                intval($record['payer_user_id']),
                intval($record['referrer_user_id'])
            );
            if ($riskReason !== '') {
                return self::reject($record, $riskReason);
            }

            $amount = qh_money_format($record['rebate_amount']);
            $claimed = Db::name('rebate_record')
                ->where('id', $id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'settled',
                    'settled_at' => datetime(),
                    'updated_at' => datetime(),
                ]);
            if ($claimed !== 1) {
                return ['status' => 'skipped', 'amount' => '0.00'];
            }

            $credited = WithdrawableBalanceService::credit(
                intval($record['referrer_user_id']),
                $amount,
                'rebate',
                '返利 +' . $amount . ' 元（订单 ' . $record['pay_trade_no'] . '）',
                'rebate_settlement',
                (string)$record['pay_trade_no']
            );
            if (!$credited) {
                throw new \RuntimeException('返利可提现余额入账失败');
            }

            NotificationModel::add([
                'user_id' => intval($record['referrer_user_id']),
                'title' => '返利已解冻',
                'content' => sprintf(
                    '折扣码 %s 的返利 +%s 元已结算到可提现余额（订单 %s）',
                    $record['discount_code'],
                    $amount,
                    $record['pay_trade_no']
                ),
                'type' => 'rebate',
                'variables' => [
                    'discount_code' => $record['discount_code'],
                    'amount' => $amount,
                    'order_no' => $record['pay_trade_no'],
                    'settled_at' => datetime(),
                    'phase' => '已结算',
                ],
                'is_read' => 0,
                'created_at' => datetime(),
            ]);

            return ['status' => 'settled', 'amount' => $amount];
        });
    }

    private static function reject(array $record, string $reason): array
    {
        Db::name('rebate_record')
            ->where('id', intval($record['id']))
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'risk_reason' => qh_plain_text($reason, 255),
                'updated_at' => datetime(),
            ]);

        return ['status' => 'rejected', 'amount' => '0.00'];
    }
}
