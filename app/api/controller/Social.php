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
            return message("缺少用户类型参数！" ,false);
        }
        $Oauth = new Oauth($callback);
        if (!empty($code)) {
            if(empty($state)){
                return message("缺少STATE参数！" ,false);
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
                        return message("该QQ未绑定任何账户！" ,false);
                    }
                }else {
                    $userModel = new User();
                    $result = $userModel->getAccessToken($access_token);
                    if($result){
                        session('userId', $result['id']);
                        return message("success" ,true,['url'=>'/user.php']);
                    }else{
                        return message("该QQ未绑定任何账户！" ,false);
                    }
                }
            }elseif(isset($array['code'])){
                return message("登录失败，返回错误原因：".$array['msg'] ,false);
            }else{
                return message("获取登录数据失败" ,false);
            }
        } else {
            $array = $Oauth->login('qq');
            if(isset($array['code']) && $array['code']==0){
                return message("success" ,true,['url'=>$array['url']]);
            }elseif(isset($array['code'])){
                return message("登录接口返回：".$array['msg'] ,false);
            }else{
                return message("获取登录地址失败" ,false);
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
            return message("缺少用户类型参数！" ,false);
        }
        if($userType=='admin'){
            if(!session('adminId')){
                return message("未登录！" ,false);
            }
        }else{
            if(!session('userId')){
                return message("未登录！" ,false);
            }
        }
        $Oauth = new Oauth($callback);
        if (!empty($code)) {
            if(empty($state)){
                return message("缺少STATE参数！" ,false);
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
                        return message("绑定成功！" ,true);
                    }else{
                        return message("绑定失败！" ,false);
                    }
                }else {
                    $userModel = new User();
                    $result = $userModel->updateAccessToken($access_token);
                    if($result){
                        return message("绑定成功！" ,true);
                    }else{
                        return message("绑定失败！" ,false);
                    }
                }
            }elseif(isset($array['code'])){
                return message("登录失败，返回错误原因：".$array['msg'] ,false);
            }else{
                return message("获取登录数据失败" ,false);
            }
        } else {
            $array = $Oauth->login('qq');
            if(isset($array['code']) && $array['code']==0){
                return message("success" ,true,['url'=>$array['url']]);
            }elseif(isset($array['code'])){
                return message("登录接口返回：".$array['msg'] ,false);
            }else{
                return message("获取登录地址失败" ,false);
            }
        }
    }
}