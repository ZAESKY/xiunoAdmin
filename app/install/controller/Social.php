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
                sysmsg(t("validation.missing_state"), 0);
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
                    sysmsg(t("social.bind_success_reopen"), 1);
                }else{
                    sysmsg(t("social.bind_fail_same_qq"), 0);
                }
            }elseif(isset($array['code'])){
                sysmsg(t("social.login_fail").$array['msg'], 0);
            }else{
                sysmsg(t("social.get_login_data_fail"), 0);
            }
        } else {
            $array = $Oauth->login('qq');
            if(isset($array['code']) && $array['code']==0){
                header('Location: '.$array['url']);
            }elseif(isset($array['code'])){
                sysmsg(t("social.login_return").$array['msg'], 0);
            }else{
                sysmsg(t("social.get_login_url_fail"), 0);
            }
        }
    }
}
