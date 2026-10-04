<?php

namespace app\common\service;

/**
 * 插件销售平台抽成配置与结算。
 *
 * 所有插件购买入口都必须通过此服务计算，确保开关、比例、金额舍入
 * 以及订单快照保持一致。
 */
class PluginCommissionService
{
    private const DEFAULT_RATE = '10.00';

    public static function config(): array
    {
        return self::normalizeConfig([
            'enabled' => conf('plugin_commission_enabled'),
            'rate' => conf('plugin_commission_rate'),
        ]);
    }

    public static function normalizeConfig(array $config): array
    {
        $enabled = self::normalizeBool($config['enabled'] ?? null, true);

        try {
            $rate = self::validateRate($config['rate'] ?? self::DEFAULT_RATE);
        } catch (\InvalidArgumentException $e) {
            $rate = self::DEFAULT_RATE;
        }

        return [
            'enabled' => $enabled,
            'rate' => $rate,
        ];
    }

    /**
     * 校验并格式化后台填写的抽成比例，最多保留两位小数。
     */
    public static function validateRate($rate): string
    {
        if (!is_scalar($rate)) {
            throw new \InvalidArgumentException('插件平台抽成比例格式不正确');
        }

        $value = trim((string)$rate);
        if ($value === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new \InvalidArgumentException('插件平台抽成比例须为 0 到 100，最多保留两位小数');
        }

        $basisPoints = sf_money_to_cents($value);
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new \InvalidArgumentException('插件平台抽成比例须在 0 到 100 之间');
        }

        return sf_money_from_cents($basisPoints);
    }

    public static function settle($price, string $payType): array
    {
        return self::calculate($price, $payType, self::config());
    }

    /**
     * 返回可直接写入 plugin_order 的结算快照。
     */
    public static function calculate($price, string $payType, array $config): array
    {
        $price = sf_money_format($price);
        if (sf_money_to_cents($price) < 0) {
            throw new \InvalidArgumentException('插件订单金额不能为负数');
        }

        $normalized = self::normalizeConfig($config);
        $isPoints = strtolower(trim($payType)) === 'points';
        $commissionEnabled = $normalized['enabled'] && !$isPoints;
        $commissionRate = $commissionEnabled ? $normalized['rate'] : '0.00';
        $commissionAmount = $commissionEnabled
            ? sf_money_apply_rate($price, $commissionRate)
            : '0.00';

        return [
            'enabled' => $commissionEnabled,
            'rate' => $commissionRate,
            'amount' => $commissionAmount,
            'developer_income' => sf_money_subtract($price, $commissionAmount),
            'pay_type' => $isPoints ? 'points' : strtolower(trim($payType)),
        ];
    }

    public static function summary(array $settlement): string
    {
        if (($settlement['pay_type'] ?? '') === 'points') {
            return '积分支付免抽成';
        }
        if (sf_money_to_cents($settlement['amount'] ?? 0) <= 0) {
            return '本单未收取平台抽成';
        }
        return '平台抽成' . self::displayRate($settlement['rate'] ?? 0)
            . '%（¥' . sf_money_format($settlement['amount'] ?? 0) . '）';
    }

    public static function displayRate($rate): string
    {
        $formatted = self::validateRate($rate);
        return rtrim(rtrim($formatted, '0'), '.');
    }

    private static function normalizeBool($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }
}
