<?php
// 事件定义文件
return [
    'bind'      => [],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
    ],

    'subscribe' => [
        'app\subscribe\Notice',
        // 更多事件订阅
    ],
];
