<?php
namespace app\user\validate;

use think\Validate;

class User extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid|应用ID'  => 'require|integer',
        'power|权限'   => 'require|integer',
        'username|用户名' => 'require',
        'password|密码' => 'require',
        'qq|QQ' => 'require|integer|max:12',
        'email|邮箱' => 'email',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require' => '请选择所属应用',
        'appid.integer'  => '应用ID格式错误',
        'power.require'  => '请选择用户权限',
        'power.integer'   => '权限格式错误',
        'username.require' => '请填写用户名',
        'password.require' => '请填写密码',
        'qq.require'     => '请填写QQ号',
        'qq.integer'     => 'QQ号只能填写数字',
        'qq.max'         => 'QQ号不能超过12位',
        'email.email'    => '邮箱格式不正确',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'edit' => ['password' => 'remove'],
    ];

}