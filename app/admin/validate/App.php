<?php
namespace app\admin\validate;

use think\Validate;

class App extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'check_type'  => 'require',
        'name'   => 'require',
        'auth_template' => 'require|integer',
        'power_template' => 'require|integer',
        'public_key' => 'require',
        'private_key' => 'require',
        'pirate_msg' => 'require',
        'endtime_msg' => 'require',
        'status_msg' => 'require',
        'authcode_msg' => 'require',
        'ip_msg' => 'require',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'check_type.require' => 'validation.check_type_required',
        'name.require' => 'validation.app_name_required',
        'auth_template.require' => 'validation.auth_template_required',
        'auth_template.integer' => 'validation.auth_template_invalid',
        'power_template.require' => 'validation.power_template_required',
        'power_template.integer' => 'validation.power_template_invalid',
        'public_key.require' => 'validation.public_key_required',
        'private_key.require' => 'validation.private_key_required',
        'pirate_msg.require' => 'validation.pirate_message_required',
        'endtime_msg.require' => 'validation.expiry_message_required',
        'status_msg.require' => 'validation.ban_message_required',
        'authcode_msg.require' => 'validation.authcode_message_required',
        'ip_msg.require' => 'validation.ip_message_required',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}
