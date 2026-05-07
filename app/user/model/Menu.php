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
        $alwaysHiddenUrls = ['PointLog/index'];
        $cache = Cache::get('SF_UserMenu'.cookie('userId'));
        if(!empty($cache)){
            return $this->filterHiddenMenuUrls($cache, $alwaysHiddenUrls);
        }
        try{
            $userInfo = parent::getUserInfo();
        }catch (\Exception $e){
            return message(t("user.info_error").'[errorCode:UserInfoError]' ,false);
        }

        $powerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message(t("user.power_info_error").'[errorCode:GetUserPowerInfoError]' ,false);
        }
        // select()->toArray() 转为普通数组，避免 ThinkPHP Collection 上 unset 不可靠
        $data = Menu::where([['status', '=', 1], ['power', 'IN', MenuPermissionService::powersForRole('user')]])->select()->toArray();

        $parent_id = [];
        $hiddenUrls = $alwaysHiddenUrls;
        if ($powerPriceInfo['addauth_power'] != 1) {
            $hiddenUrls[] = 'Auth/list';
        }
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
        $all_node_lists = MenuPermissionService::tagRole($all_node_lists);
        Cache::tag('SF_Menu')->set('SF_UserMenu'.cookie('userId'), $all_node_lists);
        return $all_node_lists;
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

    private function setMenuTree($data = [], $all_data = []){
        foreach ($data as $data_key => $data_value) {
        $children = [];
           foreach ($all_data as $all_data_key => $all_data_value) {
               if ($all_data_value['parentid'] == $data_value['id']) {
                   $children[] = $all_data_value;
               }
            }
            if (count($children) > 0) {
                $this->setMenuTree($children, $all_data);
                $data[$data_key]['children'] = $children;
            }
        }
        return $data;
    }
}
