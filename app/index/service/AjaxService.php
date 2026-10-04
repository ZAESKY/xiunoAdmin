<?php
namespace app\index\service;

use app\common\service\BaseService;
use app\common\extend\CheckInfo;
use app\common\service\RateLimitService;
use think\facade\Cache;
use think\facade\Db;
class AjaxService extends BaseService
{

    private function maskQQ($qq)
    {
        $qq = (string)$qq;
        $len = strlen($qq);
        if ($len <= 4) {
            return substr($qq, 0, 1) . str_repeat('*', $len - 1);
        }
        return substr($qq, 0, 3) . str_repeat('*', $len - 6) . substr($qq, -3);
    }

    public function appInfo()
    {
        $appid = intval(input('post.appid'));
        if(empty($appid)){
            return message(t('validation.missing_appid') ,false);
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
        if (is_array($appInfo)) {
            foreach (['register_notice', 'app_notice'] as $field) {
                $appInfo[$field] = clean_rich_text($appInfo[$field] ?? '');
            }
        }
        return message("success" ,true , $appInfo);
    }

    public function queryAuth(){
        $post = request()->post();
        $rate = RateLimitService::hit('public_auth_query', (string)get_client_ip(), 60, 3600);
        if (!$rate['ok']) {
            return message(t('auth_query.rate_limited'), false);
        }
        $appid = !empty($post['appid'])?intval($post['appid']):null;
        $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
        $query_type = !empty($post['query_type'])?$post['query_type']:null;
        if(empty($appid)){
            return message(t('auth_query.select_app') ,false);
        }
        if(empty($auth_info)){
            return message(t('auth_query.enter_content') ,false);
        }
        if(empty($query_type)){
            return message(t('auth_query.select_type') ,false);
        }
        if (!in_array($query_type, ['auth', 'user'], true)) {
            return message(t('auth_query.invalid_type'), false);
        }
        
        $appInfo = parent::getAppInfo($appid);
        if(!$appInfo){
            return message(t('app.not_exist') ,false);
        }
        if($appInfo['status'] == 0){
            return message(t('app.stopped') ,false);
        }
        if($appInfo['status'] == 1){
            return message(t('app.maintaining') ,false);
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
                    return message(t('auth.content_invalid') ,false);
                }

                $authInfo = Db::name('auth')
                    ->where(['auth_info' => $auth_info, 'appid' => $appid])
                    ->field('status,permanent_switch,endtime,qq')
                    ->find();
                if(!$authInfo){
                    return message(t('auth.not_exist') ,false ,['status' => 0]);
                }
                if($authInfo['status'] == 0){
                    return message(t('auth_query.auth_blocked', ['qq' => $this->maskQQ($authInfo['qq'])]) ,true ,['status' => 1]);
                }
                if($authInfo['permanent_switch'] == 1){
                    return message(t('auth_query.auth_permanent', ['qq' => $this->maskQQ($authInfo['qq'])]) ,true ,['status' => 2]);
                }else{
                    if($authInfo['endtime']>datetime()){
                        return message(t('auth_query.auth_valid', ['qq' => $this->maskQQ($authInfo['qq']), 'time' => $authInfo['endtime']]) ,true ,['status' => 2]);
                    }else{
                        return message(t('auth_query.auth_expired', ['qq' => $this->maskQQ($authInfo['qq']), 'time' => $authInfo['endtime']]) ,true ,['status' => 1]);
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
                    return message(t('user.not_exist') ,false ,['status' => 0]);
                }
                if($authInfo['status'] == 0){
                    return message(t('auth_query.user_blocked', ['qq' => $this->maskQQ($authInfo['qq'])]) ,true ,['status' => 1]);
                }
                $powerPriceInfo = Db::name('power_price')
                    ->where([
                        'id' => $authInfo['power'],
                    ])
                    ->field('name')
                    ->find();
                return message(t('auth_query.user_valid', ['permission' => $powerPriceInfo['name'], 'qq' => $this->maskQQ($authInfo['qq'])]) ,true ,['status' => 2]);

        }

    }
}
