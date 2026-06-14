<?php

namespace app\user\model;

use app\user\validate\Auth;
use app\common\model\BaseModel;
use app\common\extend\CheckInfo;
use think\Exception;
use think\exception\ValidateException;

/**
 * 授权-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class AuthModel
 * @package app\user\model
 */
class AuthModel extends BaseModel
{
    // 设置数据表名
    protected $name = 'auth';

    public function initialize()
    {
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
                    'Title' => '查找授权',
                    '操作' => '查找授权',
                    '授权ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $result;
            }else{
                $content = [
                    'Title' => '查找授权',
                    '操作' => '查找授权',
                    '授权ID' => $id,
                    'Result' => '[errorCode:GetAuthInfoError]'
                ];
                event('ActionLog', $content);
                return false;
            }
        }catch (\Exception $e){
            $content = [
                'Title' => '查找授权',
                '操作' => '查找授权',
                '授权ID' => $id,
                'Result' => '[errorCode:GetAuthInfoError]'
            ];
            event('ActionLog', $content);
            return false;
        }

    }

    public function editBinding(){
        $userInfo = parent::getUserInfo();
        if(!$userInfo){
            return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
        }
        $powerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message(t('user.power_info_error').'[errorCode:GetUserPowerInfoError]' ,false);
        }
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $qq = !empty($post['qq'])?intval($post['qq']):null;
        $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $permanent_switch = 0;
        if(empty($id)) return message(t('validation.missing_id') ,false);
        if(empty($appid)) return message(t('validation.missing_appid') ,false);
        if(empty($qq)) return message(t('validation.missing_qq') ,false);
        if(empty($auth_info)) return message(t('validation.missing_auth_info') ,false);
        if(empty($type)) return message(t('validation.missing_type') ,false);
        $row = self::where(['id' => $id, 'bindingid' => $userInfo['id']])->find();
        if(!$row){
            return message(t('auth.not_exist') ,false);
        }
        if($auth_info != $row['auth_info']){
            $row2 = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
            if ($row2) {
                return message(t('auth.already_exist'), false);
            }
        }
        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return message(t('app.get_info_failed').'[errorCode:GetUserAppInfoError]',false);
            }
            if($appInfo['replace_switch'] != 1){
                return message(t('app.replace_not_open'),false);
            }
            $checkInfo = new CheckInfo();
            $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
            if($checkResult['code'] != 0){
                return $checkResult;
            }
        } catch (\Exception $e) {
            return message(t('replace.auth_type_error').'[errorCode:CheckTypeError]' ,false);
        }

        if($row['qq'] == $qq && $row['auth_info'] == $auth_info){
            $allmoney = 0;
            $replace_number = $row['replace_number'];
        }else{
            if($row['replace_number'] >= $appInfo['free_replace_number']){
                $allmoney = $appInfo['replace_money'];
            }else{
                $allmoney = 0;
            }
            $replace_number = $row['replace_number'] + 1;
        }
        if($type != -1){
            try{
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if($authPriceInfo == false){
                    return message(t('auth.get_price_failed').'[errorCode:GetAuthPriceInfoError]',false);
                }
                if($row['permanent_switch'] == 1){
                    return message(t('auth.permanent_no_renew'), false);
                }
                if($authPriceInfo['diy_switch'] == 1){
                    if(empty($endtime)){
                        return message(t('auth.enter_expire_time') ,false);
                    }
                    $date = explode(' ', $endtime);
                    $time = explode(':', $date[1]);
                    $date2 = explode(' ', $row['endtime']);
                    $differDay = ceil((strtotime($date[0]) - strtotime($date2[0]))/3600/24);
                    if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                        $differDay++;
                    }
                    if($differDay <= 0){
                        return message(t('auth.correct_expire_time') ,false);
                    }else{
                        $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                        $allmoney += round(($price * $differDay) * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                    }
                }else{
                    $price = $authPriceInfo['money'];
                    $allmoney += round($price * floatval($powerPriceInfo['addauth_discount'] / 100), 2);

                    if($authPriceInfo['permanent_switch'] == 1){
                        $endtime = $row['endtime'];
                        $permanent_switch = 1;
                    }else{
                        $endtime = date('Y-m-d H:i:s',strtotime($row['endtime'].' +'.$authPriceInfo['day'].' day'));
                    }
                }
            } catch (\Exception $e) {
                return message(t('auth.get_price_failed').'[errorCode:GetAuthPriceInfoError]' ,false);
            }

        }else{
            $endtime = $row['endtime'];
            $permanent_switch = $row['permanent_switch'];
        }
        if($allmoney > $userInfo['balance']){
            return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total', ['amount' => $allmoney]),false);
        }
        $remainderBalance = $userInfo['balance'] - $allmoney;
        try{
            $result = parent::updateUserInfo(['balance' => $remainderBalance], '授权操作 -'.$allmoney.' 元');
            if(!$result){
                return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]' ,false);
            }
        } catch (\Exception $e) {
            return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]',false);
        }
        $data = [
            'auth_info' => $auth_info,
            'qq' => $qq,
            'permanent_switch' => $permanent_switch,
            'endtime' => $endtime,
            'replace_number' => $replace_number
        ];
        try{
            self::where('id', $id)
                ->data($data)
                ->update();
            $content = [
                'Title' => '更换授权',
                '操作' => '更换授权',
                '授权ID' => $id,
                '花费' => '- '.$allmoney.' 元',
                '剩余余额' => $remainderBalance.' 元',
                'Result' => 'success'
            ];
            event('ActionLog', $content);
            return message(t('replace.auth_failed').'<br> '.t('order_ui.total', ['amount' => $allmoney]).' <br> '.t('common_ui.balance_field').$remainderBalance ,true);
        } catch (\Exception $e) {
            $content = [
                'Title' => '更换授权',
                '操作' => '更换授权',
                '授权ID' => $id,
                '花费' => '- '.$allmoney.' 元',
                '剩余余额' => $remainderBalance.' 元',
                'Result' => '[errorCode:ReplaceAuthError]'
            ];
            event('ActionLog', $content);
            return message(t('replace.auth_failed').'[errorCode:ReplaceAuthError]' ,false);
        }
    }

    public function edit(){
        $userInfo = parent::getUserInfo();
        if(!$userInfo){
            return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
        }
        $powerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message(t('user.power_info_error').'[errorCode:GetUserPowerInfoError]' ,false);
        }
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $post['appid'] = $appid;
        $qq = !empty($post['qq'])?intval($post['qq']):null;
        $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
        $ip = !empty($post['ip'])?$post['ip']:'';
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $status = !empty($post['status'])?1:0;
        $permanent_switch = 0;

        try {
            validate(Auth::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }

        if(!empty($id)) {
            $row = $this->getInfo($id);
            if(!$row){
                return message(t('auth.not_exist') ,false);
            }
            if($auth_info != $row['auth_info']){
                $row2 = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
                if ($row2) {
                    return message(t('auth.already_exist'), false);
                }
            }
            try{
                $appInfo = parent::getAppInfo($appid);
                if($appInfo == false){
                    return message(t('app.get_info_failed').'[errorCode:GetUserAppInfoError]',false);
                }
                $checkInfo = new CheckInfo();
                $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
                if($checkResult['code'] != 0){
                    return $checkResult;
                }
            } catch (\Exception $e) {
                return message(t('replace.auth_type_error').'[errorCode:CheckTypeError]' ,false);
            }
            if($type != -1){
                try{
                    $authPriceInfo = parent::getAuthPriceInfo($type);
                    if($authPriceInfo == false){
                        return message(t('auth.get_price_failed').'[errorCode:GetAuthPriceInfoError]',false);
                    }
                    if($row['permanent_switch'] == 1){
                        return message(t('auth.permanent_no_renew'), false);
                    }
                    if($authPriceInfo['diy_switch'] == 1){
                        if(empty($endtime)){
                            return message(t('auth.enter_expire_time') ,false);
                        }
                        $date = explode(' ', $endtime);
                        $time = explode(':', $date[1]);
                        $date2 = explode(' ', $row['endtime']);
                        $differDay = ceil((strtotime($date[0]) - strtotime($date2[0]))/3600/24);
                        if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                            $differDay++;
                        }
                        if($differDay <= 0){
                            return message(t('auth.correct_expire_time') ,false);
                        }else{
                            $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                            $allmoney = round(($price * $differDay) * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                        }
                        if($allmoney > $userInfo['balance']){
                            return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total', ['amount' => $allmoney]) ,false);
                        }
                    }else{
                        $price = $authPriceInfo['money'];
                        $allmoney = round($price * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                        if($allmoney > $userInfo['balance']){
                            return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total', ['amount' => $allmoney]),false);
                        }
                        if($authPriceInfo['permanent_switch'] == 1){
                            $endtime = $row['endtime'];
                            $permanent_switch = 1;
                        }else{
                            $endtime = date('Y-m-d H:i:s',strtotime($row['endtime'].' +'.$authPriceInfo['day'].' day'));
                        }
                    }
                } catch (\Exception $e) {
                    return message(t('auth.get_price_failed').'[errorCode:GetAuthPriceInfoError]' ,false);
                }
                $remainderBalance = $userInfo['balance'] - $allmoney;
                try{
                    $result = parent::updateUserInfo(['balance' => $remainderBalance], '授权操作 -'.$allmoney.' 元');
                    if(!$result){
                        return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]' ,false);
                    }
                } catch (\Exception $e) {
                    return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]',false);
                }
            }else{
                $allmoney = 0;
                $remainderBalance = $userInfo['balance'];
                $endtime = $row['endtime'];
                $permanent_switch = $row['permanent_switch'];
            }
            $data = [
                'auth_info' => $auth_info,
                'qq' => $qq,
                'ip' => $ip,
                'permanent_switch' => $permanent_switch,
                'endtime' => $endtime,
                'status' => $status,
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                $content = [
                    'Title' => '编辑授权',
                    '操作' => '编辑授权',
                    '授权ID' => $id,
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return message(t('user.edit_success').'<br> '.t('order_ui.total', ['amount' => $allmoney]).' <br> '.t('common_ui.balance_field').$remainderBalance ,true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '编辑授权',
                    '操作' => '编辑授权',
                    '授权ID' => $id,
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => '[errorCode:EditAuthError]'
                ];
                event('ActionLog', $content);
                return message(t('user.edit_failed').'[errorCode:EditAuthError]' ,false);
            }
        }else{
            $row = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
            if ($row) {
                return message(t('auth.already_exist'), false);
            }
            try{
                $appInfo = parent::getAppInfo($appid);
                if($appInfo == false){
                    return message(t('app.get_info_failed').'[errorCode:GetUserAppInfoError]',false);
                }
                $checkInfo = new CheckInfo();
                $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
                if($checkResult['code'] != 0){
                    return $checkResult;
                }
            } catch (\Exception $e) {
                return message(t('replace.auth_type_error').'[errorCode:CheckTypeError]' ,false);
            }
            try{
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if($authPriceInfo == false){
                    return message(t('auth.get_price_failed').'[errorCode:GetAuthPriceInfoError]',false);
                }
                if($authPriceInfo['diy_switch'] == 1){
                    if(empty($endtime)){
                        return message(t('auth.enter_expire_time') ,false);
                    }
                    $date = explode(' ', $endtime);
                    $time = explode(':', $date[1]);
                    $differDay = ceil((strtotime($date[0]) - strtotime(date('Y-m-d')))/3600/24);
                    if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                        $differDay++;
                    }
                    if($differDay <= 0){
                        return message(t('auth.correct_expire_time') ,false);
                    }else{
                        $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                        $allmoney = round(($price * $differDay) * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                    }
                    if($allmoney > $userInfo['balance']){
                        return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total', ['amount' => $allmoney]) ,false);
                    }
                }else{
                    $price = $authPriceInfo['money'];
                    $allmoney = round($price * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                    if($allmoney > $userInfo['balance']){
                        return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total', ['amount' => $allmoney]),false);
                    }
                    if($authPriceInfo['permanent_switch'] == 1){
                        $endtime = datetime();
                        $permanent_switch = 1;
                    }else{
                        $endtime = date('Y-m-d H:i:s',strtotime(' +'.$authPriceInfo['day'].' day'));
                    }
                }
            } catch (\Exception $e) {
                return message(t('auth.get_price_failed').'[errorCode:GetAuthPriceInfoError]' ,false);
            }
            $remainderBalance = $userInfo['balance'] - $allmoney;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance], '授权操作 -'.$allmoney.' 元');
                if(!$result){
                    return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]' ,false);
                }
            } catch (\Exception $e) {
                return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]',false);
            }

            $row = self::where('qq', $qq)->field('sign,authcode')->find();
            if(empty($row)){
                $row = self::order('sign','desc')->where('qq', $qq)->field('sign')->find();
                if(empty($row)){
                    $sign = 1;
                }else{
                    $sign = $row['sign'] + 1;
                }
                $authcode = md5(time().$qq.'SF');
            }else{
                $sign = $row['sign'];
                $authcode = $row['authcode'];
            }
            $data = [
                'auth_info' => $auth_info,
                'qq' => $qq,
                'ip' => $ip,
                'authcode' => $authcode,
                'sign' => $sign,
                'replace_number' => 0,
                'permanent_switch' => $permanent_switch,
                'addtime' => datetime(),
                'endtime' => $endtime,
                'status' => $status,
                'appid' => $appid,
                'userid' => $userInfo['id'],
                'bindingid' => 0
            ];
            try{
                self::insert($data);
                $content = [
                    'Title' => '添加授权',
                    '操作' => '添加授权',
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return message(t('user.add_success').'<br> '.t('order_ui.total', ['amount' => $allmoney]).' <br> '.t('common_ui.balance_field').$remainderBalance ,true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '添加授权',
                    '操作' => '添加授权',
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => '[errorCode:AddAuthError]'
                ];
                event('ActionLog', $content);
                return message(t('user.add_failed').'[errorCode:AddAuthError]' ,false);
            }
        }
    }

    public function unbind($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            try{
                $userInfo = parent::getUserInfo();
                if(!$userInfo){
                    return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
                }
            }catch (\Exception $e){
                throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
            }
            $row = self::where(['id' => $id, 'bindingid' => $userInfo['id']])->find();
            if(!$row){
                throw new Exception(t('auth.not_exist'));
            }
            self::where('id', $id)
                ->data([
                    'bindingid' => 0
                ])
                ->update();
            $content = [
                'Title' => '取绑授权',
                '操作' => '取绑授权',
                '授权ID' => $id,
                'Result' => 'success'
            ];
            event('ActionLog', $content);
            return true;
        } catch (\Exception $e) {
            $content = [
                'Title' => '取绑授权',
                '操作' => '取绑授权',
                '授权ID' => $id,
                'Result' => '[errorCode:UnbindAuthError]'
            ];
            event('ActionLog', $content);
            throw new Exception(t('user.delete_failed').'[errorCode:UnbindAuthError]');
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('auth.not_exist'));
            }

            self::where('id', $id)->delete();
            $content = [
                'Title' => '删除授权',
                '操作' => '删除授权',
                '授权ID' => $id,
                'Result' => 'success'
            ];
            event('ActionLog', $content);
            return true;
        } catch (\Exception $e) {
            $content = [
                'Title' => '删除授权',
                '操作' => '删除授权',
                '授权ID' => $id,
                'Result' => '[errorCode:DeleteAuthError]'
            ];
            event('ActionLog', $content);
            throw new Exception(t('user.delete_failed').'[errorCode:DeleteAuthError]');
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
                throw new Exception(t('auth.not_exist'));
            }
            try{
                self::where('id', $id)
                    ->data(['status' => $status])
                    ->update();
                $content = [
                    'Title' => '编辑授权',
                    '操作' => '修改授权状态',
                    '授权ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return true;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '编辑授权',
                    '操作' => '修改授权状态',
                    '授权ID' => $id,
                    'Result' => '[errorCode:EditAuthStatusError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.status_change_success').'[errorCode:EditAuthStatusError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.status_change_success').'[errorCode:EditAuthStatusError]');
        }
    }

    public function myList(){
        try{
            $userModel = new \app\user\model\User();
            $userInfo = $userModel->getInfo();
            if(!$userInfo){
                return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
            }
        }catch (\Exception $e){
            throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
        }
        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $data = $this->buildSearchWhere('id|auth_info|qq');
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }else{
                throw new Exception(t('user.info_error').'[errorCode:UserAppIdEmpty]');
            }
            $data[] = ['bindingid', '=', $userInfo['id']];
            try{
                $list = self::order('id' ,'desc')->where($data)->paginate([
                    'list_rows'=> $limit,
                    'page' => $current_page,
                ]);
                $content = [
                    'Title' => '我的授权',
                    '操作' => '获取用户绑定的授权列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '我的授权',
                    '操作' => '获取用户绑定的授权列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetMyAuthListError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.list_failed').'[errorCode:GetMyAuthListError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.list_failed').'[errorCode:GetMyAuthListError]');
        }
    }

    public function list(){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
            }
        }catch (\Exception $e){
            throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
        }

        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $data = $this->buildSearchWhere('id|auth_info|qq');
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }else{
                throw new Exception(t('user.info_error').'[errorCode:UserAppIdEmpty]');
            }
            $data[] = ['userid', '=', $userInfo['id']];
            try{
                $list = self::order('id' ,'desc')->where($data)->paginate([
                    'list_rows'=> $limit,
                    'page' => $current_page,
                ]);
                $content = [
                    'Title' => '授权列表',
                    '操作' => '获取授权列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '授权列表',
                    '操作' => '获取授权列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetAuthListError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.list_failed').'[errorCode:GetAuthListError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.list_failed').'[errorCode:GetAuthListError]');
        }
    }
}
