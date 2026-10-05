<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$passed = 0;
$failed = 0;

function uiCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$label}\n";
        return;
    }

    $failed++;
    echo "[FAIL] {$label}\n";
}

$commonJs = file_get_contents($root . '/public/Assets/js/common.js');
$adminCss = file_get_contents($root . '/public/Assets/module/admin.css');

uiCheck(
    $commonJs !== false
    && strpos($commonJs, "defaultTheme: 'theme-qh'") !== false
    && strpos($commonJs, "admin.changeTheme('theme-qh')") !== false,
    '后台启动时固定并持久化 QH 蓝色主题'
);

uiCheck(
    $adminCss !== false
    && strpos($adminCss, '.theme-qh .layui-btn{background-color:#2d8cf0') !== false
    && strpos($adminCss, '.theme-qh .layui-form-onswitch{border-color:#2d8cf0;background-color:#2d8cf0}') !== false
    && strpos($adminCss, '.theme-qh .layui-laypage .layui-laypage-curr .layui-laypage-em{background-color:#2d8cf0}') !== false,
    'QH 主题按钮、开关和分页统一使用原蓝色 #2d8cf0'
);

$themeButtons = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
        continue;
    }
    $html = file_get_contents($file->getPathname());
    if ($html !== false && preg_match('/ew-event=[\'\"]theme[\'\"]/', $html)) {
        $themeButtons[] = substr($file->getPathname(), strlen($root) + 1);
    }
}
uiCheck(!$themeButtons, '所有后台壳模板均已移除右上角主题三点按钮');
uiCheck(!is_file($root . '/public/page/tpl/tpl-theme.html'), '主题设置侧滑面板模板已删除');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
