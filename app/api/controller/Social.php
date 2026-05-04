<?php
namespace app\api\controller;

use app\common\controller\Backend;
use app\api\lib\Oauth;
use app\admin\model\Admin;
use app\user\model\User;
class Social extends Backend
{
    public function login(){
        $post = request()->post();
        $code = isset($post['code'])?$post['code']:'';
        $state = isset($post['state'])?$post['state']:'';
        $userType = isset($post['userType'])?$post['userType']:'';
        $callback = ($userType=='admin')?'admin.php/login/index.html':'user.php/login/index.html';
        if(empty($userType)){
            return message(t("validation.missing_user_type") ,false);
        }
        if(!in_array('qq', conf('login_switch'))){
            return message(t('auth.site_scan_disabled') ,false);
        }
        $Oauth = new Oauth($callback);
        if (!empty($code)) {
            if(empty($state)){
                return message(t("validation.missing_state") ,false);
            }
            if($state != session('Oauth_state')){
                return message("The state does not match. You may be a victim of CSRF." ,false);
            }
            $array = $Oauth->callback($code);

            if(isset($array['code']) && $array['code']==0){
                //$openid = $array['social_uid'];
                $access_token = $array['access_token'];
                if($userType=='admin'){
                    $adminModel = new Admin();
                    $result = $adminModel->getAccessToken($access_token);
                    if($result){
                        session('adminId', $result['id']);
                        return message("success" ,true,['url'=>'/admin.php']);
                    }else{
                        return message(t("user.qq_not_bound") ,false);
                    }
                }else {
                    $userModel = new User();
                    $result = $userModel->getAccessToken($access_token);
                    if($result){
                        session('userId', $result['id']);
                        return message("success" ,true,['url'=>'/user.php']);
                    }else{
                        return message(t("user.qq_not_bound") ,false);
                    }
                }
            }elseif(isset($array['code'])){
                return message(t("social.login_fail").$array['msg'] ,false);
            }else{
                return message(t("social.get_login_data_fail") ,false);
            }
        } else {
            $array = $Oauth->login('qq');
            if(isset($array['code']) && $array['code']==0){
                return message("success" ,true,['url'=>$array['url']]);
            }elseif(isset($array['code'])){
                return message(t("social.login_return").$array['msg'] ,false);
            }else{
                return message(t("social.get_login_url_fail") ,false);
            }
        }
    }
    public function binding(){
        $post = request()->post();
        $code = isset($post['code'])?$post['code']:'';
        $state = isset($post['state'])?$post['state']:'';
        $userType = isset($post['userType'])?$post['userType']:'';
        $callback = ($userType=='admin')?'admin.php/index/index.html':'user.php/index/index.html';
        if(empty($userType)){
            return message(t("validation.missing_user_type") ,false);
        }
        if(!in_array('qq', conf('login_switch'))){
            return message(t('auth.site_scan_disabled') ,false);
        }
        if($userType=='admin'){
            if(!session('adminId')){
                return message(t("login.not_logged_in") ,false);
            }
        }else{
            if(!session('userId')){
                return message(t("login.not_logged_in") ,false);
            }
        }
        $Oauth = new Oauth($callback);
        if (!empty($code)) {
            if(empty($state)){
                return message(t("validation.missing_state") ,false);
            }
            if($state != session('Oauth_state')){
                return message("The state does not match. You may be a victim of CSRF." ,false);
            }
            $array = $Oauth->callback($code);

            if(isset($array['code']) && $array['code']==0){
                //$openid = $array['social_uid'];
                $access_token = $array['access_token'];
                if($userType=='admin'){
                    $adminModel = new Admin();
                    $result = $adminModel->updateAccessToken($access_token);
                    if($result){
                        return message(t("user.bind_success") ,true);
                    }else{
                        return message(t("user.bind_failed") ,false);
                    }
                }else {
                    $userModel = new User();
                    $result = $userModel->updateAccessToken($access_token);
                    if($result){
                        return message(t("user.bind_success") ,true);
                    }else{
                        return message(t("user.bind_failed") ,false);
                    }
                }
            }elseif(isset($array['code'])){
                return message(t("social.login_fail").$array['msg'] ,false);
            }else{
                return message(t("social.get_login_data_fail") ,false);
            }
        } else {
            $array = $Oauth->login('qq');
            if(isset($array['code']) && $array['code']==0){
                return message("success" ,true,['url'=>$array['url']]);
            }elseif(isset($array['code'])){
                return message(t("social.login_return").$array['msg'] ,false);
            }else{
                return message(t("social.get_login_url_fail") ,false);
            }
        }
    }
}
