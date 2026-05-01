<?php

namespace app\api\service;

use app\api\model\AuthModel;
use app\api\model\PirateModel;
use app\api\model\VersionModel;
use app\common\service\BaseService;
use think\facade\Db;

class DownloadService extends BaseService
{
    public function __construct()
    {
        $this->model = new AuthModel();
        $this->pirateModel = new PirateModel();
        $this->versionModel = new VersionModel();
        $this->authService = new AuthService();
    }

    public function download()
    {
        $param = request()->param();
        $sign = !empty($param['sign']) ? $param['sign'] : null;
        if (!$sign) {
            return json(message('请提交SIGN！', false));
        }

        $signData = @unserialize((string)cache($sign), ['allowed_classes' => false]);
        if (empty($signData) || !is_array($signData)) {
            return json(message('不存在此SIGN！', false));
        }

        $versionData = !empty($signData['versionInfo']) && is_array($signData['versionInfo']) ? $signData['versionInfo'] : [];
        if (empty($versionData) || empty($versionData['appid'])) {
            return json(message('SIGN损坏，请重新生成！', false));
        }

        $appInfo = Db::name('app')
            ->where([
                'id' => $versionData['appid'],
                'status' => 2
            ])
            ->field('authcode_file,public_key,pirate_msg_switch')
            ->find();
        if (empty($appInfo)) {
            return json(message('不存在此版本', false));
        }

        $type = !empty($versionData['type']) ? (int)$versionData['type'] : 0;
        $downloadCatalogueName = $this->normalizePathName($versionData['download_catalogue'] ?? '');
        if ($downloadCatalogueName === '') {
            return json(message('下载目录不合法', false));
        }

        $downloadCatalogue = APP_PATH . DS . 'common' . DS . 'download' . DS . ($type === 0 ? 'release' : 'update') . DS . $downloadCatalogueName . DS;
        $sourceZip = $downloadCatalogue . 'SF.zip';
        if (!is_dir($downloadCatalogue)) {
            return json(message('该版本下载目录不存在', false));
        }
        if (!is_file($sourceZip)) {
            return json(message('该版本更新文件不存在', false));
        }

        $downloadZip = $sourceZip;
        $isTempDownload = false;
        if (!empty($appInfo['authcode_file'])) {
            $authcode = '';
            if ((int)$appInfo['pirate_msg_switch'] === 1) {
                $authData = !empty($signData['authInfo']) && is_array($signData['authInfo']) ? $signData['authInfo'] : [];
                if (empty($authData)) {
                    return json(message('SIGN损坏，请重新生成！', false));
                }
                $authcode = $authData['authcode'] ?? '';
            }

            $result = $this->buildAuthDownloadZip($sourceZip, $appInfo, $versionData, $authcode);
            if ($result['code'] !== 0) {
                return json($result);
            }
            $downloadZip = $result['data']['file'];
            $isTempDownload = true;
        }

        $response = download($downloadZip, 'SF_' . uniqid() . '.zip');
        if ($isTempDownload && method_exists($response, 'deleteFileAfterSend')) {
            $response->deleteFileAfterSend(true);
        }
        return $response;
    }

    private function buildAuthDownloadZip($sourceZip, array $appInfo, array $versionData, $authcode)
    {
        if (!class_exists('ZipArchive')) {
            return message('ZipArchive 没有安装', false);
        }

        $authCodeFile = $this->normalizeRelativeFile($appInfo['authcode_file'] ?? '');
        if ($authCodeFile === '') {
            return message('授权文件路径不合法', false);
        }

        $tempDir = RUNTIME_PATH . DS . 'download' . DS;
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $this->clearExpiredTempFiles($tempDir);

        $tempFile = $tempDir . 'SF_' . uniqid('', true) . '.zip';
        $workDir = $tempDir . 'work_' . uniqid('', true) . DS;
        $zip = new \ZipArchive();

        try {
            if (!copy($sourceZip, $tempFile) || !is_file($tempFile)) {
                return message('复制文件失败', false);
            }

            $template = APP_PATH . DS . 'common' . DS . 'download' . DS . 'AuthInfo.php';
            $fileContent = file_get_contents($template);
            if ($fileContent === false) {
                return message('授权模板读取失败', false);
            }

            $fileContent = str_replace('SF_AUTHCODE', (string)$authcode, $fileContent);
            $fileContent = str_replace('SF_VERSION', (string)($versionData['version'] ?? ''), $fileContent);
            $fileContent = str_replace('SF_EDITION', (string)($versionData['edition'] ?? ''), $fileContent);
            $fileContent = str_replace('SF_PUBLIC_KEY', (string)($appInfo['public_key'] ?? ''), $fileContent);

            $targetFile = $workDir . str_replace('/', DS, $authCodeFile);
            if (!is_dir(dirname($targetFile))) {
                @mkdir(dirname($targetFile), 0755, true);
            }
            if (file_put_contents($targetFile, $fileContent) === false) {
                return message('授权文件生成失败', false);
            }

            if ($zip->open($tempFile) !== true) {
                return message('临时压缩包打开失败', false);
            }
            $zip->addFile($targetFile, $authCodeFile);
            $zip->close();

            return message('success', true, ['file' => $tempFile]);
        } catch (\Throwable $e) {
            @$zip->close();
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
            return message($e->getMessage(), false);
        } finally {
            if (is_dir($workDir)) {
                rmdirs($workDir);
            }
        }
    }

    private function normalizePathName($name)
    {
        $name = trim((string)$name);
        return preg_match('/^[A-Za-z0-9_-]{1,120}$/', $name) ? $name : '';
    }

    private function normalizeRelativeFile($path)
    {
        $path = str_replace('\\', '/', trim((string)$path, "/ \t\n\r\0\x0B"));
        if ($path === '' || strpos($path, '../') !== false || strpos($path, '..\\') !== false || preg_match('/^[A-Za-z]:/', $path)) {
            return '';
        }

        $parts = array_filter(explode('/', $path), 'strlen');
        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z0-9_.-]+$/', $part)) {
                return '';
            }
        }

        return implode('/', $parts);
    }

    private function clearExpiredTempFiles($tempDir)
    {
        foreach (glob($tempDir . 'SF_*.zip') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - 3600) {
                @unlink($file);
            }
        }
        foreach (glob($tempDir . 'work_*') ?: [] as $dir) {
            if (is_dir($dir) && filemtime($dir) < time() - 3600) {
                rmdirs($dir);
            }
        }
    }
}
