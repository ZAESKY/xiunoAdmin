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
        'id.require'     => 'feedback.id_required',
        'id.number'      => 'feedback.id_number',
        'status.require' => 'feedback.status_required',
        'status.in'      => 'feedback.invalid_status',
    ];
}
