<?php
declare (strict_types = 1);

namespace app\subscribe;

use think\Event;

class Notice {

    public function subscribe(Event $event){
        $event->listen('UserLoginNotice',[$this,'onUserLogin']);
        $event->listen('UserRegisterNotice',[$this,'onUserRegister']);
        $event->listen('ChangeBindingMailNotice',[$this,'onChangeBindingMail']);
        $event->listen('DownloadMailNotice',[$this,'onDownloadMailNotice']);
    }

    public function onUserLogin(array $param){
        $hook = conf('user_login_message');
        if(empty($hook)) return message('站点未开启用户登录验证发送！', false);
        return json_decode(hook($hook, $param), true);
    }

    public function onUserRegister(array $param){
        $hook = conf('user_register_message');
        if(empty($hook)) return message('站点未开启用户注册验证发送！', false);
        return json_decode(hook($hook, $param), true);
    }

    public function onChangeBindingMail(array $param){
        $hook = conf('change_binding_mail_message');
        if(empty($hook)) return message('站点未开启换绑邮箱验证发送！', false);
        return json_decode(hook($hook, $param), true);
    }

    public function onDownloadMailNotice(array $param){
        $hook = conf('download_mail_message');
        if(empty($hook)) return message('站点未开启换绑邮箱验证发送！', false);
        return json_decode(hook($hook, $param), true);
    }
}
