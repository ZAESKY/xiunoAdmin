<?php

namespace app\pay\service;

use think\facade\Db;

/**
 * 插件订单支付回调处理
 * @author SF授权系统
 * @since 2026-05-03
 */
class PluginPayCallbackService
{
    /**
     * 处理插件订单支付成功回调
     */
    public static function handlePaySuccess($orderNo, $payType, $payTradeNo)
    {
        try {
            // 查询订单
            $order = Db::name('plugin_order')
                ->where('order_no', $orderNo)
                ->find();

            if (!$order) {
                return ['success' => false, 'msg' => '订单不存在'];
            }

            if ($order['status'] == 1) {
                return ['success' => true, 'msg' => '订单已支付'];
            }

            // 开启事务
            Db::startTrans();
            try {
                // 计算平台抽成
                $commissionRate = floatval(conf('plugin_commission_rate') ?? 10);
                $commissionRate = max(0, min(100, $commissionRate));
                $price = floatval($order['price']);
                $commissionAmount = round($price * $commissionRate / 100, 2);
                $developerIncome = round($price - $commissionAmount, 2);

                // 更新订单状态
                Db::name('plugin_order')
                    ->where('id', $order['id'])
                    ->update([
                        'status' => 1,
                        'pay_type' => $payType,
                        'pay_trade_no' => $payTradeNo,
                        'commission_rate' => $commissionRate,
                        'commission_amount' => $commissionAmount,
                        'developer_income' => $developerIncome,
                        'paid_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                // 创建购买记录
                $purchaseExists = Db::name('plugin_purchase')
                    ->where('plugin_id', $order['plugin_id'])
                    ->where('user_id', $order['user_id'])
                    ->where('app_id', $order['app_id'])
                    ->find();

                if (!$purchaseExists) {
                    Db::name('plugin_purchase')->insert([
                        'plugin_id' => $order['plugin_id'],
                        'user_id' => $order['user_id'],
                        'app_id' => $order['app_id'],
                        'order_id' => $order['id'],
                        'price' => $order['price'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // 给插件开发者打款
                $plugin = Db::name('plugin')->where('id', $order['plugin_id'])->find();
                if ($developerIncome > 0 && $plugin && !empty($plugin['user_id'])) {
                    $developerId = intval($plugin['user_id']);
                    if ($developerId != intval($order['user_id'])) {
                        Db::name('user')->where('id', $developerId)->inc('balance', $developerIncome)->update();
                        \app\common\model\BalanceLogModel::add(
                            $developerId,
                            'plugin_income',
                            $developerIncome,
                            '插件销售收入：' . ($plugin['name'] ?? '') . '（佣金' . $commissionRate . '%）',
                            intval($order['id'])
                        );
                    }
                }

                // 记录日志
                $content = [
                    'Title' => '插件订单支付成功',
                    'Result' => 'success',
                    'Detail' => '订单号:' . $orderNo . ',用户ID:' . $order['user_id'] . ',插件ID:' . $order['plugin_id'] . ',开发者收入:' . $developerIncome,
                ];
                event('ActionLog', $content);

                Db::commit();
                return ['success' => true, 'msg' => '支付成功'];
            } catch (\Exception $e) {
                Db::rollback();
                return ['success' => false, 'msg' => '处理失败:' . $e->getMessage()];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => '系统错误:' . $e->getMessage()];
        }
    }
}
