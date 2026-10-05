<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 调试模式（线上环境必须为 false）
    'app_debug'        => env('app_debug', false),
    // 应用地址
    'app_host'         => env('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 是否启用事件
    'with_event'       => true,
    // 默认应用
    'default_app'      => 'index',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',
    // 自动多应用模式
    'auto_multi_app'   => true,
    // 开启应用快速访问
    'app_express'    =>    true,
    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    // 'admin'          =>  'admin', //  admin子域名绑定到admin后台
    // 'api.qh-team.cn' =>  'api',   //  完整域名绑定
    // '*'              =>  'index', //  二级泛域名绑定到首页
    'domain_bind'      => [
        'www'          =>  'index',
        'admin'        =>  'admin',
        'api'          =>  'api',
        'user'         =>  'user',
        '*'            =>  'index',
    ],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => ['common'],

    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '温馨提示：页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => false,
    // Comma-separated exact origins permitted to make credentialed browser API calls.
    'cors_origins'     => array_values(array_filter(array_map('trim', explode(',', (string)env('app.cors_origins', ''))))),
];
