<?php
namespace app\user\validate;

use think\Validate;

class User extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid'  => 'require|integer',
        'power'   => 'require|integer',
        'username' => 'require|length:3,64|regex:/^[\p{L}\p{N}_.-]+$/u',
        'password' => 'require',
        'qq|QQ' => 'require|integer|max:12',
        'email' => 'email',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require' => 'validation.app_required',
        'appid.integer'  => 'validation.app_id_invalid',
        'power.require'  => 'validation.power_required',
        'power.integer'   => 'validation.power_invalid',
        'username.require' => 'validation.username_required',
        'username.length' => 'validation.username_length',
        'username.regex' => 'validation.username_format',
        'password.require' => 'validation.password_required',
        'qq.require'     => 'validation.qq_required',
        'qq.integer'     => 'validation.qq_digits',
        'qq.max'         => 'validation.qq_max',
        'email.email'    => 'validation.email_invalid',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'edit' => ['password' => 'remove'],
    ];

}
