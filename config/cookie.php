<?php
// +----------------------------------------------------------------------
// | Cookie设置
// +----------------------------------------------------------------------
return [
    // cookie 保存时间
    'expire'    => 0,
    // cookie 保存路径
    'path'      => '/',
    // cookie 有效域名
    'domain'    => env('cookie.domain', ''),
    //  cookie 启用安全传输（线上请配合 HTTPS 设为 true）
    'secure'    => env('cookie.secure', false),
    // httponly设置（防止 JS 读取 cookie，降低 XSS 风险）
    'httponly'  => env('cookie.httponly', true),
    // 是否使用 setcookie
    'setcookie' => true,
    // samesite 设置，支持 'strict' 'lax'
    'samesite'  => env('cookie.samesite', 'Lax'),
];
