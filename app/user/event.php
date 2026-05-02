<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [app\user\event\UserLog::class],
        'LogLevel' => [],
        'LogWrite' => [],
        'ActionLog' => [app\user\event\ActionLog::class],
        'UserLogin' => [app\user\event\UserLogin::class],
        'UserIndex' => [app\user\event\UserIndex::class],
    ],

    'subscribe' => [
    ],
];
