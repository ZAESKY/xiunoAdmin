<?php
namespace app\user\validate;

use think\Validate;

class AuthTemplate extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'check_type'  => 'require',
        'name'   => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'check_type.require' => 'validation.auth_type_required',
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
