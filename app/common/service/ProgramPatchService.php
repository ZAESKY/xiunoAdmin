<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Db;

/**
 * 与主题版本绑定、但独立分发的 Xiuno 主程序补丁。
 *
 * 补丁包必须包含 _zaesky_patch.json，其他条目必须全部是声明过的主程序目标文件。
 * 包、清单与目标原文哈希分别校验，主题 release 包不会夹带这些文件。
 */
class ProgramPatchService
{
    public const META_ENTRY = '_zaesky_patch.json';

    public static function publishForVersion(array $version, string $zipPath): array
    {
        if ((int)($version['type'] ?? -1) !== 0) {
            return ['ok' => false, 'published' => false, 'msg' => '程序补丁只能绑定安装包版本'];
        }
        $productId = LicenseService::productIdForApp((int)($version['appid'] ?? 0));
        if ($productId === '') {
            return ['ok' => false, 'published' => false, 'msg' => '该应用未映射授权产品'];
        }
        if (!CryptoService::configured(CryptoService::PURPOSE_RELEASE)) {
            return ['ok' => false, 'published' => false, 'msg' => '发布签名密钥未配置'];
        }

        $metaResult = self::readMetadata($zipPath);
        if (!$metaResult['ok']) {
            return ['ok' => false, 'published' => false, 'msg' => $metaResult['msg']];
        }
        $meta = $metaResult['meta'];
        $buildNo = (int)($version['version'] ?? 0);
        $edition = trim((string)($version['edition'] ?? ''));
        if ((string)($meta['product_id'] ?? '') !== $productId
            || (int)($meta['theme_build'] ?? 0) !== $buildNo
            || (string)($meta['theme_edition'] ?? '') !== $edition) {
            return ['ok' => false, 'published' => false, 'msg' => '程序补丁绑定的产品或主题版本与当前版本记录不一致'];
        }
        $patchId = (string)($meta['patch_id'] ?? '');
        $revision = (int)($meta['revision'] ?? 0);
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $patchId) || $revision <= 0) {
            return ['ok' => false, 'published' => false, 'msg' => '程序补丁 ID 或 revision 无效'];
        }
        if ((int)($meta['level'] ?? 0) !== 2) {
            return ['ok' => false, 'published' => false, 'msg' => '版本关联的程序补丁必须使用 Level 2 根目录替换模式'];
        }

        $built = ManifestService::build($zipPath, [
            'kind'            => 'patch',
            'product_id'      => $productId,
            'patch_id'        => $patchId,
            'revision'        => $revision,
            'level'           => 2,
            'theme_build'     => $buildNo,
            'theme_edition'   => $edition,
            'expect_sha256'   => (array)($meta['expect_sha256'] ?? []),
            'min_bbs_version' => self::shortVersion($meta['min_bbs_version'] ?? ''),
            'max_bbs_version' => self::shortVersion($meta['max_bbs_version'] ?? ''),
            'min_php_version' => self::shortVersion($meta['min_php_version'] ?? ''),
        ]);
        if (empty($built['ok'])) {
            return ['ok' => false, 'published' => false, 'msg' => (string)$built['msg']];
        }
        $manifest = $built['manifest'];

        try {
            $signed = ManifestService::sign($manifest);
            $keys = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
            $public = $keys[$signed['key_id']] ?? '';
            if ($public === '' || !ManifestService::verify($manifest, $signed['sig'], $public)) {
                return ['ok' => false, 'published' => false, 'msg' => '程序补丁清单签名自检失败'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'published' => false, 'msg' => '程序补丁签名失败'];
        }

        $safeProduct = preg_replace('/[^A-Za-z0-9_.-]/', '_', $productId);
        $fileName = sprintf('%s_b%d_%s_r%d_%s.zip', $safeProduct, $buildNo, $patchId, $revision, substr($manifest['package_sha256'], 0, 12));
        $storage = new PluginStorageService();
        $storageDriver = 'local';
        $objectKey = '';
        $destDir = APP_PATH . DS . 'common' . DS . 'download' . DS . 'patch' . DS;
        $target = $destDir.$fileName;
        $targetExisted = false;
        if ($storage->isOssEnabled()) {
            try {
                $stored = $storage->storePath($zipPath, 'program_patch', $fileName, 'application/zip');
                $storageDriver = 'oss';
                $objectKey = (string)$stored['object_key'];
            } catch (\Throwable $e) {
                return ['ok' => false, 'published' => false, 'msg' => '程序补丁上传 OSS 失败：'.$e->getMessage()];
            }
        } else {
            if (is_link($destDir) || (!is_dir($destDir) && !@mkdir($destDir, 0750, true)) || !is_dir($destDir)) {
                return ['ok' => false, 'published' => false, 'msg' => '无法创建程序补丁分发目录'];
            }
            if (is_link($target)) {
                return ['ok' => false, 'published' => false, 'msg' => '程序补丁发布目标不安全'];
            }
            $targetExisted = is_file($target);
            if (!$targetExisted) {
                $temp = $destDir.'.'.$fileName.'.'.bin2hex(random_bytes(6)).'.tmp';
                if (!@copy($zipPath, $temp)
                    || !hash_equals((string)$manifest['package_sha256'], (string)@hash_file('sha256', $temp))) {
                    if (is_file($temp)) { @unlink($temp); }
                    return ['ok' => false, 'published' => false, 'msg' => '程序补丁复制或哈希校验失败'];
                }
                @chmod($temp, 0640);
                if (!@rename($temp, $target)) {
                    @unlink($temp);
                    return ['ok' => false, 'published' => false, 'msg' => '无法原子发布程序补丁'];
                }
            } elseif (!hash_equals((string)$manifest['package_sha256'], (string)@hash_file('sha256', $target))) {
                return ['ok' => false, 'published' => false, 'msg' => '同名程序补丁文件完整性异常'];
            }
        }

        $status = !empty($version['status']) ? 1 : 0;
        $row = [
            'product_id'      => $productId,
            'patch_id'        => $patchId,
            'revision'        => $revision,
            'level'           => 2,
            'theme_build'     => $buildNo,
            'theme_edition'   => $edition,
            'title'           => self::textLimit(trim((string)($meta['title'] ?? $patchId)), 128),
            'description'     => self::textLimit(trim((string)($meta['description'] ?? '')), 512),
            'package_file'    => $fileName,
            'storage_driver'  => $storageDriver,
            'package_object_key' => $objectKey,
            'package_sha256'  => (string)$manifest['package_sha256'],
            'package_size'    => (int)$manifest['package_size'],
            'manifest_json'   => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'manifest_sig'    => (string)$signed['sig'],
            'sig_key_id'      => (string)$signed['key_id'],
            'min_bbs_version' => (string)$manifest['min_bbs_version'],
            'max_bbs_version' => (string)$manifest['max_bbs_version'],
            'min_php_version' => (string)$manifest['min_php_version'],
            'status'          => $status,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        try {
            $exists = Db::name('patch')
                ->where('product_id', $productId)
                ->where('theme_build', $buildNo)
                ->where('patch_id', $patchId)
                ->where('revision', $revision)
                ->find();
            $oldFile = is_array($exists) ? (string)($exists['package_file'] ?? '') : '';
            $oldDriver = is_array($exists) ? (string)($exists['storage_driver'] ?? 'local') : 'local';
            $oldObjectKey = is_array($exists) ? (string)($exists['package_object_key'] ?? '') : '';
            if ($exists) {
                Db::name('patch')->where('id', (int)$exists['id'])->update($row);
            } else {
                Db::name('patch')->insert($row);
            }
            if ($oldDriver === 'oss' && $oldObjectKey !== '' && $oldObjectKey !== $objectKey) {
                self::deleteObjectIfUnreferenced($oldObjectKey);
            } elseif ($oldDriver === 'local' && $oldFile !== '' && $oldFile !== $fileName) {
                self::unlinkIfUnreferenced($oldFile);
            }
        } catch (\Throwable $e) {
            if ($storageDriver === 'oss' && $objectKey !== '') {
                $storage->deleteObject($objectKey);
            } elseif (!$targetExisted) {
                self::unlinkIfUnreferenced($fileName);
            }
            return ['ok' => false, 'published' => false, 'msg' => '程序补丁发布记录写入失败'];
        }

        return [
            'ok' => true,
            'published' => $status === 1,
            'msg' => $status ? '程序补丁已签名并发布' : '程序补丁已签名保存，版本启用后发布',
            'patch_id' => $patchId,
            'revision' => $revision,
            'sha256' => (string)$manifest['package_sha256'],
        ];
    }

    public static function statusForVersion(array $version): ?array
    {
        $productId = LicenseService::productIdForApp((int)($version['appid'] ?? 0));
        $buildNo = (int)($version['version'] ?? 0);
        $edition = trim((string)($version['edition'] ?? ''));
        if ($productId === '' || $buildNo <= 0 || $edition === '') { return null; }
        $query = Db::name('patch')->where('product_id', $productId)
            ->where('theme_build', $buildNo)->where('theme_edition', $edition);
        $count = (int)(clone $query)->count();
        $row = $query->order('revision', 'desc')->order('id', 'desc')->find();
        if (!$row) { return null; }
        return [
            'patch_id' => (string)$row['patch_id'],
            'revision' => (int)$row['revision'],
            'status' => (int)$row['status'],
            'title' => (string)$row['title'],
            'package_size' => (int)$row['package_size'],
            'count' => $count,
        ];
    }

    public static function syncStatus(array $version): void
    {
        $productId = LicenseService::productIdForApp((int)($version['appid'] ?? 0));
        $buildNo = (int)($version['version'] ?? 0);
        $edition = trim((string)($version['edition'] ?? ''));
        if ($productId === '' || $buildNo <= 0 || $edition === '') { return; }
        Db::name('patch')->where('product_id', $productId)->where('theme_build', $buildNo)->where('theme_edition', $edition)
            ->update(['status' => !empty($version['status']) ? 1 : 0]);
    }

    public static function deleteForVersion(array $version): int
    {
        $productId = LicenseService::productIdForApp((int)($version['appid'] ?? 0));
        $buildNo = (int)($version['version'] ?? 0);
        $edition = trim((string)($version['edition'] ?? ''));
        if ($productId === '' || $buildNo <= 0 || $edition === '') { return 0; }
        $selected = Db::name('patch')->where('product_id', $productId)->where('theme_build', $buildNo)
            ->where('theme_edition', $edition)->select();
        $rows = is_object($selected) && method_exists($selected, 'toArray') ? $selected->toArray() : (array)$selected;
        $files = [];
        $objects = [];
        foreach ($rows as $row) {
            if (($row['storage_driver'] ?? 'local') === 'oss' && !empty($row['package_object_key'])) {
                $objects[(string)$row['package_object_key']] = true;
                continue;
            }
            $name = (string)($row['package_file'] ?? '');
            if (preg_match('/^[A-Za-z0-9_.-]{1,160}$/D', $name)) {
                $files[$name] = true;
            }
        }
        $deleted = Db::name('patch')->where('product_id', $productId)->where('theme_build', $buildNo)
            ->where('theme_edition', $edition)->delete();
        foreach (array_keys($files) as $name) {
            self::unlinkIfUnreferenced($name);
        }
        foreach (array_keys($objects) as $objectKey) {
            self::deleteObjectIfUnreferenced($objectKey);
        }
        return $deleted;
    }

    private static function readMetadata(string $zipPath): array
    {
        if (!is_file($zipPath) || !class_exists('ZipArchive')) {
            return ['ok' => false, 'msg' => '程序补丁 ZIP 不存在或服务器缺少 ZipArchive', 'meta' => []];
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'msg' => '无法打开程序补丁 ZIP', 'meta' => []];
        }
        $raw = $zip->getFromName(self::META_ENTRY);
        $zip->close();
        if (!is_string($raw) || strlen($raw) < 2 || strlen($raw) > 65536) {
            return ['ok' => false, 'msg' => '程序补丁缺少有效的 '.self::META_ENTRY, 'meta' => []];
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta) || (int)($meta['schema_version'] ?? 0) !== 1
            || (string)($meta['kind'] ?? '') !== 'program_patch'
            || !isset($meta['expect_sha256']) || !is_array($meta['expect_sha256'])) {
            return ['ok' => false, 'msg' => '程序补丁元数据格式无效', 'meta' => []];
        }
        return ['ok' => true, 'msg' => '', 'meta' => $meta];
    }

    private static function shortVersion($value): string
    {
        $value = trim((string)$value);
        return strlen($value) <= 32 && preg_match('/^[0-9A-Za-z.+_-]*$/D', $value) ? $value : '';
    }

    private static function textLimit(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return (string)mb_substr($value, 0, $limit, 'UTF-8');
        }
        return substr($value, 0, $limit);
    }

    /** 仅当没有其它补丁记录引用时才删除内容寻址包，避免跨版本误删。 */
    private static function unlinkIfUnreferenced(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,160}$/D', $name)) {
            return;
        }
        try {
            if ((int)Db::name('patch')->where('package_file', $name)->count() > 0) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        $base = APP_PATH . DS . 'common' . DS . 'download' . DS . 'patch' . DS;
        $file = $base.$name;
        if (is_file($file) && !is_link($file)) {
            @unlink($file);
        }
    }

    private static function deleteObjectIfUnreferenced(string $objectKey): void
    {
        try {
            if ((int)Db::name('patch')->where('package_object_key', $objectKey)->count() > 0) {
                return;
            }
            (new PluginStorageService())->deleteObject($objectKey);
        } catch (\Throwable $e) {
        }
    }
}
