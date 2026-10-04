<?php

declare(strict_types=1);

use app\common\service\AccountingLogService;

$root = dirname(__DIR__);
require $root . '/app/common/service/AccountingLogService.php';

$zh = require $root . '/app/common/lang/zh-cn.php';
$en = require $root . '/app/common/lang/en-us.php';
$errors = [];

$required = [
    AccountingLogService::LEDGER_BALANCE => [
        'recharge', 'rebate', 'cdkey_exchange', 'deduct', 'admin_edit', 'refund',
        'deduct_plugin', 'plugin_cdkey_buy', 'plugin_income', 'withdraw_apply', 'withdraw_cancel',
        'withdraw_reject', 'withdraw_reject_refund', 'user_update', 'cdkey_create',
        'plugin_reward',
    ],
    AccountingLogService::LEDGER_POINT => [
        'consume', 'recharge', 'cdkey_exchange', 'deduct_plugin', 'plugin_income',
        'exchange', 'checkin', 'plugin_reward', 'refund', 'cancel',
    ],
];

foreach ($required as $ledger => $types) {
    $keys = AccountingLogService::typeLanguageKeys($ledger);
    foreach ($types as $type) {
        if (!isset($keys[$type])) {
            $errors[] = $ledger . ' type is not mapped: ' . $type;
            continue;
        }
        foreach (['zh-cn' => $zh, 'en-us' => $en] as $language => $translations) {
            if (!isset($translations[$keys[$type]]) || trim((string)$translations[$keys[$type]]) === '') {
                $errors[] = $language . ' translation is missing: ' . $keys[$type];
            }
        }
    }
}

foreach (['accounting.type.other'] as $key) {
    if (empty($zh[$key]) || empty($en[$key])) {
        $errors[] = 'fallback translation is missing: ' . $key;
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
);
$detected = [
    AccountingLogService::LEDGER_BALANCE => [],
    AccountingLogService::LEDGER_POINT => [],
];
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    foreach ([
        AccountingLogService::LEDGER_BALANCE => 'BalanceLogModel',
        AccountingLogService::LEDGER_POINT => 'PointLogModel',
    ] as $ledger => $model) {
        $pattern = '/(?:\\\\)?' . $model . '::add\s*\(\s*[^,]+,\s*[\'\"]([a-z0-9_]+)[\'\"]/s';
        if (preg_match_all($pattern, $source, $matches)) {
            $detected[$ledger] = array_merge($detected[$ledger], $matches[1]);
        }
    }
}

foreach ($detected as $ledger => $types) {
    $keys = AccountingLogService::typeLanguageKeys($ledger);
    foreach (array_unique($types) as $type) {
        if (!isset($keys[$type])) {
            $errors[] = $ledger . ' type used by source is not mapped: ' . $type;
        }
    }
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, array_unique($errors)) . PHP_EOL);
    exit(1);
}

echo 'Accounting type and language coverage passed.' . PHP_EOL;
