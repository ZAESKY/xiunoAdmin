<?php
/*
* +----------------------------------------------------------------------
* | SF 综合验证授权系统
* +----------------------------------------------------------------------
* | Quotes [ 花开的再灿烂，也有凋谢的一天，致我们过去的青春 ]
* +----------------------------------------------------------------------
* | Author: 陌上花开 <2129876388@qq.com>
* +----------------------------------------------------------------------
* | Date: 2022年1月19日 18:48:32
* +----------------------------------------------------------------------
*/

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
        'addpay_discount|添加认证折扣' => 'require|integer|between:0,100',
        'pirate_discount|查看盗版折扣' => 'require|integer|between:0,100',
        'adduser_discount|添加用户折扣' => 'require|integer|between:0,100',
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