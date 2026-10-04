<?php

namespace app\common\service;

use think\Exception;
use think\facade\Db;

/**
 * Tracks plugin packages between the upload request and the publish request.
 *
 * Package metadata submitted by a browser is never trusted. The opaque token
 * is bound to the authenticated actor and resolves to metadata written by the
 * server immediately after the package was stored.
 */
class PluginPackageUploadService
{
    private const ACTOR_TYPES = ['user', 'admin'];
    private const TOKEN_TTL = 7200;

    public static function issue(string $actorType, int $actorId, array $stored): string
    {
        self::assertActor($actorType, $actorId);
        $package = self::normalizeStoredPackage($stored);
        (new PluginStorageService())->assertValidPackageRecord($package);

        $token = bin2hex(random_bytes(32));
        $now = datetime();
        try {
            Db::name('plugin_package_upload')->insert([
                'token_hash' => hash('sha256', $token),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'storage_driver' => $package['storage_driver'],
                'file_path' => $package['file_path'],
                'package_object_key' => $package['package_object_key'],
                'package_file_name' => $package['package_file_name'],
                'package_file_size' => $package['file_size'],
                'package_mime_type' => $package['package_mime_type'],
                'package_hash' => $package['file_hash'],
                'status' => 'pending',
                'consumed_plugin_id' => 0,
                'expires_at' => date('Y-m-d H:i:s', time() + self::TOKEN_TTL),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // A package without a durable upload record can never be claimed.
            // Delete it immediately so a database error cannot leak storage.
            (new PluginStorageService())->deletePackage($package);
            throw new Exception('插件包上传凭证创建失败，请重新上传');
        }

        try {
            self::cleanupExpired(20);
        } catch (\Throwable $e) {
            // Cleanup is best effort here. The scheduled command will retry.
        }
        return $token;
    }

    public static function claim(string $token, string $actorType, int $actorId): array
    {
        self::assertActor($actorType, $actorId);
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new Exception('插件包上传凭证无效，请重新上传');
        }

        $row = Db::name('plugin_package_upload')
            ->where('token_hash', hash('sha256', $token))
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('status', 'pending')
            ->find();
        if (!$row) {
            throw new Exception('插件包上传凭证无效或已使用，请重新上传');
        }
        if (strtotime((string)$row['expires_at']) < time()) {
            throw new Exception('插件包上传凭证已过期，请重新上传');
        }

        $package = self::packageFromRow($row);
        (new PluginStorageService())->assertValidPackageRecord($package);
        $package['upload_id'] = intval($row['id']);
        return $package;
    }

    /** Must be called inside the same transaction that creates the version. */
    public static function consume(int $uploadId, int $pluginId): void
    {
        if ($uploadId <= 0 || $pluginId <= 0) {
            throw new Exception('插件包上传凭证状态异常');
        }
        $now = datetime();
        $updated = Db::name('plugin_package_upload')
            ->where('id', $uploadId)
            ->where('status', 'pending')
            ->where('expires_at', '>=', $now)
            ->update([
                'status' => 'consumed',
                'consumed_plugin_id' => $pluginId,
                'consumed_at' => $now,
                'updated_at' => $now,
            ]);
        if (intval($updated) !== 1) {
            throw new Exception('插件包上传凭证已使用或已过期，请重新上传');
        }
    }

    public static function cleanupExpired(int $limit = 200): array
    {
        $limit = max(1, min(2000, $limit));
        // Recover rows left in the intermediate state by a terminated worker.
        Db::name('plugin_package_upload')
            ->where('status', 'cleaning')
            ->where('updated_at', '<', date('Y-m-d H:i:s', time() - 600))
            ->update(['status' => 'pending', 'updated_at' => datetime()]);

        $rows = Db::name('plugin_package_upload')
            ->where('status', 'pending')
            ->where('expires_at', '<', datetime())
            ->order('id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        $cleaned = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $claimed = Db::name('plugin_package_upload')
                ->where('id', intval($row['id']))
                ->where('status', 'pending')
                ->where('expires_at', '<', datetime())
                ->update(['status' => 'cleaning', 'updated_at' => datetime()]);
            if (intval($claimed) !== 1) {
                continue;
            }

            $deleted = false;
            try {
                $deleted = (new PluginStorageService())->deletePackage(self::packageFromRow($row));
            } catch (\Throwable $e) {
                $deleted = false;
            }
            if (!$deleted
                && ($row['storage_driver'] ?? 'local') === 'local'
                && !is_file((string)($row['file_path'] ?? ''))
            ) {
                // Already absent is the desired end state for a local orphan.
                $deleted = true;
            }
            if ($deleted) {
                Db::name('plugin_package_upload')->where('id', intval($row['id']))->update([
                    'status' => 'cleaned',
                    'cleaned_at' => datetime(),
                    'updated_at' => datetime(),
                ]);
                $cleaned++;
            } else {
                // Keep it retryable if OSS is temporarily unavailable.
                Db::name('plugin_package_upload')->where('id', intval($row['id']))->update([
                    'status' => 'pending',
                    'updated_at' => datetime(),
                ]);
                $failed++;
            }
        }
        return ['selected' => count($rows), 'cleaned' => $cleaned, 'failed' => $failed];
    }

    private static function assertActor(string $actorType, int $actorId): void
    {
        if (!in_array($actorType, self::ACTOR_TYPES, true) || $actorId <= 0) {
            throw new Exception('插件包上传身份无效');
        }
    }

    private static function normalizeStoredPackage(array $stored): array
    {
        $driver = (string)($stored['storage_driver'] ?? 'local');
        $hash = strtolower((string)($stored['file_hash'] ?? ''));
        $size = intval($stored['file_size'] ?? 0);
        if (!in_array($driver, ['local', 'oss'], true)
            || !preg_match('/^[a-f0-9]{64}$/D', $hash)
            || $size <= 0
            || $size > 1073741824
        ) {
            throw new Exception('插件包上传记录无效');
        }
        return [
            'storage_driver' => $driver,
            'file_path' => (string)($stored['path'] ?? $stored['file_path'] ?? ''),
            'package_object_key' => (string)($stored['object_key'] ?? $stored['package_object_key'] ?? ''),
            'package_file_name' => basename(str_replace('\\', '/', (string)($stored['file_name'] ?? $stored['package_file_name'] ?? 'plugin.zip'))),
            'package_mime_type' => (string)($stored['mime_type'] ?? $stored['package_mime_type'] ?? 'application/zip'),
            'file_hash' => $hash,
            'file_size' => $size,
        ];
    }

    private static function packageFromRow(array $row): array
    {
        return [
            'storage_driver' => (string)($row['storage_driver'] ?? 'local'),
            'file_path' => (string)($row['file_path'] ?? ''),
            'package_object_key' => (string)($row['package_object_key'] ?? ''),
            'package_file_name' => (string)($row['package_file_name'] ?? ''),
            'package_mime_type' => (string)($row['package_mime_type'] ?? ''),
            'file_hash' => strtolower((string)($row['package_hash'] ?? '')),
            'file_size' => intval($row['package_file_size'] ?? 0),
        ];
    }
}
