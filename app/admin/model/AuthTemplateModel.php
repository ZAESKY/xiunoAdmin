<?php

namespace app\admin\model;

use app\admin\validate\AuthTemplate;
use app\common\model\BaseModel;
use think\exception\ValidateException;
/**
 * 价格模板-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class AuthTemplateModel
 * @package app\admin\model
 */
class AuthTemplateModel extends BaseModel
{
    // 设置数据表名
    protected $name = "auth_template";

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

    public function edit(){
        $post = request()->post();
        $id = !empty($post['id'])?$post['id']:null;
        $check_type = !empty($post['check_type'])?$post['check_type']:null;
        $name = !empty($post['name'])?$post['name']:null;
        $status = !empty($post['status'])?$post['status']:0;

        try {
            validate(AuthTemplate::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        if(!empty($id)){
            $data = [
                "check_type" => $check_type,
                "name" => $name,
                "status" => $status,
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                return message("编辑成功！" ,true);
            } catch (\Exception $e) {
                return message("编辑失败！".$e->getMessage() ,false);
            }
        }else{
            $data = [
                "check_type" => $check_type,
                "name" => $name,
                "status" => $status,
                "addtime" => datetime()
            ];
            try{
                self::insert($data);
                return message("添加成功！" ,true);
            } catch (\Exception $e) {
                return message("添加失败！".$e->getMessage() ,false);
            }
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此价格模板！');
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
                throw new Exception('缺少ID参数！');
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此价格模板！');
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getList(){
        try{
            $list = self::where('status','1')->select();
            return $list;
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
            $status = isset($post['status'])?intval($post['status']):null;

            $data = [];

            if($text !== null) {
                $data[] = ['id|name', 'like', '%'.$text.'%'];
            }

            if($status !== null){
                $data[] = ['status', '=', $status];
            }

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