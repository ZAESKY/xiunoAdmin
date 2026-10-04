<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * Normalize check-in milestone configuration across legacy storage formats.
 */
final class CheckinConfigService
{
    /**
     * @return int[]
     */
    public static function normalizeIntegerList($value, int $minimum = 1): array
    {
        $value = self::decodeStructuredValue($value);

        if (is_array($value)) {
            if (isset($value['field']) && is_array($value['field'])) {
                $value = $value['field'];
            } elseif (isset($value['value']) && is_array($value['value'])) {
                $value = $value['value'];
            } else {
                $value = array_values($value);
            }
        } else {
            $value = trim((string)$value);
            $value = preg_split('/[\s,，、;；]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $item = self::decodeHtmlEntities($item);
            }
            $item = trim((string)$item);
            if ($item === '' || !preg_match('/^\d+$/D', $item)) {
                throw new \InvalidArgumentException('打卡天数和奖励积分只能填写整数，并使用英文逗号分隔');
            }

            $number = (int)$item;
            if ($number < $minimum) {
                throw new \InvalidArgumentException($minimum > 0
                    ? '连续打卡天数必须是大于 0 的整数'
                    : '连续打卡奖励积分不能为负数');
            }
            $result[] = $number;
        }

        if ($result === []) {
            throw new \InvalidArgumentException('打卡天数和奖励积分不能为空');
        }

        return $result;
    }

    /**
     * @return array{0:int[],1:int[]}
     */
    public static function normalizeMilestones($days, $bonuses): array
    {
        $days = self::normalizeIntegerList($days, 1);
        $bonuses = self::normalizeIntegerList($bonuses, 0);

        if (count($days) !== count($bonuses)) {
            throw new \InvalidArgumentException('连续打卡天数与奖励积分的数量必须一致');
        }

        $previous = 0;
        foreach ($days as $day) {
            if ($day <= $previous) {
                throw new \InvalidArgumentException('连续打卡天数必须按从小到大排列，且不能重复');
            }
            $previous = $day;
        }

        return [$days, $bonuses];
    }

    public static function canonicalize($value, int $minimum = 1): string
    {
        return implode(',', self::normalizeIntegerList($value, $minimum));
    }

    private static function decodeStructuredValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = self::decodeHtmlEntities(trim($value));
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        if ($first !== '[' && $first !== '{') {
            return $value;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private static function decodeHtmlEntities(string $value): string
    {
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }
        return $value;
    }
}
