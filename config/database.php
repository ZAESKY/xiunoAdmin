<?php

return [
    'default' => 'mysql',
    'time_query_rule' => [],
    'auto_timestamp' => true,
    'datetime_format' => 'Y-m-d H:i:s',
    'datetime_field' => '',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => env('database.hostname', 'localhost'),
            'database' => env('database.database', 'www_admindev_com'),
            'username' => env('database.username', 'admin'),
            'password' => env('database.password', '123456'),
            'hostport' => env('database.hostport', 3306),
            'params' => [],
            'charset' => 'utf8',
            'prefix' => 'SF_',
            'deploy' => 0,
            'rw_separate' => false,
            'master_num' => 1,
            'slave_no' => '',
            'fields_strict' => true,
            'break_reconnect' => false,
            'trigger_sql' => true,
            'fields_cache' => false,
        ],
    ],
];
