#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$languageFiles = [
    'zh-cn' => $root . '/app/common/lang/zh-cn.php',
    'en-us' => $root . '/app/common/lang/en-us.php',
];

/** @return string[] */
function duplicateLanguageKeys(string $file): array
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $file);
    }

    preg_match_all("/^\\s*'([^']+)'\\s*=>/m", $contents, $matches);
    $counts = array_count_values($matches[1]);

    return array_keys(array_filter($counts, static function (int $count): bool {
        return $count > 1;
    }));
}

/** @return string[] */
function referencedLanguageKeys(string $root): array
{
    $keys = [];
    $roots = [$root . '/app', $root . '/addons', $root . '/public'];
    $extensions = ['php' => true, 'html' => true, 'js' => true];

    foreach ($roots as $scanRoot) {
        if (!is_dir($scanRoot)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $item): bool {
                    if (!$item->isDir()) {
                        return true;
                    }
                    return !in_array($item->getFilename(), ['lang', 'libs', 'vendor', 'upload', 'pay'], true);
                }
            )
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) {
                continue;
            }
            if ($item->getFilename() === 'xmSelect.js') {
                continue;
            }
            $extension = strtolower($item->getExtension());
            if (!isset($extensions[$extension])) {
                continue;
            }
            $contents = file_get_contents($item->getPathname());
            if ($contents === false) {
                continue;
            }
            preg_match_all('/(?<![A-Za-z0-9_])t\\s*\\(\\s*([\'\"])([A-Za-z0-9_.-]+)\\1/', $contents, $matches);
            foreach ($matches[2] as $key) {
                $keys[$key] = true;
            }
        }
    }

    $result = array_keys($keys);
    sort($result);
    return $result;
}

$languages = [];
$hasError = false;

foreach ($languageFiles as $locale => $file) {
    $messages = require $file;
    if (!is_array($messages)) {
        fwrite(STDERR, sprintf("[ERROR] %s does not return an array.\n", $file));
        $hasError = true;
        continue;
    }
    $languages[$locale] = $messages;

    $duplicates = duplicateLanguageKeys($file);
    if ($duplicates !== []) {
        fwrite(STDERR, sprintf("[ERROR] %s duplicate keys: %s\n", $locale, implode(', ', $duplicates)));
        $hasError = true;
    }
}

if (count($languages) === count($languageFiles)) {
    $zhOnly = array_keys(array_diff_key($languages['zh-cn'], $languages['en-us']));
    $enOnly = array_keys(array_diff_key($languages['en-us'], $languages['zh-cn']));
    if ($zhOnly !== []) {
        fwrite(STDERR, '[ERROR] Keys missing from en-us: ' . implode(', ', $zhOnly) . "\n");
        $hasError = true;
    }
    if ($enOnly !== []) {
        fwrite(STDERR, '[ERROR] Keys missing from zh-cn: ' . implode(', ', $enOnly) . "\n");
        $hasError = true;
    }

    $referenced = referencedLanguageKeys($root);
    $missing = array_values(array_diff($referenced, array_keys($languages['zh-cn'])));
    if ($missing !== []) {
        fwrite(STDERR, '[ERROR] Referenced keys missing from language files: ' . implode(', ', $missing) . "\n");
        $hasError = true;
    }

    printf(
        "Locales: zh-cn=%d, en-us=%d; referenced static keys=%d.\n",
        count($languages['zh-cn']),
        count($languages['en-us']),
        count($referenced)
    );
}

if ($hasError) {
    exit(1);
}

fwrite(STDOUT, "i18n resource audit passed.\n");
