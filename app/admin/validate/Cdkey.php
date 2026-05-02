<?php
namespace app\admin\validate;

use think\Validate;

class Cdkey extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid|应用ID'  => 'require|integer',
        'cdkey_type|卡密类型'   => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [

    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}