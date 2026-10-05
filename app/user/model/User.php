<?php
namespace app\user\model;

use app\common\service\BindingMailVerificationService;
use app\common\model\BaseModel;
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
            $oauthAvatar = Db::name('user_social_identity')->alias('usi')
                ->join('social_identity si', 'si.id = usi.identity_id')
                ->where('usi.user_id', intval($userId))
                ->where('si.provider', 'qq')
                ->value('si.avatar');
            $oauthAvatar = qh_safe_url($oauthAvatar ?? '', false);
            if ($oauthAvatar !== '') {
                $info['img'] = $oauthAvatar;
            } elseif ($info['qq']) {
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
        $code = !empty($post['code'])?trim((string)$post['code']):'';
        $content = BindingMailVerificationService::normalizeEmail($post['content'] ?? '');
        switch ($type){
            case 'changeBindingMail':
                if(empty($content)) return message(t('user.bind_email_empty') ,false);
                if($code === '') return message(t('notify.enter_captcha') ,false);

                $validate = new Validate(['content' => 'email']);
                if (!$validate->check(['content' => $content])) return message($validate->getError() ,false);

                $userId = intval(cookie('userId'));
                $userInfo = $this->getInfo();
                if(!$userInfo) return message(t('user.account_abnormal'), false);
                $currentEmail = BindingMailVerificationService::normalizeEmail($userInfo['email'] ?? '');
                if($currentEmail !== '' && hash_equals($currentEmail, $content)){
                    return message('profile.new_email_same', false);
                }
                if(self::where('email', $content)->where('id', '<>', $userId)->find()){
                    return message('profile.email_already_used', false);
                }
                if($currentEmail !== '' && !BindingMailVerificationService::isOldVerified($userId, $currentEmail)){
                    return message('profile.verify_current_email_first', false);
                }

                $newResult = BindingMailVerificationService::verifyCode($userId, 'new', $content, $code);
                if(!$newResult['ok']){
                    return message($newResult['message'], false);
                }

                try {
                    $result = self::where('id', $userId)
                        ->where('email', $userInfo['email'])
                        ->data(['email' => $content])
                        ->update();
                } catch (\Throwable $e) {
                    $result = false;
                }
                if($result === 1){
                    BindingMailVerificationService::clearCompletedFlow($userId, $currentEmail, $content);
                    return message(t('user.bind_success') ,true);
                }
                return message(t('user.bind_failed').'[errorCode:UserBindingMailError]' ,false);
            case 'changeBindingQQ':
                if(empty($code)) return message(t('notify.enter_captcha') ,false);
                $result = $this->setOne(['qq' => null]);
                if($result){
                    return message('profile.qq_unbind_success', true);
                }else{
                    return message('profile.qq_unbind_failed', false);
                }
            case 'changeBindingWechatMp':
                if(empty($code)) return message(t('notify.enter_captcha') ,false);
                $result = $this->setOne(['wechat_openid' => '']);
                if($result){
                    return message('profile.wechat_unbind_success', true);
                }else{
                    return message('profile.wechat_unbind_failed', false);
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
                $oldConfig = qh_safe_unserialize_array($userInfo['config']);
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
            $upgradeCents = max(qh_money_to_cents($row['money']) - qh_money_to_cents($userPower['money']), 0);
            $allmoney = qh_money_from_cents($upgradeCents);
            if($allmoney > $userInfo['balance']) return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total', ['amount' => $allmoney]) ,false);
            $remainderBalance = qh_money_subtract($userInfo['balance'], $allmoney);
            $data = [
                "power" => $row['id']
            ];
            try {
                $result = parent::updateUserInfoAnd(
                    ['balance' => $remainderBalance],
                    t('user_action.upgrade_permission_log', ['amount' => $allmoney]),
                    static function () use ($userInfo, $userPower, $data) {
                        return self::where('id', $userInfo['id'])
                            ->where('power', $userPower['id'])
                            ->data($data)
                            ->update() === 1;
                    }
                );
                if (!$result) {
                    throw new \RuntimeException(t('user_action.upgrade_permission_transaction_failed'));
                }
                $content = [
                    'Title' => '升级权限',
                    '操作' => '升级权限',
                    '权限名称' => $row['name'],
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                Menu::clearUserCache(intval($userInfo['id']));
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
