<?php
namespace app\install\controller;

use app\common\controller\Install;
use app\api\lib\Oauth;
use think\facade\Db;
class Social extends Install
{
    public function binding(){
        $get = request()->get();
        $code = isset($get['code'])?$get['code']:'';
        $state = isset($get['state'])?$get['state']:'';
        $callback = 'install.php/Social/binding';

        $Oauth = new Oauth($callback);
        if (!empty($code)) {
            if(empty($state)){
                sysmsg("缺少STATE参数！", 0);
            }
            if($state != session('Oauth_state')){
                sysmsg("The state does not match. You may be a victim of CSRF.", 0);
            }
            $array = $Oauth->callback($code);

            if(isset($array['code']) && $array['code']==0){
                //$openid = $array['social_uid'];
                $access_token = $array['access_token'];
                $result = Db::name('admin')
                    ->where('id',1)
                    ->data(['access_token' => $access_token])
                    ->update();
                if($result){
                    sysmsg("绑定成功,请返回原界面继续操作！", 1);
                }else{
                    sysmsg("绑定失败,请勿使用同一QQ绑定！", 0);
                }
            }elseif(isset($array['code'])){
                sysmsg("登录失败，返回错误原因：".$array['msg'], 0);
            }else{
                sysmsg("获取登录数据失败", 0);
            }
        } else {
            $array = $Oauth->login('qq');
            if(isset($array['code']) && $array['code']==0){
                header('Location: '.$array['url']);
            }elseif(isset($array['code'])){
                sysmsg("登录接口返回：".$array['msg'], 0);
            }else{
                sysmsg("获取登录地址失败", 0);
            }
        }
    }
}