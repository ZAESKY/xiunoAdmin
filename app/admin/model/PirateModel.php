<?php

namespace app\admin\model;
use app\common\model\BaseModel;

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
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $text = isset($post['text'])?$post['text']:null;

            $data = [];

            if($text !== null) {
                $data[] = ['id|pirate_info', 'like', '%' . $text . '%'];
            }

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
