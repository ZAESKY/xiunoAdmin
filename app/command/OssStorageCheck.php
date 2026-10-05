<?php
declare(strict_types=1);

namespace app\command;

use app\common\service\PluginStorageService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/** Safe OSS configuration and private-object round-trip check. */
class OssStorageCheck extends Command
{
    protected function configure()
    {
        $this->setName('qh:oss-check')
            ->addOption('write-test', null, Option::VALUE_NONE, '上传临时图片和 ZIP，校验私有读写后立即删除')
            ->addOption('repair-urls', null, Option::VALUE_NONE, '把历史插件 OSS 图标/封面改为站内私有媒体网关 URL')
            ->setDescription('检查 OSS 配置；不会输出 AccessKey、Bucket 地址或签名 URL');
    }

    protected function execute(Input $input, Output $output)
    {
        $storage = new PluginStorageService();
        $output->writeln('OSS 开关：' . ($storage->isOssEnabled() ? '<info>已开启</info>' : '<comment>未开启</comment>'));
        $output->writeln('OSS 配置：' . ($storage->isOssConfigured() ? '<info>完整</info>' : '<comment>不可用</comment>'));
        if (!$storage->isOssConfigured()) {
            $output->error($storage->hasIncompleteOssConfig() ? '配置不完整或 Bucket 名不合法。' : 'OSS 尚未启用。');
            return 2;
        }
        if ((bool)$input->getOption('repair-urls')) {
            $count = $this->repairPluginMediaUrls($storage);
            $output->writeln('<info>已修复历史插件媒体 URL：' . $count . ' 条。</info>');
        }
        if (!(bool)$input->getOption('write-test')) {
            $output->writeln('<comment>只读检查完成；使用 --write-test 执行私有对象往返测试。</comment>');
            return 0;
        }

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        if (!is_string($png)) {
            $output->error('内置测试图片无效。');
            return 3;
        }

        $objectKey = '';
        try {
            $stored = $storage->storeBytes($png, 'image', 'oss_check.png', 'image/png');
            $objectKey = (string)($stored['object_key'] ?? '');
            $stableUrl = (string)($stored['url'] ?? '');
            $query = (string)parse_url($stableUrl, PHP_URL_QUERY);
            parse_str($query, $params);
            $signed = $storage->getMediaRedirectUrl(
                $objectKey,
                (string)($params['token'] ?? ''),
                60
            );
            $raw = preg_replace('/\?.*$/', '', $signed);
            $rawStatus = $this->httpStatus((string)$raw);
            $signedStatus = $this->httpStatus($signed);
            if (!in_array($rawStatus, [403, 404], true)) {
                throw new \RuntimeException('裸 OSS 地址未被拒绝，Bucket/Object ACL 不是私有状态');
            }
            if (!in_array($signedStatus, [200, 206], true)) {
                throw new \RuntimeException('短时签名读取失败，HTTP ' . $signedStatus);
            }
            $this->assertProtectedPackageRoundTrip($storage);
            $output->writeln('<info>图片与受保护 ZIP 的上传、私有 ACL、签名读取及服务端下载均正常。</info>');
        } catch (\Throwable $e) {
            $output->error('OSS 往返测试失败：' . $e->getMessage());
            return 4;
        } finally {
            if ($objectKey !== '') {
                if ($storage->deleteObject($objectKey)) {
                    $output->writeln('<info>测试对象已删除。</info>');
                } else {
                    $output->warning('测试对象删除失败，请在 OSS 控制台按当日 uploads/images 路径检查。');
                }
            }
        }
        return 0;
    }

    /** Exercise the streaming package path used by installers and updates. */
    private function assertProtectedPackageRoundTrip(PluginStorageService $storage): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('服务器缺少 ZipArchive，无法测试受保护 ZIP');
        }
        $source = $this->createPrivateTestFile();

        $objectKey = '';
        $downloaded = '';
        $error = null;
        try {
            $zip = new \ZipArchive();
            if ($zip->open($source, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('无法创建受保护 ZIP 测试包');
            }
            $zip->addFromString('oss-health-check.txt', 'private package round trip');
            $zip->close();
            clearstatcache(true, $source);

            $stored = $storage->storePath(
                $source,
                'package',
                'oss_health_check.zip',
                'application/zip'
            );
            $objectKey = (string)($stored['object_key'] ?? '');
            $signed = $storage->objectDownloadUrl($objectKey, 60);
            $raw = (string)preg_replace('/\?.*$/', '', $signed);
            $rawStatus = $this->httpStatus($raw);
            $signedStatus = $this->httpStatus($signed);
            if (!in_array($rawStatus, [403, 404], true)) {
                throw new \RuntimeException('受保护 ZIP 裸地址未被拒绝');
            }
            if (!in_array($signedStatus, [200, 206], true)) {
                throw new \RuntimeException('受保护 ZIP 签名读取失败，HTTP ' . $signedStatus);
            }

            // Use the same resolver as plugin history downloads so this check
            // covers the persisted OSS metadata contract end to end.
            $downloaded = $storage->getDownloadFile($stored);
            clearstatcache(true, $downloaded);
            if (!is_file($downloaded) || (int)filesize($downloaded) <= 0) {
                throw new \RuntimeException('受保护 ZIP 服务端下载结果无效');
            }
        } catch (\Throwable $e) {
            $error = $e;
        } finally {
            if ($downloaded !== '') {
                $storage->deleteTemporaryDownloadFile($downloaded);
            }
            if (is_file($source) && !is_link($source)) {
                @unlink($source);
            }
            if ($objectKey !== '' && !$storage->deleteObject($objectKey) && $error === null) {
                $error = new \RuntimeException('受保护 ZIP 测试对象删除失败');
            }
        }
        if ($error !== null) {
            throw $error;
        }
    }

    /**
     * Create health-check files inside the application's private runtime tree.
     *
     * Some production PHP pools restrict the operating-system temporary
     * directory even when sys_get_temp_dir() still returns it. Keeping the
     * probe under runtime also prevents a package sample from being exposed
     * through a public upload directory.
     */
    private function createPrivateTestFile(): string
    {
        $runtime = rtrim((string)app()->getRuntimePath(), '/\\');
        if ($runtime === '' || !is_dir($runtime) || !is_writable($runtime)) {
            throw new \RuntimeException('应用运行目录不可用，无法执行 OSS 写入测试');
        }

        $runtimeReal = realpath($runtime);
        if ($runtimeReal === false) {
            throw new \RuntimeException('应用运行目录不可用，无法执行 OSS 写入测试');
        }

        // Release deployments commonly symlink runtime/ to a shared private
        // directory. Resolve that trusted framework path first, then reject a
        // symlink specifically at the directory we own and write into.
        $directory = $runtimeReal . DIRECTORY_SEPARATOR . 'oss_check';
        if (is_link($directory)
            || (!is_dir($directory) && !@mkdir($directory, 0700, true))
            || !is_dir($directory)
            || is_link($directory)) {
            throw new \RuntimeException('无法创建 OSS 自检私有临时目录');
        }
        @chmod($directory, 0700);
        clearstatcache(true, $directory);

        $directoryReal = realpath($directory);
        $permissions = @fileperms($directory);
        if ($directoryReal === false
            || dirname($directoryReal) !== $runtimeReal
            || !is_writable($directoryReal)
            || $permissions === false
            || ($permissions & 0777) !== 0700) {
            throw new \RuntimeException('OSS 自检私有临时目录不安全或不可写');
        }

        $file = '';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $directoryReal . DIRECTORY_SEPARATOR . 'oss_zip_' . bin2hex(random_bytes(16));
            $handle = @fopen($candidate, 'x+b');
            if ($handle === false) {
                continue;
            }
            fclose($handle);
            $file = $candidate;
            break;
        }
        if ($file === '' || is_link($file)) {
            throw new \RuntimeException('无法创建受保护 ZIP 测试文件');
        }
        @chmod($file, 0600);
        clearstatcache(true, $file);

        $fileReal = realpath($file);
        $filePermissions = @fileperms($file);
        if ($fileReal === false
            || dirname($fileReal) !== $directoryReal
            || !is_file($fileReal)
            || $filePermissions === false
            || ($filePermissions & 0777) !== 0600) {
            if (is_file($file) && !is_link($file)) {
                @unlink($file);
            }
            throw new \RuntimeException('受保护 ZIP 测试文件权限不安全');
        }

        return $fileReal;
    }

    private function httpStatus(string $url): int
    {
        if ($url === '' || !function_exists('curl_init')) {
            return 0;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_RANGE => '0-0',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status;
    }

    private function repairPluginMediaUrls(PluginStorageService $storage): int
    {
        $rows = Db::name('plugin_resources')
            ->where('storage_driver', 'oss')
            ->whereIn('resource_type', ['icon', 'cover'])
            ->where('object_key', '<>', '')
            ->select()
            ->toArray();
        $updated = 0;
        foreach ($rows as $row) {
            try {
                $url = $storage->getMediaUrl((string)$row['object_key']);
            } catch (\Throwable $e) {
                continue;
            }
            Db::transaction(function () use ($row, $url, &$updated) {
                Db::name('plugin_resources')->where('id', (int)$row['id'])->update(['url' => $url]);
                $field = $row['resource_type'] === 'cover' ? 'cover' : 'icon';
                $objectField = $field . '_object_key';
                Db::name('plugin')->where('id', (int)$row['plugin_id'])->update([
                    $field => $url,
                    $objectField => (string)$row['object_key'],
                ]);
                $updated++;
            });
        }
        return $updated;
    }
}
