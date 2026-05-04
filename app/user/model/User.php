<?php
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
        if(empty($code)) return message(t('notify.enter_captcha') ,false);
        switch ($type){
            case 'changeBindingMail':
                if(empty($content)) return message(t('user.bind_email_empty') ,false);
                if(!empty(cache('changeBindingMail'.cookie('userId')))){
                    cache('changeBindingMail'.cookie('userId'),null);
                    $validate = new Validate([
                        'content' => 'email'
                    ]);
                    if (!$validate->check(['content' => $content])) return message($validate->getError() ,false);
                    $result = $this->setOne(['email' => $content]);
                    if($result){
                        return message(t('user.bind_success') ,true);
                    }else{
                        return message(t('user.bind_failed').'[errorCode:UserBindingMailError]' ,false);
                    }
                }else{
                    return message(t('notify.captcha_expired') ,false);
                }
            case 'changeBindingQQ':
                $result = $this->setOne(['qq' => '']);
                if($result){
                    return message('QQ解绑成功' ,true);
                }else{
                    return message('QQ解绑失败' ,false);
                }
            default:
                return message(t('common_ui.type_error') ,false);
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
        if(empty($power)) return message(t('validation.missing_power') ,false);
        try{
            $userInfo = $this->getInfo();
            if(!$userInfo) return message(t('user.info_error') ,false);
            $userPower = Db::name('power_price')->where('id',$userInfo['power'])->find();
            $powerPriceModel = new \app\admin\model\PowerPriceModel();
            $result = $powerPriceModel->getUserUpdatePowerList($userInfo['power'], true);
            if(!in_array($power,$result)){
                return message(t('user.power_cannot_upgrade') ,false, $result);
            }
            $row = Db::name('power_price')->where('id',$power)->find();
            if(!$row) return message(t('power.not_exist') ,false);
            if(!$userPower) return message(t('user.power_error') ,false);
            $allmoney = round((($row['money'] - $userPower['money']) > 0 ? ($row['money'] - $userPower['money']) : 0), 2);
            if($allmoney > $userInfo['balance']) return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total', ['amount' => $allmoney]) ,false);
            $remainderBalance = $userInfo['balance'] - $allmoney;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance], '升级权限 -'.$allmoney.' 元');
                if(!$result){
                    return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]' ,false);
                }
            } catch (\Exception $e) {
                return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]',false);
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
                return message(t('user.upgrade_success').'<br> '.t('order_ui.total', ['amount' => $allmoney]).' <br> '.t('common_ui.balance_field').$remainderBalance, true);
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
                return message(t('user.upgrade_failed').'[errorCode:EditUserPowerError]', false);
            }
        } catch (\Exception $e) {
            return message(t('common.load_failed').'[errorCode:GetUserUpdatePowerListError]'.$e->getMessage() ,false);
        }


    }
}
