<?php

namespace app\common\service;

/**
 * Lightweight role mapper for the existing menu.power field.
 *
 * power: 0 = shared, 1 = admin, 2 = user
 */
class MenuPermissionService
{
    public static function powersForRole(string $role): array
    {
        return $role === 'admin' ? [0, 1] : [0, 2];
    }

    public static function roleFromPower($power): string
    {
        if ((int)$power === 1) {
            return 'admin';
        }
        if ((int)$power === 2) {
            return 'user';
        }
        return 'all';
    }

    public static function tagRole($menuList)
    {
        foreach ($menuList as $key => $item) {
            $menuList[$key]['role'] = self::roleFromPower($item['power'] ?? 0);
            if (!empty($item['children']) && is_array($item['children'])) {
                $menuList[$key]['children'] = self::tagRole($item['children']);
            }
        }
        return $menuList;
    }
}
