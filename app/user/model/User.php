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

namespace app\user\model;

use app\common\model\BaseModel;
use think\facade\Cache;
use think\Validate;
use think\facade\Db;
/**
 * 人员管理模型
 * @author 陌上花开
 * @since: 2022/6/30
 * Class User
 * @package app\user\model
 */
class User extends BaseModel
{
    // 设置数据表名称
    protected $name = 'user';

    public function getInfo(){
        $userId = cookie('userId');
        if(empty($userId)) return false;
        $info = self::where('id', $userId)->find();
        if ($info) {
            // 头像
            if ($info['qq']) {
                $info['img'] = '//q1.qlogo.cn/g?b=qq&nk='.$info['qq'].'&s=100';
            } else {
                $info['img'] = '/Assets/img/logo.png';
            }
            return $info;
        }
        return false;
    }

    public function editUserInfo(){
        $post = request()->post();
        $type = !empty($post['type'])?$post['type']:null;
        $code = !empty($post['code'])?$post['code']:null;
        $content = !empty($post['content'])?$post['content']:null;
        if(empty($code)) return message("请输入验证码！" ,false);
        switch ($type){
            case 'changeBindingMail':
                if(empty($content)) return message('请输入要绑定的邮箱！' ,false);
                if(!empty(cache('changeBindingMail'.cookie('userId')))){
                    cache('changeBindingMail'.cookie('userId'),null);
                    $validate = new Validate([
                        'content' => 'email'
                    ]);
                    if (!$validate->check(['content' => $content])) return message($validate->getError() ,false);
                    $result = $this->setOne(['email' => $content]);
                    if($result){
                        return message('绑定成功！' ,true);
                    }else{
                        return message('绑定邮箱失败！[errorCode:UserBindingMailError]' ,false);
                    }
                }else{
                    return message('不存在此验证码！' ,false);
                }
            default:
                return message('不存在此操作！' ,false);
        }
    }

    public function editConfig(){
        try{
            $post = request()->post();
            $userInfo = $this->getInfo();
            if(!$userInfo) return false;
            if(empty($userInfo['config'])){
                $oldConfig = [];
            }else{
                $oldConfig = unserialize($userInfo['config']);
            }
            $newConfig = array_merge($oldConfig, $post);
            if(empty($newConfig)){
                $newConfig = '';
            }else{
                $newConfig = serialize($newConfig);
            }
            if($this->setOne(['config' => $newConfig])){
                return true;
            }else{
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    public function getOne($username){
        $info = self::where('username', $username)->find();
        return $info;
    }

    public function setOne(array $wap){
        try{
            $userId = cookie('userId');
            if(empty($userId)) return false;
            $result = self::where('id', $userId)->data($wap)->update();
            if($result === false) return false;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getAccessToken($access_token){
        $info = self::where('access_token', $access_token)->find();
        return $info;
    }

    public function updatePower(){
        $post = request()->post();
        $power = !empty($post['power'])?intval($post['power']):null;
        if(empty($power)) return message('缺少POWER参数！' ,false);
        try{
            $userInfo = $this->getInfo();
            if(!$userInfo) return message('获取用户信息错误！' ,false);
            $userPower = Db::name('power_price')->where('id',$userInfo['power'])->find();
            $powerPriceModel = new \app\admin\model\PowerPriceModel();
            $result = $powerPriceModel->getUserUpdatePowerList($userInfo['power'], true);
            if(!in_array($power,$result)){
                return message('此权限您无权限升级！' ,false, $result);
            }
            $row = Db::name('power_price')->where('id',$power)->find();
            if(!$row) return message('不存在此权限！' ,false);
            if(!$userPower) return message('用户权限错误！' ,false);
            $allmoney = round((($row['money'] - $userPower['money']) > 0 ? ($row['money'] - $userPower['money']) : 0), 2);
            if($allmoney > $userInfo['balance']) return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元' ,false);
            $remainderBalance = $userInfo['balance'] - $allmoney;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance]);
                if(!$result){
                    return message('更新用户信息失败！[errorCode:ReduceUserBalanceError]' ,false);
                }
            } catch (\Exception $e) {
                return message('更新用户信息失败！[errorCode:ReduceUserBalanceError]',false);
            }
            $data = [
                "power" => $row['id']
            ];
            try {
                self::where('id', $userInfo['id'])
                    ->data($data)
                    ->update();
                $content = [
                    'Title' => '升级权限',
                    '操作' => '升级权限',
                    '权限名称' => $row['name'],
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                Cache::delete('SF_UserMenu'.$userInfo['id']);
                return message('升级成功！<br> 花费：'.$allmoney.' 元 <br> 余额：'.$remainderBalance.' 元', true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '升级权限',
                    '操作' => '升级权限',
                    '权限名称' => $row['name'],
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => '[errorCode:EditPaymentError]'
                ];
                event('ActionLog', $content);
                return message('升级失败！[errorCode:EditUserPowerError]', false);
            }
        } catch (\Exception $e) {
            return message('获取失败！[errorCode:GetUserUpdatePowerListError]'.$e->getMessage() ,false);
        }


    }
}