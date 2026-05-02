<?php
namespace app\admin\validate;

use think\Validate;

class PowerPrice extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'tid|模板'  => 'require|integer|>:0',
        'name|权限名称'   => 'require',
        'addauth_discount|添加授权折扣' => 'require|integer|between:0,100',
        'adduser_discount|添加用户折扣' => 'require|integer|between:0,100',
        'rebate_rate|返利比例' => 'require|float|between:0,100',
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