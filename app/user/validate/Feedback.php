<?php

namespace app\user\validate;

use think\Validate;

class Feedback extends Validate
{
    protected $rule = [
        'title'   => 'require|max:255',
        'content' => 'require|max:5000',
    ];

    protected $message = [
        'title.require'   => '请填写反馈标题',
        'title.max'       => '标题不能超过255个字符',
        'content.require' => '请填写反馈内容',
        'content.max'     => '内容不能超过5000个字符',
    ];
}
