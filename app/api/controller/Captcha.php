<?php

namespace app\api\controller;

use app\api\lib\GeetestLib;
use app\common\controller\Frontend;

class Captcha extends Frontend
{
    public function index(){
        $cookiesid = cookie('mysid');
        if ((!$cookiesid || !preg_match('/^[0-9a-z]{32}$/i', $cookiesid))) {
            $cookiesid = md5(uniqid(mt_rand() , 1) . time());
            cookie('mysid', $cookiesid, time() + 604800);
        }
        $GtSdk = new GeetestLib(conf('captcha_id'), conf('captcha_key'));
        $data = array(
            'user_id' => $cookiesid, // 网站用户id
            'client_type' => "web", // web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            'ip_address' => get_client_ip() // 请在此处传输用户请求验证时所携带的IP

        );
        $status = $GtSdk->pre_process($data, 1);
        session('gtserver', $status);
        session('user_id', $cookiesid);
        return $GtSdk->get_response_str();
    }
}