<?php

namespace app\admin\model;

use app\admin\validate\User;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * 用户-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class UserModel
 * @package app\admin\model
 */
class UserModel extends BaseModel
{
    protected $name = 'user';

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
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($post['appid'])?intval($post['appid']):null;
        $username = !empty($post['username'])?$post['username']:null;
        $password = !empty($post['password'])?$post['password']:null;
        $power = !empty($post['power'])?$post['power']:null;
        $qq = !empty($post['qq'])?intval($post['qq']):null;
        $email = !empty($post['email'])?$post['email']:'';
        $phone = !empty($post['phone'])?intval($post['phone']):'';
        $balance = !empty($post['balance'])?floatval($post['balance']):0;
        $integral = !empty($post['integral'])?intval($post['integral']):0;
        $ip = !empty($post['ip'])?serialize(explode('|',$post['ip'])):'';
        $status = !empty($post['status'])?1:0;
        $userid = !empty($post['userid'])?intval($post['userid']):0;

        try {
            validate(User::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        if(parent::getPowerPriceInfo($power) == false){
            return message('该权限不存在！' ,false);
        }
        if(!empty($id)){
            $row = $this->getInfo($id);
            if(!$row){
                return message('不存在此用户！' ,false);
            }
            if($username != $row['username']){
                $row2 = self::where(['username'=>$username, 'appid'=>$appid])->find();
                if($row2){
                    return message('该应用已存在此用户名！' ,false);
                }
            }
            $data = [
                'power' => $power,
                'username' => $username,
                'password' => $password,
                'qq' => $qq,
                'email' => $email,
                'phone' => $phone,
                'balance' => $balance,
                'integral' => $integral,
                'ip' => $ip,
                'status' => $status,
                'appid' => $appid,
                'userid' => $userid
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                Cache::delete('SF_UserMenu'.$id);
                return message('编辑成功！' ,true);
            } catch (\Exception $e) {
                return message('编辑失败！'.$e->getMessage() ,false);
            }
        }else{
            $row = self::where(['username'=>$username, 'appid'=>$appid])->find();
            if($row){
                return message('该应用已存在此用户名！' ,false);
            }
            $data = [
                'power' => $power,
                'username' => $username,
                'password' => $password,
                'qq' => $qq,
                'email' => $email,
                'phone' => $phone,
                'balance' => $balance,
                'integral' => $integral,
                'ip' => $ip,
                'addtime' => datetime(),
                'status' => $status,
                'appid' => $appid,
                'userid' => $userid
            ];
            try{
                self::insert($data);
                return message('添加成功！' ,true);
            } catch (\Exception $e) {
                return message('添加失败！'.$e->getMessage() ,false);
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
                throw new Exception('不存在此用户！');
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
                throw new Exception('不存在此用户！');
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getAppUserList($appid){
        try{
            $list = self::where('appid', $appid)->select();
            return $list->toArray();
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $appid = !empty($post['appid'])?$post['appid']:null;
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']):null;
            $data = [];
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }

            if($text !== null) {
                $data[] = ['id|username', 'like', '%'.$text.'%'];
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