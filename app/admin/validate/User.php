<?php
namespace app\admin\validate;

use think\Validate;

class User extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid|应用ID'  => 'require|integer',
        'power|权限'   => 'require|integer',
        'username|用户名' => 'require',
        'password|密码' => 'require',
        'qq|QQ' => 'require|integer|max:12',
        'email|邮箱' => 'email',
        'phone|手机号' => 'integer|max:11',
        'userid|上级ID' => 'require|integer',
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