<?php

namespace app\admin\validate;

use think\Validate;

class Feedback extends Validate
{
    protected $rule = [
        'id'     => 'require|number',
        'status' => 'require|in:0,1,2',
    ];

    protected $message = [
        'id.require'     => '缺少反馈ID',
        'id.number'      => '反馈ID必须为数字',
        'status.require' => '请选择处理状态',
        'status.in'      => '无效的处理状态',
    ];
}
