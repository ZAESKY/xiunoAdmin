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
        'title.require'   => 'feedback.title_required',
        'title.max'       => 'feedback.title_too_long',
        'content.require' => 'feedback.content_required',
        'content.max'     => 'feedback.content_too_long',
    ];
}
