<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        // 授权体系 v2（P1–P3）
        'sf:keygen'            => \app\command\KeyGen::class,
        'sf:sign'              => \app\command\Sign::class,
        'sf:product-sign'      => \app\command\ProductIdentitySign::class,
        'sf:authcode-backfill' => \app\command\AuthcodeBackfill::class,
        'sf:legacy-bridge'     => \app\command\LegacyBridge::class,
        'sf:rebate-settle'     => \app\command\RebateSettle::class,
        'sf:oss-check'         => \app\command\OssStorageCheck::class,
        'sf:plugin-upload-cleanup' => \app\command\PluginUploadCleanup::class,
    ],
];
