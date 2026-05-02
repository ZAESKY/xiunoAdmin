<?php
namespace app\index\validate;

use think\Validate;

class Register extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid|应用ID'  => 'require|integer',
        'username|用户名'   => 'require|min:6|alphaNum',
        'qq|QQ' => 'require|integer|min:5|max:12',
        'email|邮箱' => 'require|email',
        'phone|手机号' => 'require|integer|max:11',
        'password|密码'  => 'require|min:6|alphaNum|confirm:confirmPassword',
        'confirmPassword|确认密码' => 'require|min:6|alphaNum',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require'  => '请选择所属应用！',
        'appid.number'  => '应用ID错误！',
        'qq.require'  => '请输入要绑定的QQ！',
        'qq.number'  => '请输入正确的QQ！',
        'email.require'  => '请输入要绑定的邮箱！',
        'email.email'  => '请输入正确的邮箱！',
        'password.require'  => '请输入密码！',
        'confirmPassword.require'  => '请确认密码！',
        'password.confirm'  => '两次密码不相同！',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}