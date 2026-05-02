<?php
namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;

/**
 * 人员管理模型
 * @author 陌上花开
 * @since: 2022/6/30
 * Class Admin
 * @package app\admin\model
 */
class Admin extends BaseModel
{
    // 设置数据表名称
    protected $name = "admin";

    public function getInfo($id)
    {
        try{
            $info = self::where('id', $id)->find();
            if ($info) {
                // 头像
                if ($info['qq']) {
                    $info['img'] = 'http://q1.qlogo.cn/g?b=qq&nk='.$info['qq'].'&s=100';
                }
                return $info;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function getOne($username)
    {
        try{
            $info = self::where('username', $username)->find();
            return $info;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
    public function getAccessToken($access_token)
    {
        try{
            $info = self::where('access_token', $access_token)->find();
            return $info;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}