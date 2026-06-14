<?php
$safe = function($name, $key, $default = '') {
    $v = conf($name);
    if (!is_array($v)) return $default;
    return isset($v[$key]) ? $v[$key] : $default;
};
return [
    'zfb' => [
        // 易支付配置
        'epay_config' => [
            'apiurl'        => $safe('alipay_epay_config', 'url', ''),
            'partner'       => $safe('alipay_epay_config', 'pid', ''),
            'key'           => $safe('alipay_epay_config', 'key', ''),
            'sign_type'     => strtoupper('MD5'),
            'input_charset' => strtolower('utf-8'),
            'transport'     => 'http'
        ],
        // 卡易信笔笔清支付宝支付配置
        'kayixin_config' => [
            'partner'       => $safe('alipay_kyx_config', 'pid', ''),
            'key'           => $safe('alipay_kyx_config', 'key', ''),
            'getway'        => $safe('alipay_kyx_config', 'getway', ''),
            'sign_type'     => strtoupper('MD5'),
            'input_charset' => strtolower('utf-8'),
            'cacert'        => getcwd().'\\cacert.pem',
            'transport'     => 'http',
        ],
        // 支付宝支付配置
        'alipay_config' => [
            'sign_type' => "RSA2",
            'alipay_public_key' => $safe('alipay_config', 'publickey', ''),
            'merchant_private_key' => $safe('alipay_config', 'privatekey', ''),
            'charset' => "UTF-8",
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            'app_id' => $safe('alipay_config', 'appid', ''),
        ],
    ],
    'wx' => [
        // 易支付配置
        'epay_config' => [
            'apiurl'        => $safe('wxpay_epay_config', 'url', ''),
            'partner'       => $safe('wxpay_epay_config', 'pid', ''),
            'key'           => $safe('wxpay_epay_config', 'key', ''),
            'sign_type'     => strtoupper('MD5'),
            'input_charset' => strtolower('utf-8'),
            'transport'     => 'http'
        ],
        // 微信官方
        'wxpay_config' => [
            'APPID' => $safe('wxpay_config', 'appid', ''),
            'MCHID' => $safe('wxpay_config', 'mchid', ''),
            'KEY' => $safe('wxpay_config', 'key', ''),
            'APPSECRET' => $safe('wxpay_config', 'appsecret', '')
        ],
    ],
    'qq' => [
        // 易支付配置
        'epay_config' => [
            'apiurl'        => $safe('qqpay_epay_config', 'url', ''),
            'partner'       => $safe('qqpay_epay_config', 'pid', ''),
            'key'           => $safe('qqpay_epay_config', 'key', ''),
            'sign_type'     => strtoupper('MD5'),
            'input_charset' => strtolower('utf-8'),
            'transport'     => 'http'
        ],
        // 官方支付配置
        'qqpay_config' => [
            'MCH_ID' => $safe('qqpay_config', 'mchid', ''),
            'MCH_KEY' => $safe('qqpay_config', 'key', ''),
        ],
    ],
];
