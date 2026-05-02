<?php
namespace app\admin\validate;

use think\Validate;

class AuthPrice extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'tid|模板'   => 'require|integer',
        'name|模板名称' => 'require',
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