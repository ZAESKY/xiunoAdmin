<?php
namespace app\admin\validate;

use think\Validate;

class PowerTemplate extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
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