<?php
namespace app\admin\validate;

use think\Validate;

class Auth extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid|应用ID'  => 'require|integer',
        'auth_info|授权内容'   => 'require',
        'qq|授权者QQ' => 'require|integer|max:12',
        'ip|IP'  => 'ip',
        'type|授权时间'  => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require'  => '请选择所属应用！',
        'appid.number'  => '应用ID错误！',
        'qq.require'  => '请输入授权者QQ！',
        'qq.number'  => '请输入正确的授权者QQ！',
        'type.number'  => '请选择正确的授权时间！',
        'type.require'  => '请选择授权时间！',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}