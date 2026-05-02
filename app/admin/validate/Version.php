<?php
namespace app\admin\validate;

use think\Validate;

class Version extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid|应用ID'  => 'require|integer',
        'edition|版本'   => 'require',
        'version|版本号' => 'require|integer',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require'  => '请选择所属应用！',
        'appid.number'  => '应用ID错误！',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}