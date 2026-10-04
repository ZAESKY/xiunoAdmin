<?php

namespace app\admin\model;

use app\admin\validate\CheckType;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;

/**
 * 判断模式-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class CheckTypeModel
 * @package app\admin\model
 */
class CheckTypeModel extends BaseModel
{
    // 设置数据表名
    protected $name = "check_type";

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

    public function getCheckTypeName($check_type){
        try{
            $result = self::where('type', $check_type)->find();
            if($result){
                return $result;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function getCheckTypeList(){
        try{
            $list = self::order('id' ,'asc')->field('type,name')->where('status','1')->select();
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function edit(){
        $post = request()->post();
        $id = !empty($post['id'])?$post['id']:null;
        $name = !empty($post['name'])?$post['name']:null;
        $type = !empty($post['type'])?$post['type']:'';
        $status = !empty($post['status'])?$post['status']:0;

        try {
            validate(CheckType::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        if(!empty($id)){
            $data = [
                "name" => $name,
                "type" => $type,
                "status" => $status,
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                return message(t('user.edit_success') ,true);
            } catch (\Exception $e) {
                return message(t('user.edit_failed').$e->getMessage() ,false);
            }
        }else{
            $data = [
                "name" => $name,
                "type" => $type,
                "status" => $status,
                "addtime" => datetime()
            ];
            try{
                self::insert($data);
                return message(t('user.add_success') ,true);
            } catch (\Exception $e) {
                return message(t('user.add_failed').$e->getMessage() ,false);
            }
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('common.no_data'));
            }
            self::where('id', $id)->delete();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setStatus(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $status = !empty($post['status'])?1:0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('common.no_data'));
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
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
            $data = $this->buildSearchWhere('id|name');

            $list = self::order('id' ,'desc')->where($data)->paginate([
                'list_rows'=> $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
