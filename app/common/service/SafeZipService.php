<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * Structural ZIP validation performed before any extraction.
 */
class SafeZipService
{
    public static function validate(
        string $zipPath,
        array $allowedExtensions = [],
        int $maxEntries = 10000,
        int $maxUncompressedBytes = 536870912
    ): ?string {
        if (!is_file($zipPath) || !class_exists('ZipArchive')) {
            return '压缩包不存在或服务器未安装 ZIP 扩展';
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return '无法打开压缩包';
        }

        $allowedExtensions = array_map('strtolower', $allowedExtensions);
        $totalSize = 0;
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > $maxEntries) {
                return '压缩包文件数量超出限制';
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                $problem = self::entryProblem($name);
                if ($problem !== null) {
                    return '压缩包包含不安全路径：' . $problem;
                }

                $isDirectory = str_ends_with($name, '/');
                if (!$isDirectory && $allowedExtensions !== []) {
                    $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
                    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
                        return '压缩包包含不允许的文件类型：' . basename($name);
                    }
                }

                $stat = $zip->statIndex($i);
                $size = max(0, (int)($stat['size'] ?? 0));
                $totalSize += $size;
                if ($totalSize > $maxUncompressedBytes) {
                    return '压缩包解压后体积超出限制';
                }

                if (method_exists($zip, 'getExternalAttributesIndex')) {
                    $opsys = 0;
                    $attributes = 0;
                    if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                        $mode = ($attributes >> 16) & 0170000;
                        if ($mode === 0120000) {
                            return '压缩包不能包含符号链接：' . basename($name);
                        }
                    }
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    private static function entryProblem(string $name): ?string
    {
        if ($name === '' || strlen($name) > 240 || str_contains($name, "\0")) {
            return $name === '' ? '(空路径)' : basename($name);
        }
        if (str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            return $name;
        }
        foreach (explode('/', rtrim($name, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return $name;
            }
        }
        return null;
    }
}
