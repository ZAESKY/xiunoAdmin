<?php
// +----------------------------------------------------------------------
// | 模板设置
// +----------------------------------------------------------------------

return [
    'tpl_cache' => false,
    // 模板引擎类型使用Think
    'type'          => 'Think',
    // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写 3 保持操作方法
    'auto_rule'     => 1,
    // 模板目录名
    'view_dir_name' => 'view',
    // 模板后缀
    'view_suffix'   => 'html',
    // 模板文件名分隔符
    'view_depr'     => DIRECTORY_SEPARATOR,
    // 模板引擎普通标签开始标记
    'tpl_begin'     => '{',
    // 模板引擎普通标签结束标记
    'tpl_end'       => '}',
    // 标签库标签开始标记
    'taglib_begin'  => '{',
    // 标签库标签结束标记
    'taglib_end'    => '}',
    'tpl_replace_string' => array(
        '{__CSS__}' => '/Assets/css/',
        '{__JS__}' => '/Assets/js/',
        // Shared cache key for the runtime language loader and client translator.
        // Keep these two files on the same version so an old embedded dictionary
        // can never be mixed with newly rendered translation keys.
        '{__I18N_VERSION__}' => '20261004.5',
        '{__IMG__}' => '/Assets/img/',
        '{__MODULE__}' => '/Assets/module/',
        '{__LIBS__}' => '/Assets/libs/',
        '{__TEMPLATE__}' => '/template/',
        '{__PAY__}' => '/Assets/pay/',
        '{__FONT__}' => '/Assets/font/',
        '{__TEMPLATE_HOME_ASSETS__}' => '/template/assets/home/',
        '{__TEMPLATE_LOGIN_ASSETS__}' => '/template/assets/login/',
        '{__TEMPLATE_MAINTAIN_ASSETS__}' => '/template/assets/maintain/',
    )
];
