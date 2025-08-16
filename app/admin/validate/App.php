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

class App extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'check_type|判断规则'  => 'require',
        'name|应用名称'   => 'require',
        'auth_template|价格模板' => 'require|integer',
        'payment_template|认证模板' => 'require|integer',
        'power_template|权限模板' => 'require|integer',
        'public_key|授权公钥' => 'require',
        'private_key|授权私钥' => 'require',
        'pirate_msg|盗版提示' => 'require',
        'endtime_msg|授权到期提示' => 'require',
        'status_msg|授权封禁提示' => 'require',
        'authcode_msg|授权码错误提示' => 'require',
        'ip_msg|IP错误提示' => 'require',
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