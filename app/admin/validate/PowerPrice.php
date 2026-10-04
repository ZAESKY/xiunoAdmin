<?php
namespace app\admin\validate;

use think\Validate;

class PowerPrice extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'tid'  => 'require|integer|>:0',
        'name'   => 'require',
        'addauth_discount' => 'require|integer|between:0,100',
        'adduser_discount' => 'require|integer|between:0,100',
        'rebate_rate' => 'require|float|between:0,100',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'tid.require' => 'validation.template_required',
        'tid.integer' => 'validation.template_invalid',
        'tid.>' => 'validation.template_invalid',
        'name.require' => 'validation.power_name_required',
        'addauth_discount.require' => 'validation.auth_discount_required',
        'addauth_discount.integer' => 'validation.auth_discount_integer',
        'addauth_discount.between' => 'validation.auth_discount_range',
        'adduser_discount.require' => 'validation.user_discount_required',
        'adduser_discount.integer' => 'validation.user_discount_integer',
        'adduser_discount.between' => 'validation.user_discount_range',
        'rebate_rate.require' => 'validation.rebate_rate_required',
        'rebate_rate.float' => 'validation.rebate_rate_number',
        'rebate_rate.between' => 'validation.rebate_rate_range',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}
