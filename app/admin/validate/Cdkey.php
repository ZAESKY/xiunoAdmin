<?php
namespace app\admin\validate;

use think\Validate;

class Cdkey extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid'  => 'require|integer',
        'cdkey_type'   => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require' => 'validation.app_required',
        'appid.integer' => 'validation.app_id_invalid',
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
