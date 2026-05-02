<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [app\api\event\ApiLog::class],
        'LogLevel' => [],
        'LogWrite' => [],
        'UserLogin' => [app\api\event\UserLogin::class]
    ],

    'subscribe' => [
    ],
];

