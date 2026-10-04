<?php

namespace app\pay\validate;

use think\Validate;

class Index extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'type'  => 'require|in:alipay,wxpay,qqpay',
        // 订单号是标识符而不是可计算的整数；23 位订单号会超出 PHP 整数范围。
        'orderid'   => 'require|regex:/^[0-9]{17,23}$/D',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'type.require'  => 'pay.type_required',
        'type.in'  => 'pay.type_error',
        'orderid.require'  => 'pay.order_no_required',
        'orderid.regex'  => 'pay.order_no_invalid',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}
