<?php
namespace app\admin\validate;

use think\Validate;

class PowerTemplate extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'name'   => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
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
