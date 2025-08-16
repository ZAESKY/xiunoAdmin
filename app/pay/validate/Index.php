<?php

namespace app\pay\validate;

use think\Validate;

class Index extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'type|支付类型'  => 'require',
        'orderid|订单号'   => 'require|integer',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'type.require'  => '支付类型不能为空！',
        'orderid.require'  => '订单号不能为空！',
        'orderid.number'  => '订单号不符合要求！',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}