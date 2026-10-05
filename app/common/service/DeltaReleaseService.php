<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Db;

/**
 * 根据相邻的已签名完整发布物自动生成主题差分包。
 *
 * 完整包始终是最终状态的权威来源；差分包只是绑定 from_build 的传输优化。
 * 任一校验不满足时调用方应继续保留完整包，并让客户端自动回退完整下载。
 */
class DeltaReleaseService
{
    public const SCHEMA_VERSION = 4;
    public const CLIENT_SCHEMA = 1;
    private const MAX_DELTA_PERCENT = 70;
    private const LOCAL_DIRECTORY = 'delta';

    /**
     * 为目标完整发布物生成最近一个同通道 build 到目标 build 的差分包。
     */
    public static function buildForRelease(array $release, string $targetZipPath): array
    {
        $targetManifest = self::verifiedFullManifest($release);
        if ($targetManifest === null) {
            return self::skipped('目标完整包清单无效');
        }
        if (!is_file($targetZipPath) || is_link($targetZipPath)) {
            return self::skipped('目标完整包文件不可用');
        }
        $actualTargetHash = @hash_file('sha256', $targetZipPath);
        if (!is_string($actualTargetHash)
            || !hash_equals((string)$targetManifest['package_sha256'], $actualTargetHash)) {
            return self::skipped('目标完整包文件哈希不匹配');
        }

        $baseRows = Db::name('release')
            ->where('product_id', (string)$release['product_id'])
            ->where('channel', (string)$release['channel'])
            ->where('status', 1)
            ->where('build_no', '<', (int)$release['build_no'])
            ->whereNotNull('manifest_sig')
            ->order('build_no', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        $baseRelease = null;
        $baseManifest = null;
        foreach ($baseRows as $candidate) {
            $candidateManifest = self::verifiedFullManifest($candidate);
            if ($candidateManifest !== null) {
                $baseRelease = $candidate;
                $baseManifest = $candidateManifest;
                break;
            }
        }
        if ($baseRelease === null || $baseManifest === null) {
            return self::skipped('没有可作为基准的上一已发布完整包');
        }

        if (self::programChanged($baseManifest, $targetManifest)) {
            return self::skipped('Xiuno 主程序同步声明发生变化，本次使用完整包');
        }

        $plan = self::diffFileMaps(
            (array)$baseManifest['files'],
            (array)$targetManifest['files']
        );
        if (!$plan['files']) {
            // 正常主题发布至少会改变 conf.json 中的 build；不为异常的纯删除包
            // 引入特殊 ZIP 占位协议，直接安全回退完整包。
            return self::skipped('没有可写入差分包的新增或修改文件');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'qh_delta_');
        if (!is_string($tempPath) || $tempPath === '') {
            return self::skipped('无法创建差分包临时文件');
        }

        try {
            $assembled = self::assembleZip($targetZipPath, $tempPath, $plan['files']);
            if (!$assembled['ok']) {
                return self::skipped((string)$assembled['msg']);
            }
            $deltaSize = (int)@filesize($tempPath);
            $fullSize = max(1, (int)$targetManifest['package_size']);
            if ($deltaSize <= 0) {
                return self::skipped('生成的差分包为空');
            }
            if ($deltaSize * 100 >= $fullSize * self::MAX_DELTA_PERCENT) {
                return self::skipped('差分包达到完整包的 '.self::MAX_DELTA_PERCENT.'%，本次使用完整包');
            }

            $manifest = [
                'schema_version' => self::SCHEMA_VERSION,
                'kind' => 'release_delta',
                'package_type' => 'delta',
                'product_id' => (string)$release['product_id'],
                'from_build' => (int)$baseRelease['build_no'],
                'build_no' => (int)$release['build_no'],
                'edition' => (string)$release['edition'],
                'channel' => (string)$release['channel'],
                'from_package_sha256' => (string)$baseManifest['package_sha256'],
                'target_package_sha256' => (string)$targetManifest['package_sha256'],
                'from_files_sha256' => self::fileMapHash((array)$baseManifest['files']),
                'target_files_sha256' => self::fileMapHash((array)$targetManifest['files']),
                'package_sha256' => (string)hash_file('sha256', $tempPath),
                'package_size' => $deltaSize,
                'file_count' => count($plan['files']),
                'files' => $plan['files'],
                'expect_files' => $plan['expect_files'],
                'delete_files' => $plan['delete_files'],
                'target_files' => (array)$targetManifest['files'],
                'migrations' => (array)$targetManifest['migrations'],
                'program_changed' => false,
                'min_bbs_version' => (string)($targetManifest['min_bbs_version'] ?? ''),
                'max_bbs_version' => (string)($targetManifest['max_bbs_version'] ?? ''),
                'min_php_version' => (string)($targetManifest['min_php_version'] ?? ''),
                'built_at' => time(),
            ];

            $valid = self::validateManifest($manifest, $baseManifest, $targetManifest);
            if (!$valid['ok']) {
                return self::skipped('差分清单自检失败：'.(string)$valid['msg']);
            }
            $signed = ManifestService::sign($manifest);
            $publicKeys = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
            $public = (string)($publicKeys[$signed['key_id']] ?? '');
            if ($public === '' || !ManifestService::verify($manifest, (string)$signed['sig'], $public)) {
                return self::skipped('差分清单签名自检失败');
            }

            return self::persist(
                $release,
                $baseRelease,
                $manifest,
                $signed,
                $tempPath
            );
        } catch (\Throwable $e) {
            return self::skipped('自动生成差分包失败：'.$e->getMessage());
        } finally {
            if (is_file($tempPath) && !is_link($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * 纯清单差异计算，供构建器和测试共同使用。
     */
    public static function diffFileMaps(array $fromFiles, array $targetFiles): array
    {
        ksort($fromFiles, SORT_STRING);
        ksort($targetFiles, SORT_STRING);
        $changed = [];
        $expected = [];
        $deleted = [];

        foreach ($targetFiles as $path => $entry) {
            $newHash = strtolower((string)($entry['sha256'] ?? ''));
            $oldHash = isset($fromFiles[$path])
                ? strtolower((string)($fromFiles[$path]['sha256'] ?? '')) : '';
            if ($oldHash !== '' && hash_equals($oldHash, $newHash)) {
                continue;
            }
            $changed[(string)$path] = $entry;
            $expected[(string)$path] = $oldHash !== '' ? $oldHash : null;
        }
        foreach ($fromFiles as $path => $entry) {
            if (!array_key_exists($path, $targetFiles)) {
                $deleted[(string)$path] = strtolower((string)($entry['sha256'] ?? ''));
            }
        }
        ksort($changed, SORT_STRING);
        ksort($expected, SORT_STRING);
        ksort($deleted, SORT_STRING);
        return ['files' => $changed, 'expect_files' => $expected, 'delete_files' => $deleted];
    }

    public static function fileMapHash(array $files): string
    {
        ksort($files, SORT_STRING);
        return hash('sha256', CryptoService::canonicalJson($files));
    }

    /**
     * 校验 schema 4 差分清单，并可与两端完整清单做强绑定。
     */
    public static function validateManifest(array $manifest, ?array $fromManifest = null, ?array $targetManifest = null): array
    {
        if ((int)($manifest['schema_version'] ?? 0) !== self::SCHEMA_VERSION
            || (string)($manifest['kind'] ?? '') !== 'release_delta'
            || (string)($manifest['package_type'] ?? '') !== 'delta'
            || (int)($manifest['from_build'] ?? 0) <= 0
            || (int)($manifest['build_no'] ?? 0) <= (int)($manifest['from_build'] ?? 0)
            || !in_array((string)($manifest['channel'] ?? ''), [LicenseService::CHANNEL_STABLE, LicenseService::CHANNEL_BETA], true)
            || ($manifest['program_changed'] ?? null) !== false) {
            return ['ok' => false, 'msg' => '差分清单类型、版本范围或通道无效'];
        }
        foreach (['from_package_sha256', 'target_package_sha256', 'from_files_sha256', 'target_files_sha256', 'package_sha256'] as $hashField) {
            if (!preg_match('/^[a-f0-9]{64}$/D', strtolower((string)($manifest[$hashField] ?? '')))) {
                return ['ok' => false, 'msg' => '差分清单哈希字段无效：'.$hashField];
            }
        }
        if ((int)($manifest['package_size'] ?? 0) <= 0
            || !isset($manifest['files'], $manifest['expect_files'], $manifest['delete_files'], $manifest['target_files'], $manifest['migrations'])
            || !is_array($manifest['files']) || !is_array($manifest['expect_files'])
            || !is_array($manifest['delete_files']) || !is_array($manifest['target_files'])
            || !is_array($manifest['migrations'])
            || !$manifest['files'] || !$manifest['target_files']
            || count($manifest['files']) > 5000 || count($manifest['target_files']) > 5000
            || (int)($manifest['file_count'] ?? -1) !== count($manifest['files'])) {
            return ['ok' => false, 'msg' => '差分文件清单结构无效'];
        }

        foreach (['files', 'target_files'] as $mapName) {
            $paths = array_keys($manifest[$mapName]);
            $sorted = $paths;
            sort($sorted, SORT_STRING);
            if ($paths !== $sorted) {
                return ['ok' => false, 'msg' => $mapName.' 必须按路径排序'];
            }
            foreach ($manifest[$mapName] as $path => $entry) {
                if (!is_array($entry) || ManifestService::entryProblem((string)$path) !== ''
                    || !preg_match('/^[a-f0-9]{64}$/D', strtolower((string)($entry['sha256'] ?? '')))
                    || (int)($entry['size'] ?? -1) < 0) {
                    return ['ok' => false, 'msg' => '差分文件条目无效：'.$path];
                }
            }
        }

        if (array_keys($manifest['files']) !== array_keys($manifest['expect_files'])) {
            return ['ok' => false, 'msg' => '修改文件与原始哈希声明不一致'];
        }
        foreach ($manifest['files'] as $path => $entry) {
            if (!isset($manifest['target_files'][$path])
                || $entry !== $manifest['target_files'][$path]) {
                return ['ok' => false, 'msg' => '差分文件不属于目标完整清单：'.$path];
            }
            $expected = $manifest['expect_files'][$path];
            if ($expected !== null && !preg_match('/^[a-f0-9]{64}$/D', strtolower((string)$expected))) {
                return ['ok' => false, 'msg' => '文件原始哈希无效：'.$path];
            }
        }

        $deletePaths = array_keys($manifest['delete_files']);
        $sortedDeletePaths = $deletePaths;
        sort($sortedDeletePaths, SORT_STRING);
        if ($deletePaths !== $sortedDeletePaths || count($deletePaths) > 5000) {
            return ['ok' => false, 'msg' => '删除文件清单必须按路径排序'];
        }
        foreach ($manifest['delete_files'] as $path => $oldHash) {
            if (ManifestService::entryProblem((string)$path) !== ''
                || isset($manifest['target_files'][$path])
                || !preg_match('/^[a-f0-9]{64}$/D', strtolower((string)$oldHash))) {
                return ['ok' => false, 'msg' => '删除文件声明无效：'.$path];
            }
        }

        $migrations = array_values($manifest['migrations']);
        $sortedMigrations = $migrations;
        sort($sortedMigrations, SORT_STRING);
        if ($migrations !== $sortedMigrations || count($migrations) !== count(array_unique($migrations))) {
            return ['ok' => false, 'msg' => '迁移清单必须有序且不能重复'];
        }
        foreach ($migrations as $migration) {
            if (!is_string($migration)
                || !preg_match('#^migrations/[0-9][A-Za-z0-9_.-]*\.php$#D', $migration)
                || !isset($manifest['target_files'][$migration])) {
                return ['ok' => false, 'msg' => '迁移清单包含无效文件'];
            }
        }

        if (!hash_equals((string)$manifest['target_files_sha256'], self::fileMapHash($manifest['target_files']))) {
            return ['ok' => false, 'msg' => '目标完整文件清单摘要不匹配'];
        }
        if ($fromManifest !== null) {
            if ((int)($fromManifest['build_no'] ?? 0) !== (int)$manifest['from_build']
                || !hash_equals((string)($fromManifest['package_sha256'] ?? ''), (string)$manifest['from_package_sha256'])
                || !hash_equals(self::fileMapHash((array)($fromManifest['files'] ?? [])), (string)$manifest['from_files_sha256'])) {
                return ['ok' => false, 'msg' => '差分基准与完整发布物不匹配'];
            }
        }
        if ($targetManifest !== null) {
            if ((int)($targetManifest['build_no'] ?? 0) !== (int)$manifest['build_no']
                || (string)($targetManifest['product_id'] ?? '') !== (string)$manifest['product_id']
                || (string)($targetManifest['channel'] ?? '') !== (string)$manifest['channel']
                || (string)($targetManifest['edition'] ?? '') !== (string)$manifest['edition']
                || !hash_equals((string)($targetManifest['package_sha256'] ?? ''), (string)$manifest['target_package_sha256'])
                || (array)($targetManifest['files'] ?? []) !== $manifest['target_files']
                || (array)($targetManifest['migrations'] ?? []) !== $manifest['migrations']) {
                return ['ok' => false, 'msg' => '差分目标与完整发布物不匹配'];
            }
        }
        if ($fromManifest !== null && $targetManifest !== null) {
            $expectedPlan = self::diffFileMaps(
                (array)($fromManifest['files'] ?? []),
                (array)($targetManifest['files'] ?? [])
            );
            if ($manifest['files'] !== $expectedPlan['files']
                || $manifest['expect_files'] !== $expectedPlan['expect_files']
                || $manifest['delete_files'] !== $expectedPlan['delete_files']) {
                return ['ok' => false, 'msg' => '差分内容不是两份完整清单的精确差异'];
            }
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * 同时验证差分签名及其 from/target 两条完整发布记录。
     */
    public static function verifiedManifest(array $row, array $fromRelease, array $targetRelease): ?array
    {
        $manifest = json_decode((string)($row['manifest_json'] ?? ''), true);
        if (!is_array($manifest)
            || (string)($row['product_id'] ?? '') !== (string)($manifest['product_id'] ?? '')
            || (int)($row['from_build'] ?? 0) !== (int)($manifest['from_build'] ?? 0)
            || (int)($row['build_no'] ?? 0) !== (int)($manifest['build_no'] ?? 0)
            || (string)($row['channel'] ?? '') !== (string)($manifest['channel'] ?? '')
            || !hash_equals((string)($row['package_sha256'] ?? ''), (string)($manifest['package_sha256'] ?? ''))) {
            return null;
        }
        $fromManifest = self::verifiedFullManifest($fromRelease);
        $targetManifest = self::verifiedFullManifest($targetRelease);
        if ($fromManifest === null || $targetManifest === null
            || empty(self::validateManifest($manifest, $fromManifest, $targetManifest)['ok'])) {
            return null;
        }
        $keyId = (string)($row['sig_key_id'] ?? '');
        $keys = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
        if ($keyId === '' || empty($keys[$keyId])
            || !ManifestService::verify($manifest, (string)($row['manifest_sig'] ?? ''), (string)$keys[$keyId])) {
            return null;
        }
        return $manifest;
    }

    /** 同步目标完整版本的启用状态。 */
    public static function syncStatus(string $productId, int $buildNo, int $status): void
    {
        Db::name('release_delta')
            ->where('product_id', $productId)
            ->where('build_no', $buildNo)
            ->update([
                'status' => $status === 1 ? 1 : 0,
                'published_at' => $status === 1 ? date('Y-m-d H:i:s') : null,
            ]);
    }

    /** 删除以该版本为目标或基准的差分记录及其独立资源。 */
    public static function deleteForRelease(string $productId, int $buildNo): void
    {
        $rows = [];
        foreach (['build_no', 'from_build'] as $field) {
            $found = Db::name('release_delta')
                ->where('product_id', $productId)
                ->where($field, $buildNo)
                ->select()
                ->toArray();
            foreach ($found as $row) {
                $rows[(int)$row['id']] = $row;
            }
        }
        foreach ($rows as $id => $row) {
            Db::name('release_delta')->where('id', $id)->delete();
            self::deleteStoredPackage($row);
        }
    }

    /** 完整包记录的严格验签解析。 */
    public static function verifiedFullManifest(array $row): ?array
    {
        $manifest = json_decode((string)($row['manifest_json'] ?? ''), true);
        $schema = is_array($manifest) ? (int)($manifest['schema_version'] ?? 0) : 0;
        if (!is_array($manifest)
            || !in_array($schema, [2, 3], true)
            || (string)($manifest['kind'] ?? '') !== 'release'
            || (string)($manifest['package_type'] ?? '') !== 'full'
            || !is_array($manifest['files'] ?? null)
            || !is_array($manifest['migrations'] ?? null)
            || (string)($manifest['product_id'] ?? '') !== (string)($row['product_id'] ?? '')
            || (int)($manifest['build_no'] ?? 0) !== (int)($row['build_no'] ?? 0)
            || (string)($manifest['channel'] ?? '') !== (string)($row['channel'] ?? '')
            || !hash_equals((string)($manifest['package_sha256'] ?? ''), (string)($row['package_sha256'] ?? ''))) {
            return null;
        }
        $isTheme = LicenseService::isThemeProduct((string)$row['product_id']);
        if ($isTheme) {
            foreach (ManifestService::themeRequiredFiles() as $requiredFile) {
                if (empty($manifest['files'][$requiredFile])) {
                    return null;
                }
            }
            foreach (ManifestService::themeLegacyCoreFiles() as $legacyFile) {
                if (!empty($manifest['files'][$legacyFile])) {
                    return null;
                }
            }
            if (!$manifest['migrations']) {
                return null;
            }
        }
        if ($isTheme && $schema >= 3 && empty(ManifestService::validateBundledProgramManifest($manifest)['ok'])) {
            return null;
        }
        $keyId = (string)($row['sig_key_id'] ?? '');
        $keys = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
        if ($keyId === '' || empty($keys[$keyId])
            || !ManifestService::verify($manifest, (string)($row['manifest_sig'] ?? ''), (string)$keys[$keyId])) {
            return null;
        }
        return $manifest;
    }

    private static function programChanged(array $from, array $target): bool
    {
        $fromProgram = [
            'files' => (array)($from['program_files'] ?? []),
            'restore' => (array)($from['program_restore'] ?? []),
        ];
        $targetProgram = [
            'files' => (array)($target['program_files'] ?? []),
            'restore' => (array)($target['program_restore'] ?? []),
        ];
        return CryptoService::canonicalJson($fromProgram) !== CryptoService::canonicalJson($targetProgram);
    }

    private static function assembleZip(string $targetZipPath, string $deltaPath, array $files): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'msg' => '缺少 ZipArchive 扩展'];
        }
        $source = new \ZipArchive();
        $delta = new \ZipArchive();
        if ($source->open($targetZipPath) !== true) {
            return ['ok' => false, 'msg' => '无法读取目标完整包'];
        }
        if ($delta->open($deltaPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $source->close();
            return ['ok' => false, 'msg' => '无法创建差分 ZIP'];
        }
        $ok = true;
        $message = '';
        foreach ($files as $path => $entry) {
            $data = $source->getFromName((string)$path);
            if (!is_string($data)
                || !hash_equals(strtolower((string)$entry['sha256']), hash('sha256', $data))
                || strlen($data) !== (int)$entry['size']
                || !$delta->addFromString((string)$path, $data)) {
                $ok = false;
                $message = '无法从目标完整包提取差分文件：'.$path;
                break;
            }
        }
        $source->close();
        $closed = $delta->close();
        if (!$ok || !$closed) {
            @unlink($deltaPath);
            return ['ok' => false, 'msg' => $message !== '' ? $message : '无法完成差分 ZIP'];
        }
        return ['ok' => true, 'msg' => ''];
    }

    private static function persist(array $release, array $baseRelease, array $manifest, array $signed, string $tempPath): array
    {
        $safeProduct = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$release['product_id']);
        $fileName = sprintf(
            '%s_%d_%d_%s.zip',
            $safeProduct,
            (int)$baseRelease['build_no'],
            (int)$release['build_no'],
            substr((string)$manifest['package_sha256'], 0, 12)
        );
        $storage = new PluginStorageService();
        $storageDriver = 'local';
        $objectKey = '';
        $destDir = APP_PATH.DS.'common'.DS.'download'.DS.self::LOCAL_DIRECTORY.DS;
        $targetPath = $destDir.$fileName;
        $targetExisted = false;

        if ($storage->isOssEnabled()) {
            $stored = $storage->storePath($tempPath, 'application_update', $fileName, 'application/zip');
            $storageDriver = 'oss';
            $objectKey = (string)$stored['object_key'];
        } else {
            if (is_link($destDir) || (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) || !is_dir($destDir)) {
                return self::skipped('无法创建本地差分发布目录');
            }
            if (is_link($targetPath)) {
                return self::skipped('差分发布目标文件不安全');
            }
            $targetExisted = is_file($targetPath);
            if ($targetExisted) {
                if (!hash_equals((string)$manifest['package_sha256'], (string)@hash_file('sha256', $targetPath))) {
                    return self::skipped('同名差分包已存在但哈希不一致');
                }
            } else {
                $copy = $destDir.'.'.$fileName.'.'.bin2hex(random_bytes(6)).'.tmp';
                if (!@copy($tempPath, $copy)
                    || !hash_equals((string)$manifest['package_sha256'], (string)@hash_file('sha256', $copy))) {
                    @unlink($copy);
                    return self::skipped('复制差分包失败');
                }
                @chmod($copy, 0640);
                if (!@rename($copy, $targetPath)) {
                    @unlink($copy);
                    return self::skipped('无法原子发布差分包');
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'product_id' => (string)$release['product_id'],
            'from_build' => (int)$baseRelease['build_no'],
            'build_no' => (int)$release['build_no'],
            'edition' => (string)$release['edition'],
            'channel' => (string)$release['channel'],
            'package_file' => $fileName,
            'storage_driver' => $storageDriver,
            'package_object_key' => $objectKey,
            'package_sha256' => (string)$manifest['package_sha256'],
            'package_size' => (int)$manifest['package_size'],
            'manifest_json' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'manifest_sig' => (string)$signed['sig'],
            'sig_key_id' => (string)$signed['key_id'],
            'download_count' => 0,
            'status' => (int)($release['status'] ?? 0) === 1 ? 1 : 0,
            'published_at' => (int)($release['status'] ?? 0) === 1 ? $now : null,
            'updated_at' => $now,
        ];

        try {
            $existing = Db::name('release_delta')
                ->where('product_id', $row['product_id'])
                ->where('channel', $row['channel'])
                ->where('from_build', $row['from_build'])
                ->where('build_no', $row['build_no'])
                ->find();
            $old = is_array($existing) ? $existing : [];
            if ($existing) {
                Db::name('release_delta')->where('id', (int)$existing['id'])->update($row);
                $id = (int)$existing['id'];
            } else {
                $row['created_at'] = $now;
                $id = (int)Db::name('release_delta')->insertGetId($row);
            }
            if ($old && ((string)($old['package_file'] ?? '') !== $fileName
                || (string)($old['package_object_key'] ?? '') !== $objectKey)) {
                self::deleteStoredPackage($old);
            }
        } catch (\Throwable $e) {
            if ($storageDriver === 'oss' && $objectKey !== '') {
                $storage->deleteObject($objectKey);
            } elseif (!$targetExisted && is_file($targetPath) && !is_link($targetPath)) {
                @unlink($targetPath);
            }
            return self::skipped('差分发布记录写入失败');
        }

        return [
            'ok' => true,
            'published' => (int)$row['status'] === 1,
            'generated' => true,
            'msg' => '已自动生成 '.count($manifest['files']).' 个变更、'.count($manifest['delete_files']).' 个删除的差分包',
            'release_delta_id' => $id,
            'from_build' => (int)$baseRelease['build_no'],
            'build_no' => (int)$release['build_no'],
            'package_size' => (int)$manifest['package_size'],
        ];
    }

    private static function deleteStoredPackage(array $row): void
    {
        if ((string)($row['storage_driver'] ?? 'local') === 'oss') {
            (new PluginStorageService())->deleteObject((string)($row['package_object_key'] ?? ''));
            return;
        }
        $name = (string)($row['package_file'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{1,160}$/D', $name)) {
            return;
        }
        $path = APP_PATH.DS.'common'.DS.'download'.DS.self::LOCAL_DIRECTORY.DS.$name;
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }

    private static function skipped(string $message): array
    {
        return ['ok' => true, 'published' => false, 'generated' => false, 'msg' => $message];
    }
}
