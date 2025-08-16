<?php
/*
* +----------------------------------------------------------------------
* | SF 综合验证授权系统
* +----------------------------------------------------------------------
* | Quotes [ 花开的再灿烂，也有凋谢的一天，致我们过去的青春 ]
* +----------------------------------------------------------------------
* | Author: 陌上花开 <2129876388@qq.com>
* +----------------------------------------------------------------------
* | Date: 2022年1月19日 18:48:32
* +----------------------------------------------------------------------
*/

namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;
use think\facade\Cache;
/**
 * 菜单模型
 * @author 陌上花开
 * @since: 2022/6/30
 * Class Menu
 * @package app\admin\model
 */
class Menu extends BaseModel
{
    // 设置数据表
    protected $name = "menu";

    public function getOne(array $map){
        try{
            $res = Menu::where($map)->find();
            if($res){
                return $res;
            }else{
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public function edit(array $wap = []){
        if(empty($wap)){
            return false;
        }
        try{
            self::insertAll($wap);
            return true;
        }catch (\Exception $e){
            return false;
        }
    }

    public function getList(){
        try{
            $cache = Cache::get('SF_AdminMenu');
            if(!empty($cache)){
                return $cache;
            }
            $parent_id = [];
            $data = self::where([['status', '=', 1], ['power', 'IN', [0,1]]])->select();
            foreach ($data as $key => $value) {
                if ($value['parentid'] === 0) {
                    $parent_id[$key] = $value;
                }
            }
            $all_node_lists = $this->setMenuTree($parent_id, $data); //用于检测是否有子菜单
            Cache::tag('SF_Menu')->set('SF_AdminMenu', $all_node_lists);
            return $all_node_lists;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    private function setMenuTree($data = [], $all_data = []){
        try{
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
        }catch (\Exception $e){
            return [];
        }
    }
}