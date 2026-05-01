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
            return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
        }
        $userPowerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$userPowerPriceInfo) {
            return message(t('user.power_info_error').'[errorCode:GetUserPowerInfoError]' ,false);
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
                return message(t('user.not_exist') ,false);
            }
            $isSubordinatePower = parent::isSubordinatePower($row['power']);
            if(!$isSubordinatePower){
                return message(t('user.not_your_subordinate') ,false);
            }
            if($username != $row['username']){
                $row2 = self::where(['username' => $username, 'appid'=> $appid])->find();
                if($row2){
                    return message(t('user.username_exists') ,false);
                }
            }
            if($power != -1) {
                if(parent::getPowerPriceInfo($power) == false){
                    return message(t('power.not_exist') ,false);
                }
                $isSubordinatePower = parent::isSubordinatePower($power);
                if(!$isSubordinatePower){
                    return message(t('user.not_your_subordinate_upgrade') ,false);
                }
                $isSubordinatePower = parent::isSubordinatePower($power, $row['power']);
                if ($isSubordinatePower) {
                    return message(t('user.cannot_downgrade'), false);
                }
                $nowPowerPriceInfo = parent::getPowerPriceInfo($row['power']);
                if (!$nowPowerPriceInfo) {
                    return message(t('power.get_info_failed').'[errorCode:GetPowerInfoError]', false);
                }
                $newPowerPriceInfo = parent::getPowerPriceInfo($power);
                if (!$newPowerPriceInfo) {
                    return message(t('power.get_info_failed').'[errorCode:GetPowerInfoError]', false);
                }
                $price = round(($newPowerPriceInfo['money'] - $nowPowerPriceInfo['money']) * floatval($userPowerPriceInfo['adduser_discount'] / 100) ,2);
            }else{
                $price = 0;
            }
            $allmoney = $price + $balance;

            if($allmoney > $userInfo['balance']){
                return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total').'<br>'.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total') ,false);
            }

            if($integral != $row['integral']){
                if($integral > $userInfo['integral']){
                    return message(t('user.integral_insufficient').'<br> '.t('common_ui.integral_field').$userInfo['integral'].' '.t('common_ui.cdkey_type_label').'<br>'.t('common_ui.integral_field').$integral.' '.t('common_ui.cdkey_type_label') ,false);
                }
            }
            $remainderBalance = $userInfo['balance'] - $allmoney;
            $remainderIntegral = $userInfo['integral'] - $integral;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance, 'integral' => $remainderIntegral]);
                if(!$result){
                    return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]' ,false);
                }
            } catch (\Exception $e) {
                return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]',false);
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
                return message(t('user.edit_success').'<br> '.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total').' , '.$integral.' '.t('common_ui.integral_field').'<br> '.t('common_ui.balance_field').$remainderBalance.' '.t('order_ui.total').' <br> '.t('common_ui.integral_field').$remainderIntegral.' '.t('common_ui.cdkey_type_label') ,true);
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
                return message(t('user.edit_failed').'[errorCode:EditUserInfoError]' ,false);
            }
        }else{
            try{
                $appInfo = parent::getAppInfo($userInfo['appid']);
            }catch (\Exception $e){
                return message(t('app.get_info_failed') ,false);
            }
            $row = self::where(['username' => $username, 'appid' => $appid])->find();
            if($row){
                return message(t('user.username_exists') ,false);
            }
            if(parent::getPowerPriceInfo($power) == false){
                return message(t('power.not_exist') ,false);
            }
            $isSubordinatePower = parent::isSubordinatePower($power);
            if(!$isSubordinatePower){
                return message(t('user.not_your_subordinate_add') ,false);
            }
            $powerPriceInfo = parent::getPowerPriceInfo($power);
            if (!$powerPriceInfo) {
                return message(t('power.get_info_failed').'[errorCode:GetPowerInfoError]', false);
            }
            $price = round($powerPriceInfo['money'] * floatval($userPowerPriceInfo['adduser_discount'] / 100), 2);
            $allmoney = $price + $balance;

            if($allmoney > $userInfo['balance']){
                return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total').'<br>'.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total') ,false);
            }

            if($integral != $row['integral']){
                if($integral > $userInfo['integral']){
                    return message(t('user.integral_insufficient').'<br> '.t('common_ui.integral_field').$userInfo['integral'].' '.t('common_ui.cdkey_type_label').'<br>'.t('common_ui.integral_field').$integral.' '.t('common_ui.cdkey_type_label') ,false);
                }
            }
            $remainderBalance = $userInfo['balance'] - $allmoney;
            $remainderIntegral = $userInfo['integral'] - $integral;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance, 'integral' => $remainderIntegral]);
                if(!$result){
                    return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]' ,false);
                }
            } catch (\Exception $e) {
                return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]',false);
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
                return message(t('user.add_success').'<br> '.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total').' , '.$integral.' '.t('common_ui.integral_field').'<br> '.t('common_ui.balance_field').$remainderBalance.' '.t('order_ui.total').' <br> '.t('common_ui.integral_field').$remainderIntegral.' '.t('common_ui.cdkey_type_label') ,true);
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
                return message(t('user.add_failed').'[errorCode:AddUserError]' ,false);
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
                throw new Exception(t('user.not_exist'));
            }
            $isSubordinatePower = parent::isSubordinatePower($row['power']);
            if(!$isSubordinatePower){
                throw new Exception(t('user.not_your_subordinate_delete'));
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
                throw new Exception(t('user.delete_failed').'[errorCode:DeleteUserError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.delete_failed').'[errorCode:DeleteUserError]');
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
                throw new Exception(t('user.not_exist'));
            }
            $isSubordinatePower = parent::isSubordinatePower($row['power']);
            if(!$isSubordinatePower){
                throw new Exception(t('user.not_your_subordinate_status'));
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
                throw new Exception(t('user.status_change_success').'[errorCode:EditUserStatusError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.status_change_success').'[errorCode:EditUserStatusError]');
        }
    }

    public function list(){
        try{
            try{
                $userInfo = parent::getUserInfo();
                if(!$userInfo){
                    throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
                }
            }catch (\Exception $e){
                throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
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
                throw new Exception(t('user.info_error').'[errorCode:UserAppIdEmpty]');
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
                throw new Exception(t('user.list_failed').'[errorCode:GetUserListError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.list_failed').'[errorCode:GetUserListError]');
        }
    }
}
