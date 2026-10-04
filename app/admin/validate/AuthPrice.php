<?php
namespace app\admin\validate;

use think\Validate;

class AuthPrice extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'tid'   => 'require|integer',
        'name' => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'tid.require' => 'validation.template_required',
        'tid.integer' => 'validation.template_invalid',
        'name.require' => 'validation.template_name_required',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}
