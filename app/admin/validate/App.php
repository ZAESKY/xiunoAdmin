<?php
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