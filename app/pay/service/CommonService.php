<?php

namespace app\pay\service;

use app\admin\model\PayModel;
use app\common\service\BaseService;
use app\admin\model\OrderModel;
use app\common\model\NotificationModel;
use app\common\model\BalanceLogModel;
use app\common\model\PointLogModel;
use think\Exception;
use think\facade\Db;
use Throwable;

class CommonService extends BaseService
{
    public function __construct(){
        $this->payModel = new PayModel();
        $this->orderModel = new OrderModel();
    }

    public function processOrder($srow){
        try{
            //if(!is_array($srow)) return false;
            $srow = $this->payModel->getInfo($srow['trade_no']);
            if(!$srow) return false;
            $srow = $srow->toArray();
            //$input = unserialize($srow['input']);
            //throw new Exception(gettype($srow));
            switch ($srow['buy_type']){
                case 'recharge':
                    try{
                        $userId = !empty($srow['userid']) ? intval($srow['userid']) : null;
                        $money = !empty($srow['money']) ? round($srow['money'], 2) : null;
                        if(empty($userId)){
                            return false;
                        }
                        if(empty($money)){
                            return false;
                        }
                        try{
                            // 若有折扣码，按原始金额到账而非折扣后金额
                            $rechargeAmount = round(floatval($srow['money']), 2);
                            if (!empty($srow['input'])) {
                                $inputData = json_decode($srow['input'], true);
                                if (!empty($inputData['original_money'])) {
                                    $rechargeAmount = round(floatval($inputData['original_money']), 2);
                                }
                            }
                            Db::name('user')
                                ->where('id', $srow['userid'])
                                ->inc('balance', $rechargeAmount)
                                ->update();
                            BalanceLogModel::add($srow['userid'], 'recharge', $rechargeAmount, '余额充值 +'.$rechargeAmount.' 元');
                            $this->grantRechargePoints((int)$srow['userid'], (string)$srow['trade_no'], (int)floor(floatval($srow['money'])));
                            $srow['status'] = 1;
                        }catch (\Exception $e){
                            $srow['status'] = 3;
                            $srow['return'] = '执行充值操作失败！';
                        }
                        $this->orderModel->edit($srow);
                        // 返利处理
                        $this->processRebate($srow);
                    }catch (\Exception $e){
                        $srow['status'] = 3;
                        $srow['return'] = $e->getMessage();
                        $this->orderModel->edit($srow);
                        return false;
                    }
                    break;
                default:
                    return false;
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    private function grantRechargePoints(int $userId, string $tradeNo, int $points): void
    {
        Db::startTrans();
        try {
            $exists = Db::name('point_log')
                ->where('source_type', 'pay_recharge')
                ->where('source_no', $tradeNo)
                ->lock(true)
                ->find();
            if ($exists) {
                Db::commit();
                return;
            }

            if ($points > 0) {
                Db::name('user')
                    ->where('id', $userId)
                    ->inc('integral', $points)
                    ->update();
            }

            PointLogModel::add(
                $userId,
                'recharge',
                $points,
                $points > 0 ? '在线充值获得积分 +' . $points : '在线充值金额不足1元，获得0积分',
                'pay_recharge',
                $tradeNo
            );
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            throw $e;
        }
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

        // 5. 计算返利金额（基于实付金额）
        $paidAmount = round(floatval($payRow['money']), 2);
        $rebateAmount = round($paidAmount * $rebateRate / 100, 2);
        if ($rebateAmount <= 0) {
            return;
        }

        Db::startTrans();
        try {
            // 6. 防重：事务内检查，利用唯一索引防止并发双写
            $existing = Db::name('rebate_record')
                ->where('pay_trade_no', $payRow['trade_no'])
                ->lock(true)
                ->find();
            if ($existing) {
                Db::commit();
                return;
            }

            // 7. 写入返利记录
            Db::name('rebate_record')->insert([
                'order_id' => $orderRow['id'],
                'pay_trade_no' => $payRow['trade_no'],
                'payer_user_id' => $payRow['userid'],
                'referrer_user_id' => $codeRow['user_id'],
                'discount_code' => $payRow['discount_code'],
                'paid_amount' => $paidAmount,
                'rebate_rate' => $rebateRate,
                'rebate_amount' => $rebateAmount,
                'status' => 'settled',
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ]);

            // 8. 返利入账到码主余额
            Db::name('user')
                ->where('id', $codeRow['user_id'])
                ->inc('balance', $rebateAmount)
                ->update();
            BalanceLogModel::add($codeRow['user_id'], 'rebate', $rebateAmount, '返利 +'.$rebateAmount.' 元（订单 '.$payRow['trade_no'].'）');

            Db::commit();

            // 9. 发送通知给码主
            NotificationModel::add([
                'user_id' => $codeRow['user_id'],
                'title' => '返利到账通知',
                'content' => sprintf(
                    '您的折扣码 %s 被使用，获得返利 +%s 元（订单 %s，支付金额 %s 元，返利比例 %s%%）',
                    $payRow['discount_code'],
                    $rebateAmount,
                    $payRow['trade_no'],
                    $paidAmount,
                    $rebateRate
                ),
                'type' => 'rebate',
                'is_read' => 0,
                'created_at' => datetime(),
            ]);

            // 10. 审计日志
            event('ActionLog', [
                'Title' => '返利结算',
                '订单号' => $payRow['trade_no'],
                '付款用户' => $payRow['userid'],
                '返利用户' => $codeRow['user_id'],
                '折扣码' => $payRow['discount_code'],
                '支付金额' => $paidAmount,
                '返利比例' => $rebateRate . '%',
                '返利金额' => $rebateAmount,
                'Result' => 'success'
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
}
