<?php

namespace app\user\model;

use app\user\validate\User;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;

/**
 * 用户-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class UserModel
 * @package app\admin\model
 */
class UserModel extends BaseModel
{
    protected $name = "user";

    public function initialize(){
        parent::initialize();
    }

    public function getInfo($id){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return false;
            }
            $result = self::where(['id' => $id, 'userid' => $userInfo['id']])->find();
            if($result){
                $content = [
                    'Title' => '查找用户',
                    '操作' => '查找用户',
                    '用户ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $result;
            }else{
                $content = [
                    'Title' => '查找用户',
                    '操作' => '查找用户',
                    '用户ID' => $id,
                    'Result' => '[errorCode:GetUserInfoError]'
                ];
                event('ActionLog', $content);
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    public function edit(){
        $userInfo = parent::getUserInfo();
        if(!$userInfo){
            return message("获取用户信息失败！[errorCode:UserInfoError]" ,false);
        }
        $userPowerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$userPowerPriceInfo) {
            return message("获取用户权限信息失败！[errorCode:GetUserPowerInfoError]" ,false);
        }
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $post['appid'] = $appid;
        $username = !empty($post['username'])?$post['username']:null;
        $password = !empty($post['password'])?$post['password']:null;
        $power = !empty($post['power'])?$post['power']:null;
        $qq = !empty($post['qq'])?intval($post['qq']):null;
        $email = !empty($post['email'])?$post['email']:'';
        $phone = !empty($post['phone'])?intval($post['phone']):'';
        $balance = !empty($post['balance'])?round($post['balance'],2):0;
        $integral = !empty($post['integral'])?intval($post['integral']):0;
        $ip = !empty($post['ip'])?$post['ip']:'';
        $status = !empty($post['status'])?1:0;

        try {
            validate(User::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }

        if(!empty($id)){
            $row = $this->getInfo($id);
            if(!$row){
                return message("不存在此用户！" ,false);
            }
            $isSubordinatePower = parent::isSubordinatePower($row['power']);
            if(!$isSubordinatePower){
                return message("该权限不是您的下级，无权限修改！" ,false);
            }
            if($username != $row['username']){
                $row2 = self::where(['username' => $username, 'appid'=> $appid])->find();
                if($row2){
                    return message("该应用已存在此用户名！" ,false);
                }
            }
            if($power != -1) {
                if(parent::getPowerPriceInfo($power) == false){
                    return message("该权限不存在！" ,false);
                }
                $isSubordinatePower = parent::isSubordinatePower($power);
                if(!$isSubordinatePower){
                    return message("该权限不是您的下级，无权限升级！" ,false);
                }
                $isSubordinatePower = parent::isSubordinatePower($power, $row['power']);
                if ($isSubordinatePower) {
                    return message("您无法进行降级操作！", false);
                }
                $nowPowerPriceInfo = parent::getPowerPriceInfo($row['power']);
                if (!$nowPowerPriceInfo) {
                    return message("获取权限信息失败！[errorCode:GetPowerInfoError]", false);
                }
                $newPowerPriceInfo = parent::getPowerPriceInfo($power);
                if (!$newPowerPriceInfo) {
                    return message("获取权限信息失败！[errorCode:GetPowerInfoError]", false);
                }
                $price = round(($newPowerPriceInfo['money'] - $nowPowerPriceInfo['money']) * floatval($userPowerPriceInfo['adduser_discount'] / 100) ,2);
            }else{
                $price = 0;
            }
            $allmoney = $price + $balance;

            if($allmoney > $userInfo['balance']){
                return message("您的余额不足，请充值后再来！<br> 您的余额：".$userInfo['balance']." 元<br>需要金额：".$allmoney." 元" ,false);
            }

            if($integral != $row['integral']){
                if($integral > $userInfo['integral']){
                    return message("您的积分不足，请获取后再来！<br> 您的积分：".$userInfo['integral']." 个<br>需要积分：".$integral." 个" ,false);
                }
            }
            $remainderBalance = $userInfo['balance'] - $allmoney;
            $remainderIntegral = $userInfo['integral'] - $integral;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance, 'integral' => $remainderIntegral]);
                if(!$result){
                    return message("更新用户信息失败！[errorCode:ReduceUserBalanceError]" ,false);
                }
            } catch (\Exception $e) {
                return message("更新用户信息失败！[errorCode:ReduceUserBalanceError]",false);
            }

            $data = [
                "power" => ($power == -1)?$row['power']:$power,
                "username" => $username,
                "password" => $password,
                "qq" => $qq,
                "email" => $email,
                "phone" => $phone,
                "ip" => $ip,
                "status" => $status,
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->inc('balance', $balance)
                    ->inc('integral', $integral)
                    ->update();
                $content = [
                    'Title' => '编辑用户',
                    '操作' => '编辑用户',
                    '用户ID' => $id,
                    '花费' => '- '.$allmoney.' 元',
                    '积分' => '- '.$integral.' 个',
                    '剩余余额' => $remainderBalance.' 元',
                    '剩余积分' => $remainderIntegral.' 个',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return message("编辑成功！<br> 花费：".$allmoney." 元 , ".$integral." 积分<br> 余额：".$remainderBalance." 元 <br> 积分：".$remainderIntegral." 个" ,true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '编辑用户',
                    '操作' => '编辑用户',
                    '用户ID' => $id,
                    '花费' => '- '.$allmoney.' 元',
                    '积分' => '- '.$integral.' 个',
                    '剩余余额' => $remainderBalance.' 元',
                    '剩余积分' => $remainderIntegral.' 个',
                    'Result' => '[errorCode:EditUserInfoError]'
                ];
                event('ActionLog', $content);
                return message("编辑失败！[errorCode:EditUserInfoError]" ,false);
            }
        }else{
            try{
                $appInfo = parent::getAppInfo($userInfo['appid']);
            }catch (\Exception $e){
                return message("获取应用信息失败！" ,false);
            }
            $row = self::where(['username' => $username, 'appid' => $appid])->find();
            if($row){
                return message("该应用已存在此用户名！" ,false);
            }
            if(parent::getPowerPriceInfo($power) == false){
                return message("该权限不存在！" ,false);
            }
            $isSubordinatePower = parent::isSubordinatePower($power);
            if(!$isSubordinatePower){
                return message("该权限不是您的下级，无权限添加！" ,false);
            }
            $powerPriceInfo = parent::getPowerPriceInfo($power);
            if (!$powerPriceInfo) {
                return message("获取权限信息失败！[errorCode:GetPowerInfoError]", false);
            }
            $price = round($powerPriceInfo['money'] * floatval($userPowerPriceInfo['adduser_discount'] / 100), 2);
            $allmoney = $price + $balance;

            if($allmoney > $userInfo['balance']){
                return message("您的余额不足，请充值后再来！<br> 您的余额：".$userInfo['balance']." 元<br>需要金额：".$allmoney." 元" ,false);
            }

            if($integral != $row['integral']){
                if($integral > $userInfo['integral']){
                    return message("您的积分不足，请获取后再来！<br> 您的积分：".$userInfo['integral']." 个<br>需要积分：".$integral." 个" ,false);
                }
            }
            $remainderBalance = $userInfo['balance'] - $allmoney;
            $remainderIntegral = $userInfo['integral'] - $integral;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance, 'integral' => $remainderIntegral]);
                if(!$result){
                    return message("更新用户信息失败！[errorCode:ReduceUserBalanceError]" ,false);
                }
            } catch (\Exception $e) {
                return message("更新用户信息失败！[errorCode:ReduceUserBalanceError]",false);
            }

            $data = [
                "power" => $power,
                "username" => $username,
                "password" => $password,
                "qq" => $qq,
                "email" => $email,
                "phone" => $phone,
                "balance" => $balance + $appInfo['give_money'],
                "integral" => $integral,
                "ip" => $ip,
                "addtime" => datetime(),
                "status" => $status,
                "appid" => $appid,
                "userid" => $userInfo['id'],
            ];
            try{
                self::insert($data);
                $content = [
                    'Title' => '添加用户',
                    '操作' => '添加用户',
                    '花费' => '- '.$allmoney.' 元',
                    '积分' => '- '.$integral.' 个',
                    '剩余余额' => $remainderBalance.' 元',
                    '剩余积分' => $remainderIntegral.' 个',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return message("添加成功！<br> 花费：".$allmoney." 元 , ".$integral." 积分<br> 余额：".$remainderBalance." 元 <br> 积分：".$remainderIntegral." 个" ,true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '添加用户',
                    '操作' => '编辑用户',
                    '花费' => '- '.$allmoney.' 元',
                    '积分' => '- '.$integral.' 个',
                    '剩余余额' => $remainderBalance.' 元',
                    '剩余积分' => $remainderIntegral.' 个',
                    'Result' => '[errorCode:AddUserError]'
                ];
                event('ActionLog', $content);
                return message("添加失败！[errorCode:AddUserError]" ,false);
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
            $isSubordinatePower = parent::isSubordinatePower($row['power']);
            if(!$isSubordinatePower){
                throw new Exception('该权限不是您的下级，无权限删除！');
            }
            try{
                self::where('id', $id)->delete();
                $content = [
                    'Title' => '删除用户',
                    '操作' => '删除用户',
                    '用户ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return true;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '删除用户',
                    '操作' => '删除用户',
                    '用户ID' => $id,
                    'Result' => '[errorCode:DeleteUserError]'
                ];
                event('ActionLog', $content);
                throw new Exception('删除失败！[errorCode:DeleteUserError]');
            }
        }catch (\Exception $e){
            throw new Exception('删除失败！[errorCode:DeleteUserError]');
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
            $isSubordinatePower = parent::isSubordinatePower($row['power']);
            if(!$isSubordinatePower){
                throw new Exception('该权限不是您的下级，无权限修改状态！');
            }
            try{
                self::where('id', $id)
                    ->data(['status' => $status])
                    ->update();
                $content = [
                    'Title' => '编辑用户',
                    '操作' => '修改用户状态',
                    '用户ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return true;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '编辑用户',
                    '操作' => '修改用户状态',
                    '用户ID' => $id,
                    'Result' => '[errorCode:EditUserStatusError]'
                ];
                event('ActionLog', $content);
                throw new Exception('修改状态失败！[errorCode:EditUserStatusError]');
            }
        }catch (\Exception $e){
            throw new Exception('修改状态失败！[errorCode:EditUserStatusError]');
        }
    }

    public function list(){
        try{
            try{
                $userInfo = parent::getUserInfo();
                if(!$userInfo){
                    throw new Exception('获取用户信息失败！[errorCode:UserInfoError]');
                }
            }catch (\Exception $e){
                throw new Exception('获取用户信息失败！[errorCode:UserInfoError]');
            }
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']) : null;

            $data = [];
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
                $order = 'id';
            }else{
                throw new Exception('获取用户应用信息失败！[errorCode:UserAppIdEmpty]');
            }
            if($text !== null){
                $data[] = ['id|username', 'like', '%'.$text.'%'];
            }
            if($status !== null){
                $data[] = ['status', '=', $status];
            }
            $data[] = ['userid', '=', $userInfo['id']];
            try{
                $list = self::order($order ,'asc')->where($data)->paginate([
                    'list_rows'=> $limit,
                    'page' => $current_page,
                ]);
                $content = [
                    'Title' => '用户列表',
                    '操作' => '获取用户列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '用户列表',
                    '操作' => '获取用户列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetUserListError]'
                ];
                event('ActionLog', $content);
                throw new Exception('获取列表失败！[errorCode:GetUserListError]');
            }
        }catch (\Exception $e){
            throw new Exception('获取列表失败！[errorCode:GetUserListError]');
        }
    }
}