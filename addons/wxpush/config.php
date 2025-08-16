<?php

return [
    [
        'name' => 'type',
        'title' => '消息接口',
        'type' => 'radio',
        'content' => [
            '关闭',
            'Server酱',
            'WxPusher',
        ],
        'value' => '1',
        'rule' => 'required',
        'msg' => '',
        'tip' => '',
        'ok' => '',
        'extend' => '',
    ],
    [
        'name' => 'server',
        'title' => 'Server酱',
        'type' => 'array',
        'content' => [],
        'value' => [
            'sendkey' => 'server酱的SendKey',
        ],
        'rule' => 'required',
        'msg' => '',
        'tip' => '',
        'ok' => '',
        'extend' => '',
    ],
    [
        'name' => 'wxpusher',
        'title' => 'WxPusher',
        'type' => 'array',
        'content' => [],
        'value' => [
            'uid' => '填写UID',
            'apptoken' => '填写appToken',
        ],
        'rule' => 'required',
        'msg' => '',
        'tip' => '',
        'ok' => '',
        'extend' => '',
    ],
    [
        'name' => '__tips__',
        'title' => '温馨提示',
        'type' => 'string',
        'content' => [],
        'value' => 'Server酱：<a href="https://sct.ftqq.com/sendkey" target="_blank">点击进入</a> ，登录账号 -> 绑定自己的微信号 -> 获取到SendKey填写到下方输入框！<br><br>
WxPusher：<a href="https://wxpusher.zjiecode.com/admin/main/" target="_blank">点此进入</a> ，注册并且创建应用 -> 将appToken填写到上方输入框 -> 扫码关注应用 -> 在用户列表查看自己的UID填写到下方输入框',
        'rule' => 'required',
        'msg' => '',
        'tip' => '',
        'ok' => '',
        'extend' => 'orange',
    ],
];
