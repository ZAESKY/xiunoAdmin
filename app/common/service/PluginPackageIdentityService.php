<?php
declare(strict_types=1);

namespace app\common\service;

use think\Exception;

/**
 * Resolves the Xiuno installation directory independently from the market slug.
 */
class PluginPackageIdentityService
{
    public static function inspectArchive(string $zipPath): string
    {
        $problem = SafeZipService::validate($zipPath, [], 10000, 1073741824);
        if ($problem !== null) {
            throw new Exception($problem);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('无法打开插件压缩包');
        }

        $top = '';
        $hasConf = false;
        $seen = [];
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                $key = strtolower($name);
                if (isset($seen[$key])) {
                    throw new Exception('插件包包含重复或大小写冲突的文件');
                }
                $seen[$key] = true;

                $trimmed = rtrim($name, '/');
                $segments = explode('/', $trimmed);
                $currentTop = (string)($segments[0] ?? '');
                if (!self::isValid($currentTop) || $currentTop === '__MACOSX') {
                    throw new Exception('插件包顶层目录必须是 1-64 位字母、数字或下划线');
                }
                if ($top === '') {
                    $top = $currentTop;
                } elseif (!hash_equals($top, $currentTop)) {
                    throw new Exception('插件包必须且只能包含一个顶层插件目录');
                }
                if ($name === $top . '/conf.json') {
                    $hasConf = true;
                }
            }
        } finally {
            $zip->close();
        }

        if ($top === '' || !$hasConf) {
            throw new Exception('插件包顶层目录中缺少 conf.json');
        }
        return $top;
    }

    public static function isValid(string $pluginDir): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_]{1,64}$/D', $pluginDir);
    }

    /**
     * Resolve legacy records without confusing a market slug with a directory.
     * Old packages named "{dir}_v{version}.zip" can be migrated safely.
     */
    public static function resolveRecord(array $plugin): string
    {
        $pluginDir = trim((string)($plugin['plugin_dir'] ?? ''));
        if (self::isValid($pluginDir)) {
            return $pluginDir;
        }

        $fileName = basename(str_replace('\\', '/', (string)($plugin['package_file_name'] ?? '')));
        $version = trim((string)($plugin['version'] ?? ''));
        $suffix = '_v' . $version . '.zip';
        if ($version !== '' && strlen($fileName) > strlen($suffix)
            && strcasecmp(substr($fileName, -strlen($suffix)), $suffix) === 0) {
            $candidate = substr($fileName, 0, -strlen($suffix));
            if (self::isValid($candidate)) {
                return $candidate;
            }
        }

        // Legacy records whose slug was already a valid Xiuno directory remain usable.
        $slug = trim((string)($plugin['slug'] ?? ''));
        return self::isValid($slug) ? $slug : '';
    }
}
