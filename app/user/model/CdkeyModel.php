<?php

namespace app\user\model;

use app\common\extend\CheckInfo;
use app\user\validate\Cdkey;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;
/**
 * 卡密-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class CdkeyModel
 * @package app\user\model
 */
class CdkeyModel extends BaseModel
{
    // 设置数据表名
    protected $name = 'cdkey';

    public function getInfo($id){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return false;
            }
            $result = self::where(['id' => $id, 'userid' => $userInfo['id']])->find();
            if($result){
                $content = [
                    'Title' => '查找卡密',
                    '操作' => '查找卡密',
                    '卡密ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $result;
            }else{
                $content = [
                    'Title' => '查找卡密',
                    '操作' => '查找卡密',
                    '卡密ID' => $id,
                    'Result' => '[errorCode:GetCDKEYInfoError]'
                ];
                event('ActionLog', $content);
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    public function exchange(){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                throw new Exception('获取用户信息失败！[errorCode:UserInfoError]');
            }
        }catch (\Exception $e){
            throw new Exception('获取用户信息失败！[errorCode:UserInfoError]');
        }
        $post = request()->post();
        $cdkey = !empty($post['cdkey'])?$post['cdkey']:null;
        $step = !empty($post['step'])?intval($post['step']):0;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $cdkey_type = !empty($post['cdkey_type'])?$post['cdkey_type']:null;
        if(empty($cdkey)){
            throw new Exception('请填写卡密内容！');
        }
        if(empty($appid)){
            throw new Exception('获取用户信息失败！[errorCode:UserAppIdEmpty]');
        }
        $row = self::where(['cdkey' => $cdkey, 'appid' => $appid])->field('cdkey_type,info,status')->find();
        if(empty($row)){
            throw new Exception('此卡密不存在！');
        }
        if($row['status'] != 0){
            throw new Exception('此卡密已使用！');
        }
        switch ($step){
            case 1:
                switch ($cdkey_type){
                    case 'auth':
                        $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
                        $qq = !empty($post['qq'])?intval($post['qq']):null;
                        $info = json_decode($row['info'],true);
                        if(empty($auth_info)){
                            throw new Exception('请输入授权内容！');
                        }
                        if(empty($qq)){
                            throw new Exception('请输入授权者QQ！');
                        }
                        $authRow = Db::name('auth')->where(['auth_info' => $auth_info, 'appid' => $appid])->find();
                        if ($authRow) {
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
                        $result = Db::name('auth')->where('qq', $qq)->field('sign,authcode')->find();
                        if(empty($result)){
                            $result = Db::name('auth')->order('sign','desc')->where('qq', $qq)->field('sign')->find();
                            if(empty($result)){
                                $sign = 1;
                            }else{
                                $sign = $result['sign'] + 1;
                            }
                            $authcode = md5(time().$qq.'SF');
                        }else{
                            $sign = $result['sign'];
                            $authcode = $result['authcode'];
                        }
                        $data = [
                            'auth_info' => $auth_info,
                            'qq' => $qq,
                            'authcode' => $authcode,
                            'sign' => $sign,
                            'replace_number' => 0,
                            'permanent_switch' => intval($info['permanent_switch']),
                            'addtime' => datetime(),
                            'endtime' => $info['endtime']??datetime(),
                            'status' => $info['auth_status']??0,
                            'appid' => $appid,
                            'userid' => $userInfo['id'],
                            'bindingid' => $userInfo['id']
                        ];
                        Db::startTrans();
                        try {
                            Db::name('auth')
                                ->insert($data);
                            Db::name('cdkey')
                                ->where('cdkey', $cdkey)
                                ->data([
                                    'status' => 1,
                                    'usetime' => datetime()
                                ])
                                ->update();
                            // 提交事务
                            Db::commit();
                            $content = [
                                'Title' => '兑换授权',
                                '操作' => '兑换授权',
                                '授权' => $auth_info,
                                'Result' => 'success'
                            ];
                            event('ActionLog', $content);
                            return message('兑换授权成功！', true);
                        } catch (\Exception $e) {
                            // 回滚事务
                            Db::rollback();
                            $content = [
                                'Title' => '兑换授权',
                                '操作' => '兑换授权',
                                '授权' => $auth_info,
                                'Result' => $e->getMessage()
                            ];
                            event('ActionLog', $content);
                            return message('兑换授权失败！'.$e->getMessage(), false);
                        }
                    default:
                        throw new Exception('不存在此卡密类型！');
                }
            default:
                switch ($row['cdkey_type']){
                    case 'auth':
                        return message('获取卡密类型成功，请继续填写信息！', true, $row['cdkey_type']);
                    case 'user':
                        $info = json_decode($row['info'],true);
                        if(empty($info['power']) || !is_numeric($info['power'])){
                            return message('获取卡密信息错误，请联系上级更换！', false);
                        }
                        $power = intval($info['power']);
                        $powerPriceModel = new \app\admin\model\PowerPriceModel();
                        $result = $powerPriceModel->getUserUpdatePowerList($userInfo['power'], true);
                        $cdkeyPower = $powerPriceModel->getInfo($power);
                        $userPower = $powerPriceModel->getInfo($userInfo['power']);
                        if(!in_array($power,$result)){
                            return message('此权限您无权限升级!<br>兑换权限：'.$cdkeyPower['name'].'<br>当前权限：'.$userPower['name'] ,false, $result);
                        }
                        Db::startTrans();
                        try {
                            Db::name('user')
                                ->where('id', $userInfo['id'])
                                ->data([
                                    'power' => $power,
                                    'status' => intval($info['user_status'])
                                ])
                                ->update();
                            Db::name('cdkey')
                                ->where('cdkey', $cdkey)
                                ->data([
                                    'status' => 1,
                                    'usetime' => datetime()
                                ])
                                ->update();
                            // 提交事务
                            Db::commit();
                            return message('兑换权限成功！', true);
                        } catch (\Exception $e) {
                            // 回滚事务
                            Db::rollback();
                            return message('兑换权限失败！'.$e->getMessage(), true);
                        }
                    case 'balance':
                        $info = json_decode($row['info'],true);
                        Db::startTrans();
                        try {
                            Db::name('user')
                                ->where('id', $userInfo['id'])
                                ->inc('balance', $info['balance'])
                                ->update();
                            Db::name('cdkey')
                                ->where('cdkey', $cdkey)
                                ->data([
                                    'status' => 1,
                                    'usetime' => datetime()
                                ])
                                ->update();
                            // 提交事务
                            Db::commit();
                            return message('兑换余额成功！', true);
                        } catch (\Exception $e) {
                            // 回滚事务
                            Db::rollback();
                            return message('兑换余额失败！'.$e->getMessage(), true);
                        }
                    case 'integral':
                        $info = json_decode($row['info'],true);
                        Db::name('user')
                            ->where('id', $userInfo['id'])
                            ->inc('integral', $info['integral'])
                            ->update();
                        Db::name('cdkey')
                            ->where('cdkey', $cdkey)
                            ->data([
                                'status' => 1,
                                'usetime' => datetime()
                            ])
                            ->update();
                        return message('兑换余额成功！', true);
                    default:
                        throw new Exception('该卡密类型不存在，请联系上级更换！');
                }
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
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $post['appid'] = $appid;
        $cdkey_type = !empty($post['cdkey_type'])?$post['cdkey_type']:null;
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $permanent_switch = 0;
        $auth_status = !empty($post['auth_status'])?intval($post['auth_status']):1;
        $power = !empty($post['power'])?intval($post['power']):null;
        $user_status = !empty($post['user_status'])?intval($post['user_status']):1;
        $number = !empty($post['number'])?intval($post['number']):0;

        try {
            validate(Cdkey::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        $info = array();

        if($number <= 0){
            return message('生成的数量不能小于等于0！' ,false);
        }
        if($number > conf('create_cdkey_max_number')){
            return message('生成的数量不能大于'.conf('cdkey_max_number').'！' ,false);
        }
        $userCdkeyCount = self::where(['userid' => $userInfo['id'], 'status' => 0])->count('id');
        if(($userCdkeyCount + $number) > conf('have_cdkey_max_number')){
            return message('您当前未使用卡密无法超过'.conf('have_cdkey_max_number').'张，无法继续生成卡密！' ,false);
        }
        switch ($cdkey_type){
            case 'auth':
                $cdkey_type_name = '授权';
                if(empty($type)){
                    return message('请选择授权时间！' ,false);
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
                            $allmoney = ($price * $differDay * $number) * floatval($powerPriceInfo['addauth_discount'] / 100);
                        }
                        if($allmoney > $userInfo['balance']){
                            return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元' ,false);
                        }
                    }else{
                        $price = $authPriceInfo['money'];
                        $allmoney = ($price * $number) * floatval($powerPriceInfo['addauth_discount'] / 100);
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
                $info['permanent_switch'] = $permanent_switch;
                $info['type'] = $type;
                $info['endtime'] = $endtime;
                $info['auth_status'] = $auth_status;
                break;
            case 'user':
                $cdkey_type_name = '权限';
                try{
                    $powerInfo = parent::getPowerPriceInfo($power);
                    if($powerInfo == false){
                        return message('获取权限信息失败！[errorCode:GetPowerInfoError]' ,false);
                    }
                    $price = $powerInfo['money'];
                    $allmoney = ($price * $number) * floatval($powerPriceInfo['adduser_discount'] / 100);
                    if($allmoney > $userInfo['balance']){
                        return message('您的余额不足，请充值后再来！<br> 您的余额：'.$userInfo['balance'].' 元<br>需要金额：'.$allmoney.' 元',false);
                    }
                    $info['power'] = $power;
                    $info['user_status'] = $user_status;
                } catch (\Exception $e) {
                    return message('获取权限信息失败！[errorCode:GetPowerInfoError]',false);
                }
                break;
            default:
                return message('卡密类型错误！[errorCode:CDKEYTypeError]',false);
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
        for($i=0;$i<$number;$i++){
            $cdkey = (conf('cdkey_head')??'SF').'_'.get_random_str(20,5);
            $data = [
                'cdkey' => $cdkey,
                'cdkey_type' => $cdkey_type,
                'info' => json_encode($info),
                'addtime' => datetime(),
                'status' => 0,
                'appid' => $appid,
                'userid' => $userInfo['id'],
            ];
            try{
                self::insert($data);
                $content = [
                    'Title' => '添加卡密',
                    '操作' => '添加卡密',
                    '生成类型' => $cdkey_type_name,
                    '生成数量' => $number,
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '添加卡密',
                    '操作' => '添加卡密',
                    '生成类型' => $cdkey_type_name,
                    '生成数量' => $number,
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => '[errorCode:AddCDKEYError]'
                ];
                event('ActionLog', $content);
                return message('共'.$number.'张卡密，成功生成 '.$i.' 张卡密！[errorCode:AddCDKEYError]' ,false);
            }
        }
        return message('成功生成 '.$i.' 张卡密！' ,true);
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此卡密！');
            }
            try{
                self::where('id', $id)->delete();
                $content = [
                    'Title' => '删除卡密',
                    '操作' => '删除卡密',
                    '卡密ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return true;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '删除卡密',
                    '操作' => '删除卡密',
                    '卡密ID' => $id,
                    'Result' => '[errorCode:DeleteCDKEYError]'
                ];
                event('ActionLog', $content);
                throw new Exception('删除失败！[errorCode:DeleteCDKEYError]');
            }
        }catch (\Exception $e){
            throw new Exception('删除失败！[errorCode:DeleteCDKEYError]');
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
            $text = !empty($post['text'])?$post['text']:'';
            $status = isset($post['status'])?intval($post['status']):null;
            $data = [];

            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
                $order = 'id';
            }else{
                throw new Exception('获取用户信息失败！[errorCode:UserAppIdEmpty]');
            }

            if(!empty($text)){
                $data[] = ['id|cdkey', 'like', '%'.$text.'%'];
            }

            if($status !== null){
                $data[] = ['status', '=', $status];
            }

            $data[] = ['userid', '=', $userInfo['id']];
            try{
                $list = self::order($order ,'desc')->where($data)->paginate([
                    'list_rows'=> $limit,
                    'page' => $current_page,
                ]);
                $content = [
                    'Title' => '卡密列表',
                    '操作' => '获取卡密列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '卡密列表',
                    '操作' => '获取卡密列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetCDKEYListError]'
                ];
                event('ActionLog', $content);
                throw new Exception('获取列表失败！[errorCode:GetCDKEYListError]');
            }
        }catch (\Exception $e) {
            throw new Exception('获取列表失败！[errorCode:GetCDKEYListError]');
        }
    }
}