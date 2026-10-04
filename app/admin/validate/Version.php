<?php
namespace app\admin\validate;

use think\Validate;

class Version extends Validate
{
    /**
     * 验证规则.
     */
    protected $rule =   [
        'appid'  => 'require|integer',
        'edition' => 'require|max:64',
        'version'   => 'require|integer|gt:0',
    ];
    /**
     * 提示消息.
     */
    protected $message  =   [
        'appid.require'  => 'validation.app_required',
        'appid.integer'  => 'validation.app_id_invalid',
        'edition.require' => 'validation.edition_required',
        'edition.max' => 'validation.edition_max',
        'version.require' => 'validation.build_required',
        'version.integer' => 'validation.build_integer',
        'version.gt'      => 'validation.build_positive',
    ];
    /**
     * 验证场景.
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

}
