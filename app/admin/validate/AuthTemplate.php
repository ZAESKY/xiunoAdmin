<?php
namespace app\admin\validate;

use think\Validate;

class AuthTemplate extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'check_type|授权类型'  => 'require',
        'name|模板名称'   => 'require',
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