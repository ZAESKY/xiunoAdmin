<?php
namespace app\user\model;

use app\common\model\BaseModel;
use app\common\service\MenuPermissionService;
use think\facade\Cache;

/**
 * 菜单模型
 * @author 陌上花开
 * @since: 2022/6/30
 * Class Menu
 * @package app\user\model
 */
class Menu extends BaseModel
{
    private const CACHE_VERSION = 'v2';

    // 设置数据表
    protected $name = "menu";

    public function getOne(array $map){
        try{
            $res = self::where($map)->find();
            if($res){
                return $res;
            }else{
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getList(){
        $alwaysHiddenUrls = ['PointLog/index', 'Rebate/myRebateList', 'Checkin/records', 'MyList/auth'];
        $userId = intval(cookie('userId'));
        $cacheKey = self::cacheKey($userId);
        try {
            $cache = Cache::get($cacheKey);
        } catch (\Throwable $e) {
            $cache = null;
        }
        if ($this->isValidMenuTree($cache)) {
            return $this->filterHiddenMenuUrls($cache, $alwaysHiddenUrls);
        }
        if ($cache !== null) {
            try {
                Cache::delete($cacheKey);
            } catch (\Throwable $e) {
                // 菜单缓存损坏时继续从数据库重建，不能阻断用户后台。
            }
        }
        try{
            $userModel = new \app\user\model\User();
            $userInfo = $userModel->getInfo();
            if (!$userInfo) {
                return message(t("user.info_error").'[errorCode:UserInfoError]', false);
            }
        }catch (\Exception $e){
            return message(t("user.info_error").'[errorCode:UserInfoError]' ,false);
        }

        $powerPriceModel = new \app\admin\model\PowerPriceModel();
        $powerPriceInfo = $powerPriceModel->getInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message(t("user.power_info_error").'[errorCode:GetUserPowerInfoError]' ,false);
        }
        // select()->toArray() 转为普通数组，避免 ThinkPHP Collection 上 unset 不可靠
        $data = Menu::where([['status', '=', 1], ['power', 'IN', MenuPermissionService::powersForRole('user')]])
            ->order('id', 'asc')
            ->select()
            ->toArray();
        $data = $this->normalizeMenuRows($data, 'user');

        $parent_id = [];
        $hiddenUrls = $alwaysHiddenUrls;
        if ($powerPriceInfo['adduser_power'] != 1) {
            $hiddenUrls[] = 'User/list';
        }
        if ($powerPriceInfo['pirate_power'] != 1) {
            $hiddenUrls[] = 'Pirate/list';
        }
        if ($powerPriceInfo['discount_code_enabled'] != 1) {
            $hiddenUrls[] = 'Rebate/index';
        }

        foreach ($data as $key => $value) {
            if (in_array($value['url'], $hiddenUrls, true)) {
                unset($data[$key]);
                continue;
            }
            if ($value['parentid'] == 0) {
                $parent_id[] = $value;
            }
        }
        $all_node_lists = $this->setMenuTree($parent_id, $data);
        $all_node_lists = $this->pruneEmptyParents($all_node_lists);
        $all_node_lists = MenuPermissionService::tagRole($all_node_lists);
        try {
            Cache::tag('QH_Menu')->set($cacheKey, $all_node_lists);
        } catch (\Throwable $e) {
            // 缓存属于可选加速层，写入失败不影响菜单正常返回。
        }
        return $all_node_lists;
    }

    public static function clearUserCache(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        foreach ([self::cacheKey($userId), 'QH_UserMenu'.$userId] as $key) {
            try {
                Cache::delete($key);
            } catch (\Throwable $e) {
                // 用户资料已经保存成功时，缓存清理失败不应回滚业务数据。
            }
        }
    }

    private static function cacheKey(int $userId): string
    {
        return 'QH_UserMenu:'.self::CACHE_VERSION.':'.$userId;
    }

    private function isValidMenuTree($menus): bool
    {
        if (!is_array($menus) || empty($menus)) {
            return false;
        }
        foreach ($menus as $menu) {
            if (!is_array($menu)
                || !array_key_exists('id', $menu)
                || !array_key_exists('name', $menu)
                || !array_key_exists('url', $menu)
                || !array_key_exists('parentid', $menu)
            ) {
                return false;
            }
            if (array_key_exists('children', $menu)
                && (!$this->isValidMenuTree($menu['children'] ?? null))
            ) {
                return false;
            }
        }
        return true;
    }

    private function filterHiddenMenuUrls(array $menus, array $hiddenUrls): array
    {
        $filtered = [];
        foreach ($menus as $menu) {
            if (isset($menu['url']) && in_array($menu['url'], $hiddenUrls, true)) {
                continue;
            }
            if (!empty($menu['children']) && is_array($menu['children'])) {
                $menu['children'] = $this->filterHiddenMenuUrls($menu['children'], $hiddenUrls);
            }
            $filtered[] = $menu;
        }
        return array_values($filtered);
    }

    /**
     * Remove placeholder groups whose children were filtered by user power.
     */
    private function pruneEmptyParents(array $menus): array
    {
        $result = [];
        foreach ($menus as $menu) {
            if (!empty($menu['children']) && is_array($menu['children'])) {
                $menu['children'] = $this->pruneEmptyParents($menu['children']);
            }
            $url = ltrim((string)($menu['url'] ?? ''), '/');
            if (in_array($url, ['', '#'], true) && empty($menu['children'])) {
                continue;
            }
            $result[] = $menu;
        }
        return array_values($result);
    }

    private function setMenuTree($data = [], $all_data = []){
        foreach ($data as $data_key => $data_value) {
        $children = [];
           foreach ($all_data as $all_data_key => $all_data_value) {
               if ($all_data_value['parentid'] == $data_value['id']) {
                   $children[] = $all_data_value;
               }
            }
            if (count($children) > 0) {
                $data[$data_key]['children'] = $this->setMenuTree($children, $all_data);
            }
        }
        return $data;
    }

    private function normalizeMenuRows(array $rows, string $role): array
    {
        $rolePower = $role === 'admin' ? 1 : 2;
        $normalized = [];
        $rankMap = [];

        foreach ($rows as $row) {
            $row['url'] = ltrim((string)($row['url'] ?? ''), '/');
            $isTopParent = (int)($row['parentid'] ?? 0) === 0;
            $url = $row['url'];
            $name = (string)($row['name'] ?? '');
            $parentid = (int)($row['parentid'] ?? 0);
            if ($isTopParent && in_array($url, ['', '#'], true)) {
                $key = 'parent:' . $name;
            } elseif (in_array($url, ['', '#'], true)) {
                $key = 'parent:' . $parentid . ':' . $name;
            } else {
                $key = 'url:' . $url;
            }

            $rank = ((int)($row['power'] ?? 0) === $rolePower ? 10 : 0) - ((int)($row['id'] ?? 0) / 1000000);
            if (!isset($normalized[$key]) || $rank > $rankMap[$key]) {
                $normalized[$key] = $row;
                $rankMap[$key] = $rank;
            }
        }

        return array_values($normalized);
    }
}
