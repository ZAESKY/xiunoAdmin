<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Db;

/**
 * 应用级公开引导安装包。
 *
 * 该包只用于“源码下载”入口，不能放完整受保护资源。下载仍要求通过现有
 * 授权身份校验并使用一次性票据，但不会向包内注入授权码或其它秘密。
 */
class ApplicationInstallerService
{
    public const FILE_NAME = 'installer.zip';
    public const MAX_BYTES = 430000000;

    public static function storeUploadedFile(int $appId, $file): array
    {
        $app = Db::name('app')->where('id', $appId)->find();
        if (empty($app)) {
            throw new \RuntimeException('应用不存在');
        }

        $source = is_object($file) && method_exists($file, 'getPathname')
            ? (string)$file->getPathname() : '';
        $original = is_object($file) && method_exists($file, 'getOriginalName')
            ? (string)$file->getOriginalName() : '';
        $size = $source !== '' && is_file($source) ? (int)filesize($source) : 0;
        if ($source === '' || !is_file($source) || is_link($source) || $size < 1 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('安装包不存在或大小超出限制');
        }
        if (strtolower((string)pathinfo($original, PATHINFO_EXTENSION)) !== 'zip') {
            throw new \RuntimeException('只允许上传 ZIP 安装包');
        }

        $dir = self::appDir($app);
        if ($dir === '') {
            throw new \RuntimeException('应用下载目录无效');
        }
        $temp = $dir . '.installer_' . bin2hex(random_bytes(8)) . '.tmp';
        if (!@copy($source, $temp) || !is_file($temp)) {
            throw new \RuntimeException('保存安装包失败');
        }
        @chmod($temp, 0640);

        try {
            $problem = SafeZipService::validate(
                $temp,
                ManifestService::ALLOWED_EXT,
                20000,
                2147483648
            );
            if ($problem !== null) {
                throw new \RuntimeException($problem);
            }
            self::assertReleaseEntryPolicy($temp);

            $sha256 = (string)hash_file('sha256', $temp);
            if (!preg_match('/^[a-f0-9]{64}$/D', $sha256)) {
                throw new \RuntimeException('无法计算安装包哈希');
            }

            $storage = new PluginStorageService();
            if ($storage->isOssEnabled()) {
                $stored = $storage->storePath($temp, 'application_installer', $original, 'application/zip');
                $safeName = self::safeDownloadName($original, $appId);
                $now = date('Y-m-d H:i:s');
                $oldDriver = (string)($app['installer_storage_driver'] ?? 'local');
                $oldObjectKey = (string)($app['installer_object_key'] ?? '');
                try {
                    $updated = Db::name('app')->where('id', $appId)->update([
                        'installer_storage_driver' => 'oss',
                        'installer_object_key'    => (string)$stored['object_key'],
                        'installer_file_name'     => $safeName,
                        'installer_sha256'        => $sha256,
                        'installer_size'          => (int)$stored['file_size'],
                        'installer_uploaded_at'   => $now,
                    ]);
                    if ((int)$updated !== 1) {
                        throw new \RuntimeException('应用状态已变化');
                    }
                } catch (\Throwable $e) {
                    $storage->deleteObject((string)$stored['object_key']);
                    throw new \RuntimeException('安装包元数据保存失败');
                }

                if ($oldDriver === 'oss' && $oldObjectKey !== '' && $oldObjectKey !== $stored['object_key']) {
                    $storage->deleteObject($oldObjectKey);
                } elseif ($oldDriver === 'local') {
                    $oldLocal = $dir . self::FILE_NAME;
                    if (is_file($oldLocal) && !is_link($oldLocal)) {
                        @unlink($oldLocal);
                    }
                }

                return [
                    'file_name' => $safeName,
                    'sha256' => $sha256,
                    'size' => (int)$stored['file_size'],
                    'uploaded_at' => $now,
                    'storage_driver' => 'oss',
                ];
            }

            $target = $dir . self::FILE_NAME;
            $backup = $dir . '.installer_backup_' . bin2hex(random_bytes(6)) . '.zip';
            $hadOld = is_file($target) && !is_link($target);
            if ($hadOld && !@rename($target, $backup)) {
                throw new \RuntimeException('无法备份旧安装包');
            }
            if (!@rename($temp, $target)) {
                if ($hadOld && is_file($backup)) {
                    @rename($backup, $target);
                }
                throw new \RuntimeException('无法原子替换安装包');
            }
            $storedSize = (int)filesize($target);
            if ($storedSize !== $size
                || !hash_equals($sha256, (string)hash_file('sha256', $target))) {
                self::restorePreviousFile($target, $backup, $hadOld);
                throw new \RuntimeException('安装包写入后校验失败');
            }

            $safeName = self::safeDownloadName($original, $appId);
            $now = date('Y-m-d H:i:s');
            try {
                $updated = Db::name('app')->where('id', $appId)->update([
                    'installer_storage_driver' => 'local',
                    'installer_object_key'    => '',
                    'installer_file_name'   => $safeName,
                    'installer_sha256'      => $sha256,
                    'installer_size'        => $storedSize,
                    'installer_uploaded_at' => $now,
                ]);
                if ((int)$updated !== 1) {
                    throw new \RuntimeException('应用状态已变化');
                }
            } catch (\Throwable $e) {
                self::restorePreviousFile($target, $backup, $hadOld);
                throw new \RuntimeException('安装包元数据保存失败');
            }
            if ($hadOld && is_file($backup) && !is_link($backup)) {
                @unlink($backup);
            }

            return [
                'file_name'   => $safeName,
                'sha256'      => $sha256,
                'size'        => $storedSize,
                'uploaded_at' => $now,
            ];
        } finally {
            if (is_file($temp) && !is_link($temp)) {
                @unlink($temp);
            }
        }
    }

    public static function issueDownloadTicket(int $appId, int $authId, string $ip): array
    {
        $app = Db::name('app')->where('id', $appId)->where('status', 2)->find();
        if (empty($app) || !self::available($app)) {
            return ['ok' => false, 'msg' => '此应用尚未上传公开安装包'];
        }
        if ($authId <= 0) {
            return ['ok' => false, 'msg' => '授权身份无效'];
        }
        $ticket = SecureTicketService::issue(
            ['kind' => 'app_installer', 'appid' => $appId, 'auth_id' => $authId],
            ['appid' => $appId, 'auth_id' => $authId, 'ip' => $ip]
        );
        return ['ok' => true, 'ticket' => $ticket];
    }

    public static function file(array $app): string
    {
        $dir = self::appDir($app);
        if ($dir === '') {
            return '';
        }
        $file = $dir . self::FILE_NAME;
        if (!is_file($file) || is_link($file)) {
            return '';
        }
        $real = realpath($file);
        if ($real === false || !str_starts_with($real, $dir)) {
            return '';
        }
        $expected = strtolower(trim((string)($app['installer_sha256'] ?? '')));
        $actual = (string)@hash_file('sha256', $real);
        if (!preg_match('/^[a-f0-9]{64}$/D', $expected)
            || !preg_match('/^[a-f0-9]{64}$/D', $actual)
            || !hash_equals($expected, $actual)) {
            return '';
        }
        $expectedSize = (int)($app['installer_size'] ?? 0);
        if ($expectedSize <= 0 || (int)@filesize($real) !== $expectedSize) {
            return '';
        }
        return $real;
    }

    /** Local path or short-lived OSS URL after metadata validation. */
    public static function location(array $app): string
    {
        $driver = (string)($app['installer_storage_driver'] ?? 'local');
        if ($driver === 'oss') {
            $key = (string)($app['installer_object_key'] ?? '');
            $sha256 = strtolower((string)($app['installer_sha256'] ?? ''));
            $size = (int)($app['installer_size'] ?? 0);
            if (!preg_match('/^[a-f0-9]{64}$/D', $sha256) || $size <= 0 || $key === '') {
                return '';
            }
            try {
                return (new PluginStorageService())->downloadToTemporaryFile($key, $sha256, $size);
            } catch (\Throwable $e) {
                return '';
            }
        }
        if ($driver !== 'local') {
            return '';
        }
        return self::file($app);
    }

    public static function available(array $app): bool
    {
        if (($app['installer_storage_driver'] ?? 'local') === 'oss') {
            $key = (string)($app['installer_object_key'] ?? '');
            $hash = strtolower((string)($app['installer_sha256'] ?? ''));
            $size = (int)($app['installer_size'] ?? 0);
            if ($key === '' || !preg_match('/^[a-f0-9]{64}$/D', $hash) || $size <= 0) {
                return false;
            }
            try {
                (new PluginStorageService())->objectDownloadUrl($key, 30);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }
        return self::file($app) !== '';
    }

    public static function downloadName(array $app): string
    {
        return self::safeDownloadName((string)($app['installer_file_name'] ?? ''), (int)($app['id'] ?? 0));
    }

    private static function appDir(array $app): string
    {
        $token = trim((string)($app['download_file'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $token)) {
            return '';
        }
        $root = APP_PATH . DS . 'common' . DS . 'download';
        $candidate = $root . DS . $token;
        if (!is_dir($candidate) || is_link($candidate)) {
            return '';
        }
        $realRoot = realpath($root);
        $realDir = realpath($candidate);
        if ($realRoot === false || $realDir === false
            || dirname($realDir) !== $realRoot
            || !str_starts_with($realDir . DIRECTORY_SEPARATOR, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return '';
        }
        return rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private static function safeDownloadName(string $name, int $appId): string
    {
        $base = basename(str_replace('\\', '/', trim($name)));
        $base = preg_replace('/[^A-Za-z0-9_.-]/', '_', $base);
        if ($base === '' || strtolower((string)pathinfo($base, PATHINFO_EXTENSION)) !== 'zip') {
            $base = 'app_' . max(0, $appId) . '_installer.zip';
        }
        return substr($base, 0, 180);
    }

    private static function assertReleaseEntryPolicy(string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('无法打开安装包');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $problem = ManifestService::entryProblem($name);
                if ($problem !== '') {
                    throw new \RuntimeException('安装包包含不允许的条目：' . basename($name));
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function restorePreviousFile(string $target, string $backup, bool $hadOld): void
    {
        if (is_file($target) && !is_link($target)) {
            @unlink($target);
        }
        if ($hadOld && is_file($backup) && !is_link($backup)) {
            @rename($backup, $target);
        }
    }
}
