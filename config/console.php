<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        // 授权体系 v2（P1–P3）
        'qh:keygen'            => \app\command\KeyGen::class,
        'qh:sign'              => \app\command\Sign::class,
        'qh:product-sign'      => \app\command\ProductIdentitySign::class,
        'qh:authcode-backfill' => \app\command\AuthcodeBackfill::class,
        'qh:legacy-bridge'     => \app\command\LegacyBridge::class,
        'qh:rebate-settle'     => \app\command\RebateSettle::class,
        'qh:oss-check'         => \app\command\OssStorageCheck::class,
        'qh:plugin-upload-cleanup' => \app\command\PluginUploadCleanup::class,
    ],
];
