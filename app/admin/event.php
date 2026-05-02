<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [app\admin\event\AdminLog::class],
        'LogLevel' => [],
        'LogWrite' => [],
        'ActionLog' => [app\admin\event\ActionLog::class],
    ],

    'subscribe' => [
    ],
];
