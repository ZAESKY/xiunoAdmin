<?php

namespace app\common\service;

/**
 * 账务流水展示服务。
 *
 * 流水类型由服务端统一转换为当前语言的展示名称，避免各页面维护不同的
 * JavaScript 字典，也避免将 user_update、checkin 等内部代码直接展示给用户。
 */
class AccountingLogService
{
    const LEDGER_BALANCE = 'balance';
    const LEDGER_POINT = 'point';

    private const BALANCE_TYPE_KEYS = [
        'recharge' => 'accounting.balance_type.recharge',
        'rebate' => 'accounting.balance_type.rebate',
        'cdkey_exchange' => 'accounting.balance_type.cdkey_exchange',
        'deduct' => 'accounting.balance_type.deduct',
        'admin_edit' => 'accounting.balance_type.admin_edit',
        'refund' => 'accounting.balance_type.refund',
        'deduct_plugin' => 'accounting.balance_type.deduct_plugin',
        'plugin_cdkey_buy' => 'accounting.balance_type.plugin_cdkey_buy',
        'plugin_income' => 'accounting.balance_type.plugin_income',
        'withdraw_apply' => 'accounting.balance_type.withdraw_apply',
        'withdraw_cancel' => 'accounting.balance_type.withdraw_cancel',
        'withdraw_reject' => 'accounting.balance_type.withdraw_reject',
        'withdraw_reject_refund' => 'accounting.balance_type.withdraw_reject_refund',
        'user_update' => 'accounting.balance_type.user_update',
        'cdkey_create' => 'accounting.balance_type.cdkey_create',
        'plugin_reward' => 'accounting.balance_type.plugin_reward',
    ];

    private const POINT_TYPE_KEYS = [
        'consume' => 'accounting.point_type.consume',
        'recharge' => 'accounting.point_type.recharge',
        'cdkey_exchange' => 'accounting.point_type.cdkey_exchange',
        'deduct_plugin' => 'accounting.point_type.deduct_plugin',
        'plugin_income' => 'accounting.point_type.plugin_income',
        'exchange' => 'accounting.point_type.exchange',
        'checkin' => 'accounting.point_type.checkin',
        'plugin_reward' => 'accounting.point_type.plugin_reward',
        'refund' => 'accounting.point_type.refund',
        'cancel' => 'accounting.point_type.cancel',
    ];

    public static function typeLanguageKeys($ledger)
    {
        return $ledger === self::LEDGER_POINT
            ? self::POINT_TYPE_KEYS
            : self::BALANCE_TYPE_KEYS;
    }

    public static function typeLabel($ledger, $type)
    {
        $type = strtolower(trim((string)$type));
        $keys = self::typeLanguageKeys($ledger);
        return t($keys[$type] ?? 'accounting.type.other');
    }

    /**
     * 为接口结果补充 type_label，并统一转换成可序列化数组。
     */
    public static function decorateItems($items, $ledger)
    {
        $result = [];
        foreach ($items as $item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $item = $item->toArray();
            } elseif (is_object($item)) {
                $item = (array)$item;
            }
            if (!is_array($item)) {
                continue;
            }
            $item['type_label'] = self::typeLabel($ledger, $item['type'] ?? '');
            $result[] = $item;
        }
        return $result;
    }
}
