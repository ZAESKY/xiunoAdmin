<?php

return [
    // Internal ThinkPHP language packs. Public input accepts zh/en aliases.
    'default_lang'    => env('lang.default_lang', 'en-us'),
    'allow_lang_list' => ['zh-cn', 'en-us'],
    'detect_var'      => 'lang',
    'use_cookie'      => true,
    'cookie_var'      => 'think_lang',
    'header_var'      => 'think-lang',
    'extend_list'     => [],
    'accept_language' => [
        'zh-hans-cn' => 'zh-cn',
        'zh-hans'    => 'zh-cn',
        'zh-cn'      => 'zh-cn',
        'zh'         => 'zh-cn',
        'cn'         => 'zh-cn',
        'en-us'      => 'en-us',
        'en-gb'      => 'en-us',
        'en'         => 'en-us',
    ],
    'allow_group'     => false,
];
