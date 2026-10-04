<?php

namespace app\pay\service;

use app\common\model\NotificationModel;
use app\common\service\PluginCommissionService;
use app\common\service\RebateRiskService;
use app\common\service\WithdrawableBalanceService;
use think\facade\Db;
use think\facade\Log;

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
            // 开启事务
            Db::startTrans();
            try {
                // Lock the order so concurrent provider callbacks are idempotent.
                $order = Db::name('plugin_order')
                    ->where('order_no', $orderNo)
                    ->lock(true)
                    ->find();

                if (!$order) {
                    Db::rollback();
                    return ['success' => false, 'msg' => t('plugin_order.not_found')];
                }

                if (intval($order['status']) === 1) {
                    Db::commit();
                    return ['success' => true, 'msg' => t('pay.already_paid_short')];
                }

                $price = sf_money_format($order['price']);
                $settlement = PluginCommissionService::settle($price, (string)$payType);
                $commissionRate = $settlement['rate'];
                $commissionAmount = $settlement['amount'];
                $developerIncome = $settlement['developer_income'];
                $plugin = Db::name('plugin')->where('id', $order['plugin_id'])->find();
                $developerId = $plugin ? intval($plugin['user_id'] ?? 0) : 0;
                $relatedPurchase = $developerId > 0
                    && RebateRiskService::relatedAccountReason(intval($order['user_id']), $developerId) !== '';
                if ($relatedPurchase) {
                    // 历史待支付订单也必须在回调时复核，关联账号交易不产生可提现收入。
                    $commissionAmount = $price;
                    $developerIncome = '0.00';
                }

                // 更新订单状态
                $claimed = Db::name('plugin_order')
                    ->where('id', $order['id'])
                    ->where('status', 0)
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
                if ($claimed !== 1) {
                    throw new \RuntimeException('payment order was not claimed');
                }

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

                if ($price > 0 && $payType !== 'points') {
                    \app\common\model\PointLogModel::grantConsumptionPoints(
                        intval($order['user_id']),
                        $price,
                        'plugin_order_consume',
                        intval($order['id']),
                        '消费购买插件获得积分 +' . intval(floor($price)) . '：' . $order['plugin_name'],
                        intval($order['id'])
                    );
                }

                // 给插件开发者打款
                if ($developerIncome > 0 && $plugin && !empty($plugin['user_id'])) {
                    if ($developerId != intval($order['user_id'])) {
                        if (!WithdrawableBalanceService::credit(
                            $developerId,
                            $developerIncome,
                            'plugin_income',
                            '插件销售收入：' . ($plugin['name'] ?? '') . '（' . PluginCommissionService::summary($settlement) . '）',
                            'plugin_order_income',
                            intval($order['id'])
                        )) {
                            throw new \RuntimeException('developer income credit failed');
                        }
                    }
                }

                // 记录日志
                $content = [
                    'Title' => '插件订单支付成功',
                    'Result' => $relatedPurchase ? 'risk_blocked' : 'success',
                    'Detail' => '订单号:' . $orderNo . ',用户ID:' . $order['user_id'] . ',插件ID:' . $order['plugin_id'] . ',开发者收入:' . $developerIncome,
                ];
                event('ActionLog', $content);

                Db::commit();
                try {
                    if ($plugin && $developerId > 0 && $developerId !== intval($order['user_id'])) {
                        $buyer = Db::name('user')->where('id', intval($order['user_id']))->field('username')->find();
                        $buyerName = $buyer ? (string)$buyer['username'] : t('plugin_action.unknown_user');
                        $commissionSummary = sf_money_to_cents($settlement['amount'] ?? 0) <= 0
                            ? t('plugin_commission.no_commission')
                            : t('plugin_commission.summary', [
                                'rate' => PluginCommissionService::displayRate($settlement['rate'] ?? 0),
                                'amount' => sf_money_format($settlement['amount'] ?? 0),
                            ]);
                        $incomeText = t('plugin_action.balance_income', [
                            'amount' => $developerIncome,
                            'summary' => $commissionSummary,
                        ]);
                        NotificationModel::add([
                            'user_id' => $developerId,
                            'title' => t('plugin_action.sale_notice_title'),
                            'content' => t('plugin_action.sale_notice_content', [
                                'username' => $buyerName,
                                'plugin' => $plugin['name'],
                                'income' => $incomeText,
                            ]),
                            'type' => 'plugin_purchase',
                            'link' => '/UserPlugin/list.html',
                            'variables' => [
                                'buyer_name' => $buyerName,
                                'plugin_name' => $plugin['name'],
                                'income' => $incomeText,
                            ],
                            'is_read' => 0,
                            'created_at' => datetime(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Plugin purchase notification failed: ' . $e->getMessage());
                }
                return ['success' => true, 'msg' => t('pay.success')];
            } catch (\Throwable $e) {
                Db::rollback();
                Log::error('Plugin payment callback failed: ' . $e->getMessage(), ['exception' => $e, 'order_no' => (string)$orderNo]);
                return ['success' => false, 'msg' => t('pay.result_processing_failed')];
            }
        } catch (\Throwable $e) {
            Log::error('Plugin payment callback exception: ' . $e->getMessage(), ['exception' => $e, 'order_no' => (string)$orderNo]);
            return ['success' => false, 'msg' => t('common.server_error')];
        }
    }
}
