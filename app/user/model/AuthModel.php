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
            return message('获取用户信息失败！[errorCode:UserInfoError]' ,false);
        }
        $powerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message('获取用户权限信息失败！[errorCode:GetUserPowerInfoError]' ,false);
        }
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $qq = !empty($post['qq'])?intval($post['qq']):null;
        $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $permanent_switch = 0;
        if(empty($id)) return message('缺少ID参数！' ,false);
        if(empty($appid)) return message('缺少APPID参数！' ,false);
        if(empty($qq)) return message('缺少QQ参数！' ,false);
        if(empty($auth_info)) return message('缺少AUTH_INFO参数！' ,false);
        if(empty($type)) return message('缺少TYPE参数！' ,false);
        $row = self::where(['id' => $id, 'bindingid' => $userInfo['id']])->find();
        if(!$row){
            return message('不存在此授权！' ,false);
        }
        if($auth_info != $row['auth_info']){
            $row2 = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
            if ($row2) {
                return message('该应用已存在此授权！', false);
            }
        }
        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return message('获取应用信息失败！[errorCode:GetUserAppInfoError]',false);
            }
            if($appInfo['replace_switch'] != 1){
                return message('当前应用未开启更换授权功能',false);
            }
            $checkInfo = new CheckInfo();
            $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
            if($checkResult['code'] != 0){
                return $checkResult;
            }
        } catch (\Exception $e) {
            return message('检测授权格式失败！[errorCode:CheckTypeError]' ,false);
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
                    return message('获取价格信息失败！[errorCode:GetAuthPriceInfoError]',false);
                }
                if($row['permanent_switch'] == 1){
                    return message('该授权已是永久授权，无法进行续费！', false);
                }
                if($authPriceInfo['diy_switch'] == 1){
                    if(empty($endtime)){
                        return message('请输入到期时间！' ,false);
                    }
                    $date = explode(' ', $endtime);
                    $time = explode(':', $date[1]);
                    $date2 = explode(' ', $row['endtime']);
                    $differDay = ceil((strtotime($date[0]) - strtotime($date2[0]))/3600/24);
                    if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                        $differDay++;
                    }
                    if($differDay <= 0){
                        return message('请填写正确到期时间！' ,false);
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
                return message('获取价格信息失败！[errorCode:GetAuthPriceInfoError]' ,false);
            }

        }else{
            $endtime = $row['endtime'];
            $permanent_switch = $row['permanent_switch'];
        }
        if($allmoney > $userInfo['balance']){
            return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元',false);
        }
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
            return message('更换成功！<br> 花费：'.$allmoney.' 元 <br> 余额：'.$remainderBalance.' 元' ,true);
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
            return message('更换失败！[errorCode:ReplaceAuthError]' ,false);
        }
    }

    public function edit(){
        $userInfo = parent::getUserInfo();
        if(!$userInfo){
            return message('获取用户信息失败！[errorCode:UserInfoError]' ,false);
        }
        $powerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message('获取用户权限信息失败！[errorCode:GetUserPowerInfoError]' ,false);
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
                return message('不存在此授权！' ,false);
            }
            if($auth_info != $row['auth_info']){
                $row2 = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
                if ($row2) {
                    return message('该应用已存在此授权！', false);
                }
            }
            try{
                $appInfo = parent::getAppInfo($appid);
                if($appInfo == false){
                    return message('获取应用信息失败！[errorCode:GetUserAppInfoError]',false);
                }
                $checkInfo = new CheckInfo();
                $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
                if($checkResult['code'] != 0){
                    return $checkResult;
                }
            } catch (\Exception $e) {
                return message('检测授权格式失败！[errorCode:CheckTypeError]' ,false);
            }
            if($type != -1){
                try{
                    $authPriceInfo = parent::getAuthPriceInfo($type);
                    if($authPriceInfo == false){
                        return message('获取价格信息失败！[errorCode:GetAuthPriceInfoError]',false);
                    }
                    if($row['permanent_switch'] == 1){
                        return message('该授权已是永久授权，无法进行续费！', false);
                    }
                    if($authPriceInfo['diy_switch'] == 1){
                        if(empty($endtime)){
                            return message('请输入到期时间！' ,false);
                        }
                        $date = explode(' ', $endtime);
                        $time = explode(':', $date[1]);
                        $date2 = explode(' ', $row['endtime']);
                        $differDay = ceil((strtotime($date[0]) - strtotime($date2[0]))/3600/24);
                        if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                            $differDay++;
                        }
                        if($differDay <= 0){
                            return message('请填写正确到期时间！' ,false);
                        }else{
                            $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                            $allmoney = round(($price * $differDay) * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                        }
                        if($allmoney > $userInfo['balance']){
                            return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元' ,false);
                        }
                    }else{
                        $price = $authPriceInfo['money'];
                        $allmoney = round($price * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                        if($allmoney > $userInfo['balance']){
                            return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元',false);
                        }
                        if($authPriceInfo['permanent_switch'] == 1){
                            $endtime = $row['endtime'];
                            $permanent_switch = 1;
                        }else{
                            $endtime = date('Y-m-d H:i:s',strtotime($row['endtime'].' +'.$authPriceInfo['day'].' day'));
                        }
                    }
                } catch (\Exception $e) {
                    return message('获取价格信息失败！[errorCode:GetAuthPriceInfoError]' ,false);
                }
                $remainderBalance = $userInfo['balance'] - $allmoney;
                try{
                    $result = parent::updateUserInfo(['balance' => $remainderBalance]);
                    if(!$result){
                        return message('更新用户信息失败！[errorCode:ReduceUserBalanceError]' ,false);
                    }
                } catch (\Exception $e) {
                    return message('更新用户信息失败！[errorCode:ReduceUserBalanceError]',false);
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
                return message('编辑成功！<br> 花费：'.$allmoney.' 元 <br> 余额：'.$remainderBalance.' 元' ,true);
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
                return message('编辑失败！[errorCode:EditAuthError]' ,false);
            }
        }else{
            $row = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
            if ($row) {
                return message('该应用已存在此授权！', false);
            }
            try{
                $appInfo = parent::getAppInfo($appid);
                if($appInfo == false){
                    return message('获取应用信息失败！[errorCode:GetUserAppInfoError]',false);
                }
                $checkInfo = new CheckInfo();
                $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
                if($checkResult['code'] != 0){
                    return $checkResult;
                }
            } catch (\Exception $e) {
                return message('检测授权格式失败！[errorCode:CheckTypeError]' ,false);
            }
            try{
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if($authPriceInfo == false){
                    return message('获取价格信息失败！[errorCode:GetAuthPriceInfoError]',false);
                }
                if($authPriceInfo['diy_switch'] == 1){
                    if(empty($endtime)){
                        return message('请输入到期时间！' ,false);
                    }
                    $date = explode(' ', $endtime);
                    $time = explode(':', $date[1]);
                    $differDay = ceil((strtotime($date[0]) - strtotime(date('Y-m-d')))/3600/24);
                    if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                        $differDay++;
                    }
                    if($differDay <= 0){
                        return message('请填写正确到期时间！' ,false);
                    }else{
                        $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                        $allmoney = round(($price * $differDay) * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                    }
                    if($allmoney > $userInfo['balance']){
                        return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元' ,false);
                    }
                }else{
                    $price = $authPriceInfo['money'];
                    $allmoney = round($price * floatval($powerPriceInfo['addauth_discount'] / 100), 2);
                    if($allmoney > $userInfo['balance']){
                        return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元',false);
                    }
                    if($authPriceInfo['permanent_switch'] == 1){
                        $endtime = datetime();
                        $permanent_switch = 1;
                    }else{
                        $endtime = date('Y-m-d H:i:s',strtotime(' +'.$authPriceInfo['day'].' day'));
                    }
                }
            } catch (\Exception $e) {
                return message('获取价格信息失败！[errorCode:GetAuthPriceInfoError]' ,false);
            }
            $remainderBalance = $userInfo['balance'] - $allmoney;
            try{
                $result = parent::updateUserInfo(['balance' => $remainderBalance]);
                if(!$result){
                    return message('更新用户信息失败！[errorCode:ReduceUserBalanceError]' ,false);
                }
            } catch (\Exception $e) {
                return message('更新用户信息失败！[errorCode:ReduceUserBalanceError]',false);
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
                return message('添加成功！<br> 花费：'.$allmoney.' 元 <br> 余额：'.$remainderBalance.' 元' ,true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '添加授权',
                    '操作' => '添加授权',
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => '[errorCode:AddAuthError]'
                ];
                event('ActionLog', $content);
                return message('添加失败！[errorCode:AddAuthError]' ,false);
            }
        }
    }

    public function unbind($id){
        try{
            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            try{
                $userInfo = parent::getUserInfo();
                if(!$userInfo){
                    return message('获取用户信息失败！[errorCode:UserInfoError]' ,false);
                }
            }catch (\Exception $e){
                throw new Exception('获取用户信息失败！[errorCode:UserInfoError]');
            }
            $row = self::where(['id' => $id, 'bindingid' => $userInfo['id']])->find();
            if(!$row){
                throw new Exception('不存在此授权！');
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
            throw new Exception('删除失败！[errorCode:UnbindAuthError]');
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此授权！');
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
            throw new Exception('删除失败！[errorCode:DeleteAuthError]');
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
                throw new Exception('不存在此授权！');
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
                throw new Exception('修改状态失败！[errorCode:EditAuthStatusError]');
            }
        }catch (\Exception $e){
            throw new Exception('修改状态失败！[errorCode:EditAuthStatusError]');
        }
    }

    public function myList(){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return message('获取用户信息失败！[errorCode:UserInfoError]' ,false);
            }
        }catch (\Exception $e){
            throw new Exception('获取用户信息失败！[errorCode:UserInfoError]');
        }
        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']):null;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $data = [];
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }else{
                throw new Exception('获取用户信息失败！[errorCode:UserAppIdEmpty]');
            }
            if($text !== null){
                $data[] = ['id|auth_info|qq', 'like', '%'.$text.'%'];
            }
            if($status !== null){
                $data[] = ['status', '=', $status];
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
                throw new Exception('获取列表失败！[errorCode:GetMyAuthListError]');
            }
        }catch (\Exception $e){
            throw new Exception('获取列表失败！[errorCode:GetMyAuthListError]');
        }
    }

    public function list(){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return message('获取用户信息失败！[errorCode:UserInfoError]' ,false);
            }
        }catch (\Exception $e){
            throw new Exception('获取用户信息失败！[errorCode:UserInfoError]');
        }

        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']):null;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $data = [];
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }else{
                throw new Exception('获取用户信息失败！[errorCode:UserAppIdEmpty]');
            }
            if($text !== null){
                $data[] = ['id|auth_info|qq', 'like', '%'.$text.'%'];
            }
            if($status !== null){
                $data[] = ['status', '=', $status];
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
                throw new Exception('获取列表失败！[errorCode:GetAuthListError]');
            }
        }catch (\Exception $e){
            throw new Exception('获取列表失败！[errorCode:GetAuthListError]');
        }
    }
}