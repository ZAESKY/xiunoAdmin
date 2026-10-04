<?php
namespace app\user\validate;

use think\Validate;

class Cdkey extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid'  => 'require|integer|>:0',
        'cdkey_type'   => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require' => 'validation.app_required',
        'appid.integer' => 'validation.app_id_invalid',
        'appid.>' => 'validation.app_id_invalid',
        'cdkey_type.require' => 'validation.cdkey_type_required',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}
