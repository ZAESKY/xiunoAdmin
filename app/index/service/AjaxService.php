<?php
namespace app\index\service;

use app\common\service\BaseService;
use app\common\extend\CheckInfo;
use think\facade\Cache;
use think\facade\Db;
class AjaxService extends BaseService
{

    public function appInfo()
    {
        $appid = intval(input('post.appid'));
        if(empty($appid)){
            return message("缺少APPID参数！" ,false);
        }
        if(!empty(Cache::get('appid'.$appid))){
            $appInfo = Cache::get('appid'.$appid);
        }else{
            $appInfo = Db::name('app')
                ->where([
                    'id' => $appid,
                    'status' => '2'
                ])
                ->field('id,name,logo,introduce,register_notice,app_notice')
                ->find();
            Cache::tag('SF_App')->set('appid'.$appid, $appInfo);
        }
        return message("success" ,true , $appInfo);
    }

    public function queryAuth(){
        $post = request()->post();
        $appid = !empty($post['appid'])?intval($post['appid']):null;
        $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
        $query_type = !empty($post['query_type'])?$post['query_type']:null;
        if(empty($appid)){
            return message('请选择要查询的应用！' ,false);
        }
        if(empty($auth_info)){
            return message('请输入要查询的授权内容！' ,false);
        }
        if(empty($query_type)){
            return message('请选择要查询的类型！' ,false);
        }
        
        $appInfo = parent::getAppInfo($appid);
        if(!$appInfo){
            return message('不存在此应用！' ,false);
        }
        if($appInfo['status'] == 0){
            return message('该应用已停止运营！' ,false);
        }
        if($appInfo['status'] == 1){
            return message('该应用正在维护中！' ,false);
        }

        switch($query_type){
            case 'auth':
                try{
                    $checkInfo = new CheckInfo();
                    $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
                    if($checkResult['code'] != 0){
                        return $checkResult;
                    }
                } catch (\Exception $e) {
                    return message('判断授权内容失败，请联系站长处理！' ,false);
                }

                $authInfo = Db::name('auth')
                    ->where(['auth_info' => $auth_info, 'appid' => $appid])
                    ->field('status,permanent_switch,endtime,qq')
                    ->find();
                if(!$authInfo){
                    return message('不存在此授权！' ,false ,['status' => 0]);
                }
                if($authInfo['status'] == 0){
                    return message('该授权以封禁！<br>持有者QQ:'.$authInfo['qq'] ,true ,['status' => 1]);
                }
                if($authInfo['permanent_switch'] == 1){
                    return message('正版授权！<br>持有者QQ:'.$authInfo['qq'].'<br>到期时间:永久授权' ,true ,['status' => 2]);
                }else{
                    if($authInfo['endtime']>datetime()){
                        return message('正版授权！<br>持有者QQ:'.$authInfo['qq'].'<br>到期时间:'.$authInfo['endtime'] ,true ,['status' => 2]);
                    }else{
                        return message('授权已到期！<br>持有者QQ:'.$authInfo['qq'].'<br>到期时间:'.$authInfo['endtime'] ,true ,['status' => 1]);
                    }
                }
            case 'user':
                $authInfo = Db::name('user')
                    ->where([
                        'username' => $auth_info,
                        'appid' => $appid
                    ])
                    ->field('power,status,qq')
                    ->find();
                if(!$authInfo){
                    return message('不存在此用户！' ,false ,['status' => 0]);
                }
                if($authInfo['status'] == 0){
                    return message('该用户以封禁！<br>用户QQ:'.$authInfo['qq'] ,true ,['status' => 1]);
                }
                $powerPriceInfo = Db::name('power_price')
                    ->where([
                        'id' => $authInfo['power'],
                    ])
                    ->field('name')
                    ->find();
                return message('权限名称:'.$powerPriceInfo['name'].'！<br>用户QQ:'.$authInfo['qq'] ,true ,['status' => 2]);

            case 'pay':
                $authInfo = Db::name('payment')
                    ->where([
                        'url' => $auth_info,
                        'appid' => $appid
                    ])
                    ->field('status,permanent_switch,endtime,qq')
                    ->find();
                if(!$authInfo){
                    return message('不存在此授权！' ,false ,['status' => 0]);
                }
                if($authInfo['status'] == 0){
                    return message('该授权以封禁！<br>持有者QQ:'.$authInfo['qq'] ,true ,['status' => 1]);
                }
                if($authInfo['permanent_switch'] == 1){
                    return message('正版授权！<br>持有者QQ:'.$authInfo['qq'].'<br>到期时间:永久授权' ,true ,['status' => 2]);
                }else{
                    if($authInfo['endtime']>datetime()){
                        return message('正版授权！<br>持有者QQ:'.$authInfo['qq'].'<br>到期时间:'.$authInfo['endtime'] ,true ,['status' => 2]);
                    }else{
                        return message('授权已到期！<br>持有者QQ:'.$authInfo['qq'].'<br>到期时间:'.$authInfo['endtime'] ,true ,['status' => 1]);
                    }
                }
        }

    }
}