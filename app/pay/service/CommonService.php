<?php

namespace app\pay\service;

use app\admin\model\PayModel;
use app\common\service\BaseService;
use app\admin\model\OrderModel;
use app\common\model\NotificationModel;
use app\common\model\BalanceLogModel;
use app\common\service\RebateRiskService;
use think\Exception;
use think\facade\Db;

class CommonService extends BaseService
{
    public function __construct(){
        $this->payModel = new PayModel();
        $this->orderModel = new OrderModel();
    }

    public function processOrder($srow){
        if (!is_array($srow) || empty($srow['trade_no'])) {
            throw new Exception(t('pay.order_params_invalid'));
        }

        return (bool) Db::transaction(function () use ($srow) {
            $srow = Db::name('pay')
                ->where('trade_no', $srow['trade_no'])
                ->lock(true)
                ->find();
            if (!$srow) {
                throw new Exception(t('pay.payment_order_not_found'));
            }
            if ($srow['buy_type'] !== 'recharge') {
                return false;
            }

            $userId = (int)($srow['userid'] ?? 0);
            $rechargeAmount = qh_money_format($srow['money'] ?? 0);
            if (!empty($srow['input'])) {
                $inputData = json_decode($srow['input'], true);
                if (is_array($inputData) && isset($inputData['original_money'])) {
                    $rechargeAmount = qh_money_format($inputData['original_money']);
                }
            }
            if ($userId <= 0 || $rechargeAmount <= 0) {
                throw new Exception(t('pay.recharge_user_or_amount_invalid'));
            }

            // 支付订单行已被锁定；余额流水作为业务幂等凭据，避免回调重试重复加款。
            $credited = Db::name('balance_log')
                ->where('source_type', 'pay_recharge')
                ->where('source_no', $srow['trade_no'])
                ->lock(true)
                ->find();
            if (!$credited) {
                $updated = Db::name('user')
                    ->where('id', $userId)
                    ->inc('balance', $rechargeAmount)
                    ->update();
                if (!$updated) {
                    throw new Exception(t('pay.recharge_balance_update_failed'));
                }
                if (!BalanceLogModel::add($userId, 'recharge', $rechargeAmount, t('pay.recharge_log', ['amount' => $rechargeAmount]), 'pay_recharge', $srow['trade_no'])) {
                    throw new Exception(t('pay.recharge_log_failed'));
                }
            }

            $srow['status'] = 1;
            $srow['endtime'] = !empty($srow['endtime']) ? $srow['endtime'] : datetime();
            $existingOrder = Db::name('order')
                ->where('trade_no', $srow['trade_no'])
                ->lock(true)
                ->find();
            if ($existingOrder) {
                Db::name('order')->where('id', $existingOrder['id'])->update([
                    'status' => 1,
                    'endtime' => $srow['endtime'],
                    'api_trade_no' => $srow['api_trade_no'] ?? null,
                ]);
            } elseif (!$this->orderModel->edit($srow)) {
                throw new Exception(t('pay.recharge_order_write_failed'));
            }

            $this->processRebate($srow);
            return true;
        });
    }

    private function processRebate(array $payRow): void
    {
        if (empty($payRow['discount_code'])) {
            return;
        }

        // 1. 查折扣码
        $codeRow = Db::name('discount_code')
            ->where('code', $payRow['discount_code'])
            ->find();
        if (!$codeRow || $codeRow['status'] != 1) {
            return;
        }

        // 2. 查码主信息
        $ownerUser = Db::name('user')->where('id', $codeRow['user_id'])->find();
        if (!$ownerUser) {
            return;
        }
        $ownerPower = Db::name('power_price')->where('id', $ownerUser['power'])->find();
        if (!$ownerPower || $ownerPower['rebate_enabled'] != 1) {
            return;
        }

        // 3. 服务端读取返利比例（不信任前端）
        $rebateRate = floatval($ownerPower['rebate_rate']);
        if ($rebateRate <= 0 || $rebateRate > 100) {
            return;
        }

        // 4. 查找关联订单
        $orderRow = Db::name('order')
            ->where('trade_no', $payRow['trade_no'])
            ->find();
        if (!$orderRow) {
            return;
        }

        // 5. 计算返利金额（基于充值面额，避免在折扣后的实付金额上二次折算）
        $paidAmount = qh_money_format($payRow['money']);
        $rebateBaseAmount = $this->resolveRebateBaseAmount($payRow);
        $rebateAmount = qh_money_apply_rate($rebateBaseAmount, $rebateRate);
        if ($rebateAmount <= 0) {
            return;
        }

        Db::startTrans();
        try {
            // 串行化同一码主的返利创建，防止并发订单绕过日/月额度。
            $lockedOwner = Db::name('user')
                ->where('id', intval($codeRow['user_id']))
                ->lock(true)
                ->find();
            if (!$lockedOwner || intval($lockedOwner['status']) !== 1) {
                Db::rollback();
                return;
            }

            // 6. 防重：事务内检查，利用唯一索引防止并发双写
            $existing = Db::name('rebate_record')
                ->where('pay_trade_no', $payRow['trade_no'])
                ->lock(true)
                ->find();
            if ($existing) {
                Db::commit();
                return;
            }

            // 7. 返利先冻结，关联账号或超限订单只留风控审计记录。
            $riskReason = RebateRiskService::relatedAccountReason(
                intval($payRow['userid']),
                intval($codeRow['user_id'])
            );
            if ($riskReason === '') {
                $riskReason = RebateRiskService::limitReason(
                    intval($payRow['userid']),
                    intval($codeRow['user_id']),
                    $rebateAmount
                );
            }
            $status = $riskReason === '' ? 'pending' : 'rejected';
            $settleAt = date('Y-m-d H:i:s', strtotime('+' . RebateRiskService::holdDays() . ' days'));

            Db::name('rebate_record')->insert([
                'order_id' => $orderRow['id'],
                'pay_trade_no' => $payRow['trade_no'],
                'payer_user_id' => $payRow['userid'],
                'referrer_user_id' => $codeRow['user_id'],
                'discount_code' => $payRow['discount_code'],
                'paid_amount' => $paidAmount,
                'rebate_base_amount' => $rebateBaseAmount,
                'rebate_rate' => $rebateRate,
                'rebate_amount' => $rebateAmount,
                'status' => $status,
                'settle_at' => $settleAt,
                'settled_at' => null,
                'risk_reason' => qh_plain_text($riskReason, 255),
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ]);

            Db::commit();

            // 8. 仅通知正常进入冻结期的返利；风控原因不向前台披露。
            if ($status === 'pending') {
                NotificationModel::add([
                    'user_id' => $codeRow['user_id'],
                    'title' => t('rebate.pending_notice_title'),
                    'content' => t('rebate.pending_notice_content', [
                        'code' => $payRow['discount_code'],
                        'amount' => $rebateAmount,
                        'time' => $settleAt,
                        'order' => $payRow['trade_no'],
                        'face_amount' => $rebateBaseAmount,
                        'paid_amount' => $paidAmount,
                    ]),
                    'type' => 'rebate',
                    'variables' => [
                        'discount_code' => $payRow['discount_code'],
                        'amount' => $rebateAmount,
                        'order_no' => $payRow['trade_no'],
                        'settled_at' => $settleAt,
                        'phase' => t('rebate.pending_settlement'),
                    ],
                    'is_read' => 0,
                    'created_at' => datetime(),
                ]);
            }

            // 9. 审计日志
            event('ActionLog', [
                'Title' => '返利冻结审核',
                '订单号' => $payRow['trade_no'],
                '付款用户' => $payRow['userid'],
                '返利用户' => $codeRow['user_id'],
                '折扣码' => $payRow['discount_code'],
                '返利基数' => $rebateBaseAmount,
                '支付金额' => $paidAmount,
                '返利比例' => $rebateRate . '%',
                '返利金额' => $rebateAmount,
                '结算时间' => $settleAt,
                '风控结果' => $status,
                '风控原因' => $riskReason,
                'Result' => $status === 'pending' ? 'success' : 'rejected'
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            event('ActionLog', [
                'Title' => '返利处理失败',
                '订单号' => $payRow['trade_no'],
                '折扣码' => $payRow['discount_code'],
                '错误' => $e->getMessage(),
                'Result' => 'failed'
            ]);
        }
    }

    /**
     * 返利以充值面额为基数；历史无折扣订单没有 original_money 时退回实付金额。
     */
    private function resolveRebateBaseAmount(array $payRow): string
    {
        $paidAmount = qh_money_format($payRow['money'] ?? 0);
        $inputData = json_decode((string)($payRow['input'] ?? ''), true);
        if (!is_array($inputData) || !array_key_exists('original_money', $inputData)) {
            return $paidAmount;
        }

        try {
            $originalAmount = qh_money_format($inputData['original_money']);
        } catch (\InvalidArgumentException $e) {
            return $paidAmount;
        }

        return qh_money_to_cents($originalAmount) > 0 ? $originalAmount : $paidAmount;
    }
}
