<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Db;

/**
 * 把版本列表中的“安装包”(type=0)安全发布到 v2 授权更新通道。
 */
class VersionReleaseService
{
    public static function publishInstaller(array $version, string $zipPath): array
    {
        if ((int)($version['type'] ?? -1) !== 0) {
            return ['ok' => true, 'published' => false, 'msg' => '更新包沿用旧版更新通道'];
        }
        $appId = (int)($version['appid'] ?? 0);
        $productId = LicenseService::productIdForApp($appId);
        if ($productId === '') {
            return ['ok' => true, 'published' => false, 'msg' => '该应用未映射 v2 产品，仅保存版本安装包'];
        }
        if (!CryptoService::configured(CryptoService::PURPOSE_RELEASE)) {
            return ['ok' => false, 'published' => false, 'msg' => '发布签名密钥未配置，安装包未发布'];
        }

        $buildNo = (int)($version['version'] ?? 0);
        $edition = trim((string)($version['edition'] ?? ''));
        if ($buildNo <= 0 || $edition === '') {
            return ['ok' => false, 'published' => false, 'msg' => '版本号或版本名称无效'];
        }
        $channel = !empty($version['beta']) ? LicenseService::CHANNEL_BETA : LicenseService::CHANNEL_STABLE;
        $built = ManifestService::build($zipPath, [
            'kind'            => 'release',
            'product_id'      => $productId,
            'build_no'        => $buildNo,
            'edition'         => $edition,
            'channel'         => $channel,
            'min_bbs_version' => '',
            'max_bbs_version' => '',
            'min_php_version' => '',
        ]);
        if (empty($built['ok'])) {
            return ['ok' => false, 'published' => false, 'msg' => (string)$built['msg']];
        }
        $manifest = $built['manifest'];

        try {
            $signed = ManifestService::sign($manifest);
            $publicKeys = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
            $public = $publicKeys[$signed['key_id']] ?? '';
            if ($public === '' || !ManifestService::verify($manifest, $signed['sig'], $public)) {
                return ['ok' => false, 'published' => false, 'msg' => '安装包清单签名自检失败'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'published' => false, 'msg' => '安装包签名失败'];
        }

        $safeProduct = preg_replace('/[^A-Za-z0-9_.-]/', '_', $productId);
        $fileName = sprintf('%s_%d_%s.zip', $safeProduct, $buildNo, substr($manifest['package_sha256'], 0, 12));
        $storage = new PluginStorageService();
        $storageDriver = 'local';
        $objectKey = '';
        $destDir = APP_PATH . DS . 'common' . DS . 'download' . DS . 'v2' . DS;
        $target = $destDir . $fileName;
        $targetExisted = false;

        if ($storage->isOssEnabled()) {
            try {
                $stored = $storage->storePath($zipPath, 'application_release', $fileName, 'application/zip');
                $storageDriver = 'oss';
                $objectKey = (string)$stored['object_key'];
            } catch (\Throwable $e) {
                return ['ok' => false, 'published' => false, 'msg' => '发布包上传 OSS 失败：' . $e->getMessage()];
            }
        } else {
            if ((is_link($destDir) || (!is_dir($destDir) && !@mkdir($destDir, 0755, true))) || !is_dir($destDir)) {
                return ['ok' => false, 'published' => false, 'msg' => '无法创建 v2 发布目录'];
            }
            if (is_link($target)) {
                return ['ok' => false, 'published' => false, 'msg' => '发布目标文件不安全'];
            }
            $targetExisted = is_file($target);
            if ($targetExisted) {
                if (!hash_equals((string)$manifest['package_sha256'], (string)@hash_file('sha256', $target))) {
                    return ['ok' => false, 'published' => false, 'msg' => '发布目标已存在但完整性校验失败'];
                }
            } else {
                $temp = $destDir . '.' . $fileName . '.' . bin2hex(random_bytes(6)) . '.tmp';
                if (!@copy($zipPath, $temp)
                    || !is_file($temp)
                    || !hash_equals((string)$manifest['package_sha256'], (string)@hash_file('sha256', $temp))) {
                    if (is_file($temp)) {
                        @unlink($temp);
                    }
                    return ['ok' => false, 'published' => false, 'msg' => '复制安装包或复制后哈希校验失败'];
                }
                @chmod($temp, 0640);
                if (!@rename($temp, $target)) {
                    @unlink($temp);
                    return ['ok' => false, 'published' => false, 'msg' => '无法原子发布安装包'];
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        $status = !empty($version['status']) ? 1 : 0;
        $row = [
            'product_id'      => $productId,
            'build_no'        => $buildNo,
            'edition'         => $edition,
            'channel'         => $channel,
            'package_file'    => $fileName,
            'storage_driver'  => $storageDriver,
            'package_object_key' => $objectKey,
            'package_sha256'  => (string)$manifest['package_sha256'],
            'package_size'    => (int)$manifest['package_size'],
            'manifest_json'   => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'manifest_sig'    => (string)$signed['sig'],
            'sig_key_id'      => (string)$signed['key_id'],
            'min_bbs_version' => '',
            'max_bbs_version' => '',
            'min_php_version' => '',
            'is_security'     => 0,
            'release_note'    => (string)($version['update_log'] ?? ''),
            'status'          => $status,
            'published_at'    => $status ? $now : null,
            'created_at'      => $now,
        ];

        try {
            $exists = Db::name('release')
                ->where('product_id', $productId)
                ->where('build_no', $buildNo)
                ->find();
            $oldFile = is_array($exists) ? (string)($exists['package_file'] ?? '') : '';
            $oldDriver = is_array($exists) ? (string)($exists['storage_driver'] ?? 'local') : 'local';
            $oldObjectKey = is_array($exists) ? (string)($exists['package_object_key'] ?? '') : '';
            if ($exists) {
                Db::name('release')->where('id', (int)$exists['id'])->update($row);
                $releaseId = (int)$exists['id'];
            } else {
                $releaseId = (int)Db::name('release')->insertGetId($row);
            }
            if ($oldDriver === 'oss' && $oldObjectKey !== '' && $oldObjectKey !== $objectKey) {
                $storage->deleteObject($oldObjectKey);
            } elseif ($oldDriver === 'local' && $oldFile !== '' && $oldFile !== $fileName && preg_match('/^[A-Za-z0-9_.-]{1,160}$/D', $oldFile)) {
                $oldPath = $destDir . $oldFile;
                if (is_file($oldPath) && !is_link($oldPath)) {
                    @unlink($oldPath);
                }
            }
        } catch (\Throwable $e) {
            if ($storageDriver === 'oss' && $objectKey !== '') {
                $storage->deleteObject($objectKey);
            } elseif (!$targetExisted && is_file($target) && !is_link($target)) {
                @unlink($target);
            }
            return ['ok' => false, 'published' => false, 'msg' => '安装包发布记录写入失败'];
        }

        $deltaResult = ['generated' => false, 'msg' => '未生成差分包'];
        try {
            $savedRelease = Db::name('release')->where('id', $releaseId)->find();
            if (is_array($savedRelease)) {
                $deltaResult = DeltaReleaseService::buildForRelease($savedRelease, $zipPath);
            }
        } catch (\Throwable $e) {
            // 差分只是传输优化，失败不得撤销已经完成签名和落库的完整包。
            $deltaResult = ['generated' => false, 'msg' => '差分生成异常，本次使用完整包'];
        }

        $publishMessage = $status === 1 ? '安装包已签名并发布到授权下载通道' : '安装包已签名保存，版本启用后发布';
        $deltaMessage = trim((string)($deltaResult['msg'] ?? ''));
        if ($deltaMessage !== '') {
            $publishMessage .= '；'.$deltaMessage;
        }

        return [
            'ok'         => true,
            'published'  => $status === 1,
            'msg'        => $publishMessage,
            'release_id' => $releaseId,
            'sha256'     => (string)$manifest['package_sha256'],
            'delta'      => $deltaResult,
        ];
    }

    public static function syncStatus(array $version): void
    {
        if ((int)($version['type'] ?? -1) !== 0) {
            return;
        }
        $productId = LicenseService::productIdForApp((int)($version['appid'] ?? 0));
        $buildNo = (int)($version['version'] ?? 0);
        if ($productId === '' || $buildNo <= 0) {
            return;
        }
        $status = !empty($version['status']) ? 1 : 0;
        Db::name('release')
            ->where('product_id', $productId)
            ->where('build_no', $buildNo)
            ->update([
                'status'       => $status,
                'published_at' => $status ? date('Y-m-d H:i:s') : null,
                'release_note' => (string)($version['update_log'] ?? ''),
            ]);
        try {
            DeltaReleaseService::syncStatus($productId, $buildNo, $status);
        } catch (\Throwable $e) {
            // 尚未执行差分表迁移的环境继续保持完整包通道可用。
        }
    }

    public static function withdraw(array $version): void
    {
        $productId = LicenseService::productIdForApp((int)($version['appid'] ?? 0));
        $buildNo = (int)($version['version'] ?? 0);
        if ($productId === '' || $buildNo <= 0) {
            return;
        }
        Db::name('release')
            ->where('product_id', $productId)
            ->where('build_no', $buildNo)
            ->update(['status' => 0, 'published_at' => null]);
        try {
            DeltaReleaseService::syncStatus($productId, $buildNo, 0);
        } catch (\Throwable $e) {
        }
    }

    public static function deleteForVersion(array $version): void
    {
        $productId = LicenseService::productIdForApp((int)($version['appid'] ?? 0));
        $buildNo = (int)($version['version'] ?? 0);
        if ($productId === '' || $buildNo <= 0) {
            return;
        }
        $row = Db::name('release')
            ->where('product_id', $productId)
            ->where('build_no', $buildNo)
            ->find();
        if (!$row) {
            return;
        }
        Db::name('release')->where('id', (int)$row['id'])->delete();
        try {
            DeltaReleaseService::deleteForRelease($productId, $buildNo);
        } catch (\Throwable $e) {
            // 完整版本删除流程不能被缺失的增量迁移阻断。
        }
        if (($row['storage_driver'] ?? 'local') === 'oss') {
            (new PluginStorageService())->deleteObject((string)($row['package_object_key'] ?? ''));
            return;
        }
        $name = (string)($row['package_file'] ?? '');
        if (preg_match('/^[A-Za-z0-9_.-]{1,160}$/D', $name)) {
            $path = APP_PATH . DS . 'common' . DS . 'download' . DS . 'v2' . DS . $name;
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
        }
    }
}
