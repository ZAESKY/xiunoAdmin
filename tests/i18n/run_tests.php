<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$passed = 0;
$failed = 0;

function i18nCheck(bool $condition, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$label}\n";
        return;
    }

    $failed++;
    echo "[FAIL] {$label}" . ($detail !== '' ? ": {$detail}" : '') . "\n";
}

function i18nPathExcluded(string $path, string $root): bool
{
    $relative = str_replace('\\', '/', substr($path, strlen($root)));
    $excludedPrefixes = array(
        '/public/Assets/libs/',
        '/public/Assets/module/',
        '/public/template/assets/',
        '/app/common/lang/',
    );

    foreach ($excludedPrefixes as $prefix) {
        if (strpos($relative, $prefix) === 0) {
            return true;
        }
    }

    return false;
}

function i18nSourceFiles(string $root): array
{
    $files = array();
    foreach (array('app', 'addons', 'public') as $directory) {
        $path = $root . '/' . $directory;
        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || i18nPathExcluded($file->getPathname(), $root)) {
                continue;
            }
            if (!in_array(strtolower($file->getExtension()), array('php', 'html', 'js'), true)) {
                continue;
            }
            $files[] = $file->getPathname();
        }
    }

    sort($files);
    return $files;
}

$zh = require $root . '/app/common/lang/zh-cn.php';
$en = require $root . '/app/common/lang/en-us.php';
$zhKeys = array_keys($zh);
$enKeys = array_keys($en);
sort($zhKeys);
sort($enKeys);

i18nCheck(
    $zhKeys === $enKeys,
    '中英文语言包键集合一致',
    '中文独有 ' . count(array_diff($zhKeys, $enKeys)) . ' 个，英文独有 ' . count(array_diff($enKeys, $zhKeys)) . ' 个'
);

$missingZh = array();
$missingEn = array();
$usedKeys = array();
$literalPattern = '/(?<![A-Za-z0-9_$])(?:window\.)?t\(\s*([\'\"])([A-Za-z0-9_.:-]+)\1/';
foreach (i18nSourceFiles($root) as $sourceFile) {
    $source = file_get_contents($sourceFile);
    if ($source === false || !preg_match_all($literalPattern, $source, $matches)) {
        continue;
    }
    foreach ($matches[2] as $key) {
        $usedKeys[$key] = true;
        if (!array_key_exists($key, $zh)) {
            $missingZh[$key] = true;
        }
        if (!array_key_exists($key, $en)) {
            $missingEn[$key] = true;
        }
    }
}
ksort($missingZh);
ksort($missingEn);
i18nCheck(
    !$missingZh && !$missingEn,
    '源码中的字面量翻译键均有中英文定义',
    '扫描 ' . count($usedKeys) . ' 个键；缺失：' . implode(', ', array_unique(array_merge(array_keys($missingZh), array_keys($missingEn))))
);

$templateErrors = array();
$staticPages = array(
    'public/qrlogin/qrlogin.html',
    'public/wechat_mp_login.html',
);
foreach (i18nSourceFiles($root) as $templatePath) {
    if (strtolower(pathinfo($templatePath, PATHINFO_EXTENSION)) !== 'html') {
        continue;
    }
    $relativePath = str_replace('\\', '/', substr($templatePath, strlen($root) + 1));
    if (in_array($relativePath, $staticPages, true)) {
        continue;
    }
    $template = file_get_contents($templatePath);
    if ($template === false) {
        continue;
    }
    if (preg_match_all('/(?:lang|common)\.js\?v=([^\"\']+)/', $template, $matches)) {
        foreach ($matches[1] as $version) {
            if ($version !== '{__I18N_VERSION__}') {
                $templateErrors[] = $relativePath . ' -> ' . $version;
            }
        }
    }
}
i18nCheck(
    !$templateErrors,
    '应用模板统一使用内容派生的国际化缓存版本',
    implode(', ', $templateErrors)
);

$viewConfig = require $root . '/config/view.php';
$versionSource = '';
foreach (array('lang.js', 'common.js') as $runtimeName) {
    $runtimePath = $root . '/public/Assets/js/' . $runtimeName;
    $versionSource .= $runtimeName . ':' . hash_file('sha256', $runtimePath) . ';';
}
$expectedVersion = substr(hash('sha256', $versionSource), 0, 12);
$actualVersion = $viewConfig['tpl_replace_string']['{__I18N_VERSION__}'] ?? '';
i18nCheck(
    $actualVersion === $expectedVersion,
    '模板缓存版本由 lang.js 与 common.js 内容摘要生成',
    "期望 {$expectedVersion}，实际 {$actualVersion}"
);

$staticErrors = array();
foreach ($staticPages as $relativePath) {
    $html = file_get_contents($root . '/' . $relativePath);
    if ($html === false
        || strpos($html, '/Assets/js/common.js?v=') === false
        || strpos($html, 'common.js?v=20261004.5') !== false) {
        $staticErrors[] = $relativePath;
    }
}
i18nCheck(
    !$staticErrors,
    '静态登录框不再引用失效的旧版翻译运行时',
    implode(', ', $staticErrors)
);

$commonJs = file_get_contents($root . '/public/Assets/js/common.js');
i18nCheck(
    $commonJs !== false
    && strpos($commonJs, 'window.QH_I18N_MESSAGES') !== false
    && strpos($commonJs, 'window.QH_I18N_LOCALE') !== false
    && strpos($commonJs, 'window.QH_LANG') !== false
    && strpos($commonJs, 'window.SF_I18N_MESSAGES') === false
    && strpos($commonJs, 'window.SF_I18N_LOCALE') === false,
    '客户端翻译运行时统一读取 QH 国际化变量'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
