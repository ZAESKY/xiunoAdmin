<?php

namespace app\common\service;

/**
 * 完整发布包路径解析（统一入口）
 *
 * 修复 A-23：AuthService 计算 filesize 时未做目录名清洗且把 type 硬编码为 update，
 * 与 DownloadService 的解析规则不一致。此处收敛为单一 release 目录实现，两侧共用。
 *
 * @since 2026-08-16 P0 安全加固
 */
class ReleasePackageService
{
    /** 包文件固定名 */
    public const PACKAGE_NAME = 'QH.zip';

    /**
     * 目录名清洗：只允许字母、数字、下划线、连字符，长度 1-120。
     * 不合规返回空串，调用方必须据此拒绝，绝不可回退到未清洗值。
     */
    public static function normalizeCatalogue($name): string
    {
        $name = trim((string)$name);
        return preg_match('/^[A-Za-z0-9_-]{1,120}$/', $name) ? $name : '';
    }

    /**
     * 保留参数只为兼容旧调用签名；所有版本包统一进入 release 目录。
     */
    public static function subDir($type): string
    {
        return 'release';
    }

    /**
     * 解析包所在目录（带结尾分隔符）。目录名不合规时返回空串。
     */
    public static function dir($type, $catalogue): string
    {
        $catalogue = self::normalizeCatalogue($catalogue);
        if ($catalogue === '') {
            return '';
        }
        return APP_PATH . DS . 'common' . DS . 'download' . DS
            . self::subDir($type) . DS . $catalogue . DS;
    }

    /**
     * Resolve an existing package directory and prove that it is a real child
     * of the expected release root. Symlinks are deliberately rejected:
     * package upload and deletion must never escape through a filesystem link.
     */
    public static function existingDir($type, $catalogue): string
    {
        $dir = self::dir($type, $catalogue);
        if ($dir === '') {
            return '';
        }

        $base = APP_PATH . DS . 'common' . DS . 'download' . DS . self::subDir($type);
        $candidate = rtrim($dir, DS);
        if (is_link($candidate) || !is_dir($candidate)) {
            return '';
        }

        $realBase = realpath($base);
        $realDir = realpath($candidate);
        if ($realBase === false || $realDir === false) {
            return '';
        }

        $prefix = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realDir . DIRECTORY_SEPARATOR, $prefix) || $realDir === $realBase) {
            return '';
        }

        return rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * 解析包文件绝对路径。不合规或不存在时返回空串。
     */
    public static function file($type, $catalogue): string
    {
        $dir = self::existingDir($type, $catalogue);
        if ($dir === '') {
            return '';
        }
        $file = $dir . self::PACKAGE_NAME;
        if (is_link($file) || !is_file($file)) {
            return '';
        }

        $realFile = realpath($file);
        if ($realFile === false || !str_starts_with($realFile, $dir)) {
            return '';
        }
        return $realFile;
    }

    /**
     * 包体积（MB，保留两位小数）。文件缺失时返回 0，不抛异常。
     */
    public static function sizeMb($type, $catalogue): float
    {
        $file = self::file($type, $catalogue);
        if ($file === '') {
            return 0.0;
        }
        $bytes = @filesize($file);
        if ($bytes === false || $bytes <= 0) {
            return 0.0;
        }
        return round($bytes / 1048576 * 100) / 100;
    }

    /**
     * 包 SHA-256。供后续 P3 发布管线与清单签名使用；文件缺失返回空串。
     */
    public static function sha256($type, $catalogue): string
    {
        $file = self::file($type, $catalogue);
        if ($file === '') {
            return '';
        }
        $hash = @hash_file('sha256', $file);
        return is_string($hash) ? $hash : '';
    }
}
