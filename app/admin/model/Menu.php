<?php
namespace app\admin\model;

use app\common\model\BaseModel;
use app\common\service\MenuPermissionService;
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
            // Role-based menu control: admin sees shared + admin menu entries.
            // 排除已废弃的模板配置菜单
            $data = self::where([['status', '=', 1], ['power', 'IN', MenuPermissionService::powersForRole('admin')], ['url', '<>', 'Set/template']])
                ->order('id', 'asc')
                ->select()
                ->toArray();
            $data = $this->normalizeMenuRows($data, 'admin');
            foreach ($data as $key => $value) {
                if ((int)$value['parentid'] === 0) {
                    $parent_id[$key] = $value;
                }
            }
            $all_node_lists = $this->setMenuTree($parent_id, $data); //用于检测是否有子菜单
            $all_node_lists = MenuPermissionService::tagRole($all_node_lists);
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
                    $data[$data_key]['children'] = $this->setMenuTree($children, $all_data);
                }
            }
            return $data;
        }catch (\Exception $e){
            return [];
        }
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
