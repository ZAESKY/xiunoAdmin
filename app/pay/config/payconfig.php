<?php
/*
* +----------------------------------------------------------------------
* | SF 综合验证授权系统
* +----------------------------------------------------------------------
* | Quotes [ 花开的再灿烂，也有凋谢的一天，致我们过去的青春 ]
* +----------------------------------------------------------------------
* | Author: 陌上花开 <2129876388@qq.com>
* +----------------------------------------------------------------------
* | Date: 2022年1月19日 18:48:32
* +----------------------------------------------------------------------
*/

return [
    'zfb' => [
        // 易支付配置
        'epay_config' => [
            //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
            //支付API地址
            'apiurl'        => conf('alipay_epay_config')['url'],
            //商户ID
            'partner'       => conf('alipay_epay_config')['pid'],
            //商户KEY
            'key'           => conf('alipay_epay_config')['key'],
            //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

            //签名方式 不需修改
            'sign_type'     => strtoupper('MD5'),
            //字符编码格式 目前支持 gbk 或 utf-8
            'input_charset' => strtolower('utf-8'),
            //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
            'transport'     => 'http'
        ],
        // 卡易信笔笔清支付宝支付配置
        'kayixin_config' => [
            //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
            //合作身份者id，以2088开头的16位纯数字
            'partner'		=> conf('alipay_kyx_config')['pid'],
            //安全检验码，以数字和字母组成的32位字符
            'key'			=> conf('alipay_kyx_config')['key'],
            'getway'		=> conf('alipay_kyx_config')['getway'],
            //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
            //签名方式 不需修改
            'sign_type'     => strtoupper('MD5'),

            //字符编码格式 目前支持 gbk 或 utf-8
            'input_charset' => strtolower('utf-8'),
            //ca证书路径地址，用于curl中ssl校验
            //请保证cacert.pem文件在当前文件夹目录中
            'cacert'        => getcwd().'\\cacert.pem',
            //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
            'transport'     => 'http',
        ],
        // 支付宝支付配置
        'alipay_config' => [
            //签名方式,默认为RSA2(RSA2048)
            'sign_type' => "RSA2",

            //支付宝公钥
            'alipay_public_key' => conf('alipay_config')['publickey'],

            //商户私钥
            'merchant_private_key' => conf('alipay_config')['privatekey'],

            //编码格式
            'charset' => "UTF-8",

            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

            //应用ID
            'app_id' => conf('alipay_config')['appid'],
        ],
//        // 码支付
//        'codepay_config' => [
//            //上传到public/Assets/pay/codepay/ali_zsm.png 会自动使用赞赏码来收款
//            'codepay_zsm' => 'ali_zsm.png',
//            //上传到public/Assets/pay/codepay/alipay.png 会自动使用来收款
//            'codepay_alipay' => 'alipay.png',
//            //码支付接口配置
//            'id' => conf('alipay_codepay_config')['id'],
//            'key' => conf('alipay_codepay_config')['key'],
//            //字符编码格式 目前支持 gbk GB2312 或 utf-8 保证跟文档编码一致 建议使用utf-8
//            'chart' => strtolower('utf-8'),
//            //是否启用免挂机模式 1为启用. 未开通请勿更改否则资金无法及时到账
//            'act' => "0", //认证版则开启 一般情况都为0
//            /**订单支付页面显示方式
//             * 1: GET框架云端支付 (简单 兼容性强 自动升级 1分钟可集成)
//             * 2: POST表单到云端支付 (简单 兼容性强 自动升级)
//             * 3：自定义开发模式 (默认 复杂 需要一定开发能力 手动升级 html/codepay_diy_order.php修改收银台代码)
//             * 4：高级模式(复杂 需要较强的开发能力 手动升级 html/codepay_supper_order.php修改收银台代码)
//             */
//            'page' => 4, //支付页面展示方式
//            //支付页面风格样式 仅针对 page 参数为 1或2 才会有用。
//            'style' => 1, //暂时保留的功能 后期会生效 留意官网发布的风格编号
//            //二维码超时设置  单位：秒
//            'outTime' => 300,//360秒=6分钟 最小值60  不建议太长 否则会影响其他人支付
//            //最低金额限制
//            'min' => 0.01,
//            //"qrcode_url" => "./codepay/qrcode.php"; //使用本地二维码
//            'pay_type' => 1,
//        ]
    ],
    'wx' => [
        // 易支付配置
        'epay_config' => [
            //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
            //支付API地址
            'apiurl'        => conf('wxpay_epay_config')['url'],
            //商户ID
            'partner'       => conf('wxpay_epay_config')['pid'],
            //商户KEY
            'key'           => conf('wxpay_epay_config')['key'],
            //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

            //签名方式 不需修改
            'sign_type'     => strtoupper('MD5'),
            //字符编码格式 目前支持 gbk 或 utf-8
            'input_charset' => strtolower('utf-8'),
            //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
            'transport'     => 'http'
        ],
        // 微信官方
        'wxpay_config' => [
            'APPID' => conf('wxpay_config')['appid'],
            'MCHID' => conf('wxpay_config')['mchid'],
            'KEY' => conf('wxpay_config')['key'],
            'APPSECRET' => conf('wxpay_config')['appsecret']
        ],
//        // 码支付
//        'codepay_config' => [
//            //上传到public/Assets/pay/codepay/wx_zsm.png 会自动使用赞赏码来收款
//            'codepay_zsm' => 'wx_zsm.png',
//            //上传到public/Assets/pay/codepay/wxpay.png 会自动使用来收款
//            'codepay_wxpay' => 'wxpay.png',
//            //码支付接口配置
//            'id' => conf('wxpay_codepay_config')['id'],
//            'key' => conf('wxpay_codepay_config')['key'],
//            //字符编码格式 目前支持 gbk GB2312 或 utf-8 保证跟文档编码一致 建议使用utf-8
//            'chart' => strtolower('utf-8'),
//            //是否启用免挂机模式 1为启用. 未开通请勿更改否则资金无法及时到账
//            'act' => "0", //认证版则开启 一般情况都为0
//            /**订单支付页面显示方式
//             * 1: GET框架云端支付 (简单 兼容性强 自动升级 1分钟可集成)
//             * 2: POST表单到云端支付 (简单 兼容性强 自动升级)
//             * 3：自定义开发模式 (默认 复杂 需要一定开发能力 手动升级 html/codepay_diy_order.php修改收银台代码)
//             * 4：高级模式(复杂 需要较强的开发能力 手动升级 html/codepay_supper_order.php修改收银台代码)
//             */
//            'page' => 4, //支付页面展示方式
//            //支付页面风格样式 仅针对 page 参数为 1或2 才会有用。
//            'style' => 1, //暂时保留的功能 后期会生效 留意官网发布的风格编号
//            //二维码超时设置  单位：秒
//            'outTime' => 300,//360秒=6分钟 最小值60  不建议太长 否则会影响其他人支付
//            //最低金额限制
//            'min' => 0.01,
//            //"qrcode_url" => "./codepay/qrcode.php"; //使用本地二维码
//            'pay_type' => 1,
//        ]
    ],
    'qq' => [
        // 易支付配置
        'epay_config' => [
            //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
            //支付API地址
            'apiurl'        => conf('qqpay_epay_config')['url']??'',
            //商户ID
            'partner'       => conf('qqpay_epay_config')['pid']??'',
            //商户KEY
            'key'           => conf('qqpay_epay_config')['key']??'',
            //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

            //签名方式 不需修改
            'sign_type'     => strtoupper('MD5'),
            //字符编码格式 目前支持 gbk 或 utf-8
            'input_charset' => strtolower('utf-8'),
            //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
            'transport'     => 'http'
        ],
        // 官方支付配置
        'qqpay_config' => [
            'MCH_ID' => conf('qqpay_config')['mchid'],
            'MCH_KEY' => conf('qqpay_config')['key'],
        ],
//        // 码支付
//        'codepay_config' => [
//            //上传到public/Assets/pay/codepay/qq_zsm.png 会自动使用赞赏码来收款
//            'codepay_zsm' => 'qq_zsm.png',
//            //上传到public/Assets/pay/codepay/qqpay.png 会自动使用来收款
//            'codepay_qqpay' => 'qqpay.png',
//            //码支付接口配置
//            'id' => conf('qqpay_codepay_config')['id'],
//            'key' => conf('qqpay_codepay_config')['key'],
//            //字符编码格式 目前支持 gbk GB2312 或 utf-8 保证跟文档编码一致 建议使用utf-8
//            'chart' => strtolower('utf-8'),
//            //是否启用免挂机模式 1为启用. 未开通请勿更改否则资金无法及时到账
//            'act' => "0", //认证版则开启 一般情况都为0
//            /**订单支付页面显示方式
//             * 1: GET框架云端支付 (简单 兼容性强 自动升级 1分钟可集成)
//             * 2: POST表单到云端支付 (简单 兼容性强 自动升级)
//             * 3：自定义开发模式 (默认 复杂 需要一定开发能力 手动升级 html/codepay_diy_order.php修改收银台代码)
//             * 4：高级模式(复杂 需要较强的开发能力 手动升级 html/codepay_supper_order.php修改收银台代码)
//             */
//            'page' => 4, //支付页面展示方式
//            //支付页面风格样式 仅针对 page 参数为 1或2 才会有用。
//            'style' => 1, //暂时保留的功能 后期会生效 留意官网发布的风格编号
//            //二维码超时设置  单位：秒
//            'outTime' => 300,//360秒=6分钟 最小值60  不建议太长 否则会影响其他人支付
//            //最低金额限制
//            'min' => 0.01,
//            //"qrcode_url" => "./codepay/qrcode.php"; //使用本地二维码
//            'pay_type' => 1,
//        ]
    ],
];