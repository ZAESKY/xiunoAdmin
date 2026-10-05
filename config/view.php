<?php
// +----------------------------------------------------------------------
// | 模板设置
// +----------------------------------------------------------------------

// Tie the browser cache key to the actual client-side i18n runtime. This keeps
// translated templates and dynamically rendered table/button labels on the
// same runtime after a deployment, without relying on a manually bumped value.
$i18nVersionSource = '';
$i18nRuntimeFiles = array(
    dirname(__DIR__) . '/public/Assets/js/lang.js',
    dirname(__DIR__) . '/public/Assets/js/common.js',
);
foreach ($i18nRuntimeFiles as $i18nRuntimeFile) {
    $i18nVersionSource .= basename($i18nRuntimeFile) . ':';
    $i18nVersionSource .= is_file($i18nRuntimeFile)
        ? hash_file('sha256', $i18nRuntimeFile)
        : 'missing';
    $i18nVersionSource .= ';';
}
$i18nVersion = substr(hash('sha256', $i18nVersionSource), 0, 12);

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
        // Shared content-derived cache key for the language loader and translator.
        '{__I18N_VERSION__}' => $i18nVersion,
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
