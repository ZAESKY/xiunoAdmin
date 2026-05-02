<?php
namespace app\api\validate;

use think\Validate;

class Auth extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid|应用ID'  => 'require|integer',
        'auth_info|授权内容'   => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require'  => '缺少应用ID！',
        'appid.number'  => '应用ID应为整数！',
        'auth_info.require'  => '缺少授权内容！',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}