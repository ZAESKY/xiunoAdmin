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