<?php
namespace app\user\validate;

use think\Validate;

class Auth extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid'  => 'require|integer',
        'auth_info'   => 'require',
        'qq' => 'require|integer|max:12',
        'ip|IP'  => 'ip',
        'type' => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require'  => 'validation.app_required',
        'appid.integer'  => 'validation.app_id_invalid',
        'auth_info.require' => 'validation.auth_content_required',
        'qq.require'  => 'validation.auth_qq_required',
        'qq.integer'  => 'validation.auth_qq_invalid',
        'qq.max' => 'validation.qq_max',
        'ip.ip' => 'validation.ip_invalid',
        'type.require' => 'validation.auth_time_required',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}
