<?php

namespace app\admin\model;
use app\common\model\BaseModel;
use think\Exception;

/**
 * 盗版-模型
 * @author 陌上花开
 * @since 2022/7/3
 * Class PirateModel
 * @package app\admin\model
 */
class PirateModel extends BaseModel
{
    // 设置数据表
    protected $name = 'pirate';

    public function getInfo($id){
        try{
            $result = self::where('id', $id)->find();
            if($result){
                return $result;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('pirate.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('pirate.not_exist'));
            }
            self::where('id', $id)->delete();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = sf_page_limit($post['limit'] ?? null, 10);
            $current_page = sf_page_number($post['current_page'] ?? null);
            $data = $this->buildSearchWhere('id|pirate_info', 'text', '');

            $list = self::order('addtime' ,'desc')->where($data)->paginate([
                'list_rows'=> $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
