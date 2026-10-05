<?php

namespace app\common\service;

/**
 * 更新清单构建与验签
 *
 * 清单是「更新包完整性与来源真实性」的唯一依据（修复 A-02）。
 * 客户端安装前必须依次校验：
 *     清单 Ed25519 签名 → 包 SHA-256 → 版本单调性 → 兼容区间 → 逐文件 SHA-256
 *
 * 清单字段一经确定就不能随意增删 —— 客户端按确定性 JSON 验签，
 * 任何字段变动都会导致旧客户端验签失败。新增字段必须走 schema_version。
 *
 * @since 2026-08-16 P3
 */
class ManifestService
{
    /** 清单结构版本。新增字段时 +1，并在客户端做向后兼容处理。 */
    public const SCHEMA_VERSION = 3;

    /** 允许出现在更新包中的文件扩展名（白名单） */
    public const ALLOWED_EXT = [
        'php', 'htm', 'html', 'js', 'css', 'json', 'sql', 'md', 'txt',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
        'ttf', 'woff', 'woff2', 'eot', 'otf',
    ];

    /**
     * 从 ZIP 构建清单
     *
     * @param string $zipPath 包路径
     * @param array  $meta    ['kind'=>'release'|'patch', 'product_id','build_no','edition','channel',
     *                         'patch_id','revision','level','min_bbs_version','max_bbs_version',
     *                         'min_php_version','expect_sha256'=>[相对路径=>哈希]]
     * @return array ['ok'=>bool,'msg'=>string,'manifest'=>array]
     */
    public static function build(string $zipPath, array $meta): array
    {
        if (!is_file($zipPath)) {
            return ['ok' => false, 'msg' => '包文件不存在：' . $zipPath, 'manifest' => []];
        }
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'msg' => '缺少 ZipArchive 扩展', 'manifest' => []];
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'msg' => '无法打开 ZIP', 'manifest' => []];
        }

        $files = [];
        $problems = [];
        $seenEntries = [];
        $themeConfJson = null;
        $productIdentityJson = null;
        $programManifestJson = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if ($name === '' || substr($name, -1) === '/') {
                continue;
            }
            if (isset($seenEntries[$name])) {
                $problems[] = $name . ' -> ZIP 条目重复';
                continue;
            }
            $seenEntries[$name] = true;
            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                    && (((int)$attributes >> 16) & 0xF000) === 0xA000) {
                    $problems[] = $name . ' -> 不允许符号链接';
                    continue;
                }
            }

            // 打包阶段就拦下危险条目，不把问题留到客户端
            $why = self::entryProblem($name);
            if ($why !== '') {
                $problems[] = $name . ' -> ' . $why;
                continue;
            }

            $stream = $zip->getStream($name);
            if ($stream === false) {
                $problems[] = $name . ' -> 无法读取';
                continue;
            }
            $ctx = hash_init('sha256');
            while (!feof($stream)) {
                hash_update($ctx, (string)fread($stream, 65536));
            }
            fclose($stream);

            $stat = $zip->statIndex($i);
            $files[$name] = [
                'sha256' => hash_final($ctx),
                'size'   => (int)($stat['size'] ?? 0),
            ];
            if ($name === 'conf.json') {
                $rawConf = $zip->getFromIndex($i);
                $themeConfJson = is_string($rawConf) ? $rawConf : null;
            }
            if ($name === 'model/zaesky_license/product.json') {
                $rawIdentity = $zip->getFromIndex($i);
                $productIdentityJson = is_string($rawIdentity) ? $rawIdentity : null;
            }
            if ($name === 'program/_zaesky_program.json') {
                $rawProgram = $zip->getFromIndex($i);
                $programManifestJson = is_string($rawProgram) ? $rawProgram : null;
            }
        }
        $zip->close();

        if ($problems) {
            return [
                'ok' => false,
                'msg' => "包内存在不合规条目，已中止：\n  " . implode("\n  ", array_slice($problems, 0, 20)),
                'manifest' => [],
            ];
        }
        if (!$files) {
            return ['ok' => false, 'msg' => '包内没有任何有效文件', 'manifest' => []];
        }

        ksort($files, SORT_STRING);

        $manifest = [
            'schema_version'  => self::SCHEMA_VERSION,
            'kind'            => (string)($meta['kind'] ?? 'release'),
            'product_id'      => (string)($meta['product_id'] ?? ''),
            'package_sha256'  => hash_file('sha256', $zipPath),
            'package_size'    => (int)filesize($zipPath),
            'file_count'      => count($files),
            'files'           => $files,
            'min_bbs_version' => (string)($meta['min_bbs_version'] ?? ''),
            'max_bbs_version' => (string)($meta['max_bbs_version'] ?? ''),
            'min_php_version' => (string)($meta['min_php_version'] ?? ''),
            'built_at'        => (int)($meta['built_at'] ?? time()),
        ];

        if ($manifest['kind'] === 'patch') {
            $manifest['patch_id'] = (string)($meta['patch_id'] ?? '');
            $manifest['revision'] = (int)($meta['revision'] ?? 1);
            $manifest['level']    = (int)($meta['level'] ?? 1);
            $manifest['theme_build'] = (int)($meta['theme_build'] ?? 0);
            $manifest['theme_edition'] = (string)($meta['theme_edition'] ?? '');
            // level 2（直写核心文件）必须声明原文哈希，装前比对不匹配即拒绝
            $manifest['expect_sha256'] = (array)($meta['expect_sha256'] ?? []);
            if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $manifest['patch_id'])
                || $manifest['revision'] <= 0) {
                return ['ok' => false, 'msg' => '补丁标识或 revision 无效', 'manifest' => []];
            }
            if ($manifest['theme_build'] <= 0 || $manifest['theme_edition'] === '') {
                return ['ok' => false, 'msg' => '程序补丁必须绑定明确的主题版本和 build', 'manifest' => []];
            }
            if (!in_array($manifest['level'], [1, 2], true)) {
                return ['ok' => false, 'msg' => '补丁 level 无效', 'manifest' => []];
            }
            if ($manifest['level'] >= 2) {
                $normalizedExpected = [];
                if (!$manifest['expect_sha256'] || count($manifest['expect_sha256']) > 32) {
                    return ['ok' => false, 'msg' => 'level 2 补丁必须为 1 至 32 个主程序文件声明原文哈希', 'manifest' => []];
                }
                foreach ($manifest['expect_sha256'] as $target => $hashes) {
                    $target = (string)$target;
                    $problem = self::coreTargetProblem($target);
                    if ($problem !== '') {
                        return ['ok' => false, 'msg' => '核心补丁目标不安全：'.$target.'（'.$problem.'）', 'manifest' => []];
                    }
                    $hashes = is_array($hashes) ? $hashes : [$hashes];
                    if (!$hashes || count($hashes) > 16 || empty($files[$target])) {
                        return ['ok' => false, 'msg' => '核心补丁缺少目标文件或原文哈希：'.$target, 'manifest' => []];
                    }
                    $validHashes = [];
                    foreach ($hashes as $hash) {
                        $hash = strtolower((string)$hash);
                        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                            return ['ok' => false, 'msg' => '核心补丁原文哈希格式无效：'.$target, 'manifest' => []];
                        }
                        $validHashes[$hash] = true;
                    }
                    $validHashes = array_keys($validHashes);
                    sort($validHashes, SORT_STRING);
                    $normalizedExpected[$target] = $validHashes;
                }
                ksort($normalizedExpected, SORT_STRING);
                foreach (array_keys($files) as $file) {
                    if ($file !== '_zaesky_patch.json' && !isset($normalizedExpected[$file])) {
                        return ['ok' => false, 'msg' => '核心补丁包含未声明文件：'.$file, 'manifest' => []];
                    }
                }
                $manifest['expect_sha256'] = $normalizedExpected;
            }
        } else {
            // v2 发布物明确为“完整最新包”。客户端安装这一份包后，再按
            // migrations 列表顺序执行所有尚未记录成功的迁移。
            $migrations = [];
            foreach (array_keys($files) as $file) {
                if ($file === 'migrations/1_auto_reconcile.php'
                    || preg_match('#^migrations/[0-9][A-Za-z0-9_.-]*\.php$#', $file)) {
                    $migrations[] = $file;
                }
            }
            sort($migrations, SORT_STRING);
            $manifest['package_type'] = 'full';
            $manifest['migrations'] = $migrations;
            $manifest['build_no'] = (int)($meta['build_no'] ?? 0);
            $manifest['edition']  = (string)($meta['edition'] ?? '');
            $manifest['channel']  = (string)($meta['channel'] ?? 'stable');
            if ($manifest['build_no'] <= 0) {
                return ['ok' => false, 'msg' => 'build_no 必须为正整数', 'manifest' => []];
            }
            if (LicenseService::isThemeProduct($manifest['product_id'])) {
                foreach (self::themeRequiredFiles() as $requiredFile) {
                    if (!isset($files[$requiredFile])) {
                        return ['ok' => false, 'msg' => '完整主题包缺少必要文件：'.$requiredFile, 'manifest' => []];
                    }
                }
                foreach (self::themeLegacyCoreFiles() as $legacyFile) {
                    if (isset($files[$legacyFile])) {
                        return ['ok' => false, 'msg' => '完整主题包包含已停用的授权源码：'.$legacyFile, 'manifest' => []];
                    }
                }
                if (!$migrations) {
                    return ['ok' => false, 'msg' => '完整主题包必须包含至少一个顺序迁移文件', 'manifest' => []];
                }
                $themeConf = is_string($themeConfJson) ? json_decode($themeConfJson, true) : null;
                if (!is_array($themeConf) || empty($themeConf['version'])) {
                    return ['ok' => false, 'msg' => '完整主题包 conf.json 无效或缺少版本号', 'manifest' => []];
                }
                if ((string)$themeConf['version'] !== $manifest['edition']) {
                    return ['ok' => false, 'msg' => '版本列表中的版本名称与主题 conf.json 不一致，应为 '.$themeConf['version'], 'manifest' => []];
                }
                $themeBuild = (int)($themeConf['build'] ?? 0);
                if ($themeBuild <= 0 || $themeBuild !== $manifest['build_no']) {
                    return ['ok' => false, 'msg' => '版本列表中的 build 与主题 conf.json 不一致，应为 '.$themeBuild, 'manifest' => []];
                }
                if (!in_array('migrations/1_auto_reconcile.php', $migrations, true)) {
                    return ['ok' => false, 'msg' => '完整主题包缺少固定自动迁移文件', 'manifest' => []];
                }
                $productIdentity = is_string($productIdentityJson) ? json_decode($productIdentityJson, true) : null;
                $applicationId = LicenseService::productAppId($manifest['product_id']);
                if ($applicationId <= 0
                    || !is_array($productIdentity)
                    || !ProductIdentityService::verify(
                        $productIdentity,
                        $applicationId,
                        $manifest['product_id']
                    )) {
                    return ['ok' => false, 'msg' => '完整主题包产品身份文件无效或签名不匹配', 'manifest' => []];
                }
                $migrationBuilds = [];
                foreach ($migrations as $migrationFile) {
                    if ($migrationFile !== 'migrations/1_auto_reconcile.php'
                        && preg_match('#^migrations/([0-9]+)[A-Za-z0-9_.-]*\.php$#', $migrationFile, $migrationMatch)) {
                        $migrationBuilds[] = (int)$migrationMatch[1];
                    }
                }
                $latestManualBuild = $migrationBuilds ? max($migrationBuilds) : 0;
                if ($latestManualBuild > $manifest['build_no']) {
                    return ['ok' => false, 'msg' => '手工迁移 build 不能高于版本列表 build，应至少为 '.$latestManualBuild, 'manifest' => []];
                }
                $programResult = self::normalizeBundledProgram(
                    $programManifestJson,
                    $files,
                    $manifest['product_id'],
                    $manifest['edition'],
                    $manifest['build_no']
                );
                if (!$programResult['ok']) {
                    return ['ok' => false, 'msg' => $programResult['msg'], 'manifest' => []];
                }
                // 这两个字段属于最终 Ed25519 签名清单。客户端绝不能只相信
                // ZIP 内的 JSON，后者只是发布端输入与审计副本。
                $manifest['program_files'] = $programResult['files'];
                $manifest['program_restore'] = $programResult['restore'];
            }
        }

        return ['ok' => true, 'msg' => '', 'manifest' => $manifest];
    }

    /** 解析并规范化随主题版本发布的 Xiuno 主程序目标。 */
    private static function normalizeBundledProgram(?string $raw, array $packageFiles, string $productId, string $edition, int $buildNo): array
    {
        if (!is_string($raw) || strlen($raw) < 2 || strlen($raw) > 262144) {
            return ['ok' => false, 'msg' => '完整主题包缺少 program 发布清单', 'files' => [], 'restore' => []];
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta)
            || (int)($meta['schema_version'] ?? 0) !== 1
            || (string)($meta['kind'] ?? '') !== 'bundled_program'
            || (string)($meta['product_id'] ?? '') !== $productId
            || (string)($meta['theme_edition'] ?? '') !== $edition
            || (int)($meta['theme_build'] ?? 0) !== $buildNo
            || !isset($meta['files'], $meta['restore'])
            || !is_array($meta['files']) || !is_array($meta['restore'])
            || count($meta['files']) > 128 || count($meta['restore']) > 128) {
            return ['ok' => false, 'msg' => 'program 发布清单结构、产品或主题版本不匹配', 'files' => [], 'restore' => []];
        }

        $normalized = [];
        foreach ($meta['files'] as $target => $entry) {
            $target = (string)$target;
            if (!is_array($entry) || self::coreTargetProblem($target) !== '') {
                return ['ok' => false, 'msg' => 'program 包含不安全目标：'.$target, 'files' => [], 'restore' => []];
            }
            $source = (string)($entry['source'] ?? '');
            $expectedSource = 'program/'.$target;
            $hash = strtolower((string)($entry['sha256'] ?? ''));
            if ($source !== $expectedSource || !isset($packageFiles[$source])
                || !preg_match('/^[a-f0-9]{64}$/D', $hash)
                || !hash_equals($hash, strtolower((string)($packageFiles[$source]['sha256'] ?? '')))) {
                return ['ok' => false, 'msg' => 'program 源文件或哈希不匹配：'.$target, 'files' => [], 'restore' => []];
            }
            $accepted = $entry['expect_sha256'] ?? null;
            if (!is_array($accepted) || !$accepted || count($accepted) > 64) {
                return ['ok' => false, 'msg' => 'program 缺少安全的原文哈希：'.$target, 'files' => [], 'restore' => []];
            }
            $hashes = [];
            foreach ($accepted as $oldHash) {
                $oldHash = strtolower((string)$oldHash);
                if (!preg_match('/^[a-f0-9]{64}$/D', $oldHash)) {
                    return ['ok' => false, 'msg' => 'program 原文哈希格式无效：'.$target, 'files' => [], 'restore' => []];
                }
                $hashes[$oldHash] = true;
            }
            $hashes = array_keys($hashes);
            sort($hashes, SORT_STRING);
            $mode = (int)($entry['mode'] ?? 0644) & 0777;
            $normalized[$target] = [
                'source' => $source,
                'sha256' => $hash,
                'expect_sha256' => $hashes,
                'mode' => $mode > 0 ? $mode : 0644,
            ];
        }
        ksort($normalized, SORT_STRING);

        $restore = [];
        foreach ($meta['restore'] as $target) {
            $target = (string)$target;
            if (self::coreTargetProblem($target) !== '' || isset($normalized[$target])) {
                return ['ok' => false, 'msg' => 'program 恢复目标无效或冲突：'.$target, 'files' => [], 'restore' => []];
            }
            $restore[$target] = true;
        }
        $restore = array_keys($restore);
        sort($restore, SORT_STRING);

        // program/ 中除了元数据以外，每个文件都必须被精确声明；禁止利用未声明
        // 文件绕过目标白名单或把内容悄悄留在主题目录。
        foreach (array_keys($packageFiles) as $source) {
            if (strpos($source, 'program/') !== 0 || $source === 'program/_zaesky_program.json') {
                continue;
            }
            $target = substr($source, strlen('program/'));
            if (!isset($normalized[$target]) || $normalized[$target]['source'] !== $source) {
                return ['ok' => false, 'msg' => 'program 包含未声明文件：'.$source, 'files' => [], 'restore' => []];
            }
        }
        return ['ok' => true, 'msg' => '', 'files' => $normalized, 'restore' => $restore];
    }

    /**
     * 校验签名发布清单中的 Xiuno 主程序同步声明。
     *
     * 发布阶段会通过 normalizeBundledProgram() 生成这两组字段；更新接口仍需
     * 在返回清单前重新校验，避免数据库中的异常或历史脏数据进入下载流程。
     */
    public static function validateBundledProgramManifest(array $manifest): array
    {
        $files = $manifest['program_files'] ?? null;
        $restore = $manifest['program_restore'] ?? null;
        $packageFiles = $manifest['files'] ?? null;
        if (!is_array($files) || !is_array($restore) || !is_array($packageFiles)
            || count($files) > 128 || count($restore) > 128
            || empty($packageFiles['program/_zaesky_program.json'])) {
            return ['ok' => false, 'msg' => '主程序同步清单结构无效'];
        }

        $normalizedTargets = [];
        foreach ($files as $target => $entry) {
            $target = (string)$target;
            if (!is_array($entry) || self::coreTargetProblem($target) !== '') {
                return ['ok' => false, 'msg' => '主程序同步目标不安全：'.$target];
            }
            $source = (string)($entry['source'] ?? '');
            $hash = strtolower((string)($entry['sha256'] ?? ''));
            $sourceEntry = $packageFiles[$source] ?? null;
            $accepted = $entry['expect_sha256'] ?? null;
            $mode = $entry['mode'] ?? null;
            if ($source !== 'program/'.$target || !is_array($sourceEntry)
                || !preg_match('/^[a-f0-9]{64}$/D', $hash)
                || !hash_equals($hash, strtolower((string)($sourceEntry['sha256'] ?? '')))
                || !is_array($accepted) || !$accepted || count($accepted) > 64
                || !is_int($mode) || $mode <= 0 || $mode > 0777) {
                return ['ok' => false, 'msg' => '主程序同步源文件、哈希或原文声明无效：'.$target];
            }
            $seenHashes = [];
            foreach ($accepted as $oldHash) {
                $oldHash = strtolower((string)$oldHash);
                if (!preg_match('/^[a-f0-9]{64}$/D', $oldHash) || isset($seenHashes[$oldHash])) {
                    return ['ok' => false, 'msg' => '主程序同步原文哈希无效或重复：'.$target];
                }
                $seenHashes[$oldHash] = true;
            }
            $normalizedTargets[$target] = true;
        }

        $restoreSeen = [];
        foreach ($restore as $target) {
            $target = (string)$target;
            if (self::coreTargetProblem($target) !== ''
                || isset($normalizedTargets[$target]) || isset($restoreSeen[$target])) {
                return ['ok' => false, 'msg' => '主程序恢复目标无效、重复或冲突：'.$target];
            }
            $restoreSeen[$target] = true;
        }

        $sortedFiles = array_keys($files);
        $canonicalFiles = $sortedFiles;
        sort($canonicalFiles, SORT_STRING);
        $canonicalRestore = array_values($restore);
        sort($canonicalRestore, SORT_STRING);
        if ($sortedFiles !== $canonicalFiles || array_values($restore) !== $canonicalRestore) {
            return ['ok' => false, 'msg' => '主程序同步清单必须按路径排序'];
        }

        foreach (array_keys($packageFiles) as $source) {
            $source = (string)$source;
            if (strpos($source, 'program/') !== 0 || $source === 'program/_zaesky_program.json') {
                continue;
            }
            $target = substr($source, strlen('program/'));
            if (!isset($normalizedTargets[$target])
                || (string)($files[$target]['source'] ?? '') !== $source) {
                return ['ok' => false, 'msg' => '主程序目录包含未声明文件：'.$source];
            }
        }
        return ['ok' => true, 'msg' => ''];
    }

    /** 主题正式发布包必须存在的运行文件。 */
    public static function themeRequiredFiles(): array
    {
        return [
            'conf.json',
            'install.php',
            'setting.php',
            'model/install_config.php',
            'model/zaesky_license/Bootstrap.php',
            'model/zaesky_license/Core.php',
            'model/zaesky_license/product.json',
        ];
    }

    /** 合并前的明文授权模块；正式发布包一律拒绝。 */
    public static function themeLegacyCoreFiles(): array
    {
        return [
            'model/zaesky_license/Config.php',
            'model/zaesky_license/Store.php',
            'model/zaesky_license/Verifier.php',
            'model/zaesky_license/AdminSecurity.php',
            'model/zaesky_license/Client.php',
            'model/zaesky_license/License.php',
            'model/zaesky_license/SafeZip.php',
            'model/zaesky_license/Migrator.php',
            'model/zaesky_license/Updater.php',
            'model/zaesky_license/Resetter.php',
            'model/zaesky_license/Patcher.php',
        ];
    }

    /** 允许 Level 2 补丁替换的 Xiuno 主程序路径；配置、数据和插件目录不开放。 */
    public static function coreTargetProblem(string $name): string
    {
        $problem = self::entryProblem($name);
        if ($problem !== '') {
            return $problem;
        }
        foreach (['model/', 'route/', 'view/', 'admin/', 'xiunophp/'] as $prefix) {
            if (strpos($name, $prefix) === 0) {
                return '';
            }
        }
        return $name === 'index.php' ? '' : '不在主程序路径白名单';
    }

    /**
     * 条目合规性检查（打包侧）
     *
     * 与客户端 SafeZip 的规则保持一致，形成双重防线：
     * 打包时拒绝生成危险包，安装时再次拒绝解压危险包。
     */
    public static function entryProblem(string $name): string
    {
        if ($name === '') {
            return '空条目名';
        }
        if (strlen($name) > 200) {
            return '路径过长';
        }
        $normalized = str_replace('\\', '/', $name);
        if ($normalized !== $name) {
            return '包含反斜杠';
        }
        if (strpos($name, "\0") !== false) {
            return '包含空字节';
        }
        if ($name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) {
            return '绝对路径';
        }
        foreach (explode('/', $name) as $seg) {
            if ($seg === '..' || $seg === '.') {
                return '路径穿越';
            }
            if ($seg === '' ) {
                return '空路径段';
            }
        }
        $lower = strtolower($name);
        if (str_starts_with($lower, 'storage/') || str_starts_with($lower, 'backup/')) {
            return '发布包不得覆盖授权、备份或运行时数据目录';
        }
        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXT, true)) {
            return '扩展名不在白名单：' . ($ext === '' ? '(无)' : $ext);
        }
        return '';
    }

    /**
     * 签名清单
     */
    public static function sign(array $manifest): array
    {
        return CryptoService::sign($manifest, CryptoService::PURPOSE_RELEASE);
    }

    /**
     * 验签（服务端自检 / 测试用）
     */
    public static function verify(array $manifest, string $sig, string $publicKeyB64): bool
    {
        return CryptoService::verify($manifest, $sig, $publicKeyB64);
    }
}
