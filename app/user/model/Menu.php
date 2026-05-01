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
        $cache = Cache::get('SF_UserMenu'.cookie('userId'));
        if(!empty($cache)){
            return $cache;
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
        $parent_id = [];
        // Role-based menu control: user sees shared + user menu entries.
        $data = Menu::where([['status', '=', 1], ['power', 'IN', MenuPermissionService::powersForRole('user')]])->select();

        foreach ($data as $key => $value) {
            if($value['url'] == 'Auth/list'){
                if($powerPriceInfo['addauth_power'] != 1) {
                    unset($data[$key]);
                    continue;
                }
            } else if ($value['url'] == 'Payment/list'){
                if($powerPriceInfo['addpay_power'] != 1) {
                    unset($data[$key]);
                    continue;
                }
            } else if ($value['url'] == 'User/list'){
                if($powerPriceInfo['adduser_power'] != 1) {
                    unset($data[$key]);
                    continue;
                }
            } else if ($value['url'] == 'Pirate/list'){
                if($powerPriceInfo['pirate_power'] != 1) {
                    unset($data[$key]);
                    continue;
                }
            }

            if ($value['parentid'] == 0) {
                if($value['name'] == '授权管理'){
                    if($powerPriceInfo['addpay_power'] != 1 && $powerPriceInfo['addauth_power'] != 1){
                        unset($data[$key]);
                        continue;
                    }
                }
                $parent_id[$key] = $value;
            }
        }
        $all_node_lists = $this->setMenuTree($parent_id, $data); //用于检测是否有子菜单
        $all_node_lists = MenuPermissionService::tagRole($all_node_lists);
        Cache::tag('SF_Menu')->set('SF_UserMenu'.cookie('userId'), $all_node_lists);
        return $all_node_lists;
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
