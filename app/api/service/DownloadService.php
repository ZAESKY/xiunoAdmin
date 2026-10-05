<?php

namespace app\api\service;

use app\api\model\AuthModel;
use app\api\model\PirateModel;
use app\api\model\VersionModel;
use app\common\service\ApiErrorService;
use app\common\service\ApplicationInstallerService;
use app\common\service\BaseService;
use app\common\service\ReleasePackageService;
use app\common\service\PluginStorageService;
use app\common\service\SecureTicketService;
use think\facade\Db;
use think\facade\Log;

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
        $sign = !empty($param['sign']) ? trim((string)$param['sign']) : '';
        if ($sign === '') {
            return json(message(t('validation.missing_sign'), false));
        }

        // 顺带清理过期票据，避免额外定时任务
        SecureTicketService::purgeExpired();

        $context = SecureTicketService::isWellFormed($sign)
            ? $this->resolveSecureTicket($sign)
            : $this->resolveLegacySign($sign);

        if ($context === null) {
            return json(message(t('validation.invalid_sign'), false));
        }

        $kind        = isset($context['kind']) ? (string)$context['kind'] : 'version';
        $versionData = isset($context['version']) && is_array($context['version']) ? $context['version'] : [];
        $authData    = $context['auth'];
        $appId       = $kind === 'app_installer'
            ? (int)($context['appid'] ?? 0)
            : (int)($versionData['appid'] ?? 0);

        if ($appId <= 0) {
            return json(message(t('validation.sign_damaged'), false));
        }

        $appInfo = Db::name('app')
            ->where([
                'id' => $appId,
                'status' => 2
            ])
            ->field('id,download_file,installer_storage_driver,installer_object_key,installer_file_name,installer_sha256,installer_size,authcode_file,public_key,pirate_msg_switch,authcode_switch,ip_switch,auth_enforce')
            ->find();
        if (empty($appInfo)) {
            return json(message(t('version.not_exist'), false));
        }

        /*
         * A-07：下载前复核授权状态。
         * 原实现只要持有 sign 就直接放行，凭证泄露即等于受保护更新包泄露。
         */
        if (!$this->authorizedToDownload($appInfo, $authData)) {
            Log::warning(sprintf(
                '[QH-API][download-denied] appid=%s auth_id=%s',
                $appId,
                $authData['id'] ?? '0'
            ));
            return json(message(t('auth.not_authorized'), false));
        }

        $downloadZip = '';
        $isTempDownload = false;
        $sourceIsTemp = false;
        $downloadName = '';
        if ($kind === 'app_installer') {
            $downloadZip = ApplicationInstallerService::location($appInfo);
            if ($downloadZip === '') {
                return json(message(t('download.public_package_invalid'), false));
            }
            $isTempDownload = ($appInfo['installer_storage_driver'] ?? 'local') === 'oss';
            $downloadName = ApplicationInstallerService::downloadName($appInfo);
        } else {
            if (($versionData['storage_driver'] ?? 'local') === 'oss') {
                try {
                    $sourceZip = (new PluginStorageService())->downloadToTemporaryFile(
                        (string)($versionData['package_object_key'] ?? ''),
                        strtolower((string)($versionData['package_sha256'] ?? '')),
                        (int)($versionData['package_size'] ?? 0)
                    );
                    $sourceIsTemp = true;
                    $isTempDownload = true;
                } catch (\Throwable $e) {
                    return json(message(t('download.cloud_package_invalid'), false));
                }
            } else {
                // A-23：目录解析统一走 ReleasePackageService（含目录名清洗与 type 判别）
                $type = !empty($versionData['type']) ? (int)$versionData['type'] : 0;
                $catalogue = ReleasePackageService::normalizeCatalogue($versionData['download_catalogue'] ?? '');
                if ($catalogue === '') {
                    return json(message(t('version.dir_empty'), false));
                }
                $downloadCatalogue = ReleasePackageService::dir($type, $catalogue);
                if ($downloadCatalogue === '' || !is_dir($downloadCatalogue)) {
                    return json(message(t('version.dir_not_exist'), false));
                }
                $sourceZip = ReleasePackageService::file($type, $catalogue);
                if ($sourceZip === '') {
                    return json(message(t('version.file_not_exist'), false));
                }
            }
            $downloadZip = $sourceZip;
            $downloadName = 'QH_' . bin2hex(random_bytes(8)) . '.zip';
        }

        // 公开引导包保持原样下载，绝不注入授权码；旧版版本包继续兼容既有逻辑。
        if ($kind !== 'app_installer' && !empty($appInfo['authcode_file'])) {
            $authcode = '';
            if ((int)$appInfo['pirate_msg_switch'] === 1 || (int)$appInfo['authcode_switch'] === 1) {
                if (empty($authData)) {
                    return json(message(t('validation.sign_damaged'), false));
                }
                $authcode = $authData['authcode'] ?? '';
            }

            $result = $this->buildAuthDownloadZip($sourceZip, $appInfo, $versionData, $authcode);
            if ($result['code'] !== 0) {
                if ($sourceIsTemp && is_file($sourceZip)) {
                    @unlink($sourceZip);
                }
                return json($result);
            }
            if ($sourceIsTemp && is_file($sourceZip)) {
                @unlink($sourceZip);
                $sourceIsTemp = false;
            }
            $downloadZip = $result['data']['file'];
            $isTempDownload = true;
        }

        // 下载次数+1
        if ($kind !== 'app_installer' && !empty($versionData['id'])) {
            Db::name('version')->where('id', intval($versionData['id']))->inc('number')->update();
        }

        // 审计：只记录 ID 与授权码尾 4 位，不落完整授权码（需求 10）
        Log::info(sprintf(
            '[QH-API][download] kind=%s appid=%s version_id=%s auth_id=%s code=%s',
            $kind,
            $appId,
            $versionData['id'] ?? '0',
            $authData['id'] ?? '0',
            isset($authData['authcode']) && $authData['authcode'] !== ''
                ? '***' . substr((string)$authData['authcode'], -4)
                : '-'
        ));

        if (preg_match('#^https://#i', $downloadZip)) {
            $safeUrl = qh_safe_url($downloadZip, false);
            if ($safeUrl === '') {
                return json(message(t('download.url_invalid'), false));
            }
            return redirect($safeUrl, 302)->header([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        $response = download($downloadZip, $downloadName);
        if ($isTempDownload && method_exists($response, 'deleteFileAfterSend')) {
            $response->deleteFileAfterSend(true);
        }
        return $response;
    }

    /**
     * 新版一次性票据：消费票据 -> 按 ID 回查版本与授权行
     *
     * 票据内不携带 authcode 等敏感值（A-25），全部按 ID 实时回查。
     */
    private function resolveSecureTicket(string $ticket): ?array
    {
        $payload = SecureTicketService::consume($ticket, request()->ip());
        if ($payload === null) {
            return null;
        }

        $kind      = isset($payload['kind']) ? (string)$payload['kind'] : 'version';
        $versionId = (int)($payload['version_id'] ?? 0);
        $authId    = (int)($payload['auth_id'] ?? 0);
        if ($kind === 'app_installer') {
            $appId = (int)($payload['appid'] ?? 0);
            if ($appId <= 0 || $authId <= 0
                || (int)($payload['_appid'] ?? 0) !== $appId
                || (int)($payload['_auth_id'] ?? 0) !== $authId) {
                return null;
            }
            $row = Db::name('auth')->where('id', $authId)->find();
            $auth = is_array($row) ? $row : [];
            if (empty($auth) || (int)($auth['appid'] ?? 0) !== $appId) {
                return null;
            }
            return ['kind' => 'app_installer', 'appid' => $appId, 'version' => [], 'auth' => $auth];
        }
        if ($kind !== 'version') {
            return null;
        }
        if ($versionId <= 0) {
            return null;
        }

        $version = Db::name('version')->where('id', $versionId)->find();
        if (empty($version)) {
            return null;
        }
        $boundAppId = (int)($payload['_appid'] ?? 0);
        if ($boundAppId > 0 && (int)($version['appid'] ?? 0) !== $boundAppId) {
            return null;
        }

        $auth = [];
        if ($authId > 0) {
            $row = Db::name('auth')->where('id', $authId)->find();
            $auth = is_array($row) ? $row : [];
            if (empty($auth)
                || (int)($payload['_auth_id'] ?? 0) !== $authId
                || (int)($auth['appid'] ?? 0) !== (int)($version['appid'] ?? 0)
            ) {
                return null;
            }
        }

        return ['kind' => 'version', 'version' => $version, 'auth' => $auth];
    }

    /**
     * 历史 md5(uniqid()) 凭证的过渡兼容。
     *
     * 老凭证最长存活 43200 秒。上线后满 12 小时即可把
     * download_legacy_sign_enabled 置 0 彻底关闭本通道（A-07 收口）。
     */
    private function resolveLegacySign(string $sign): ?array
    {
        $enabled = conf('download_legacy_sign_enabled');
        if ($enabled !== null && (int)$enabled !== 1) {
            return null;
        }

        // 仅接受历史格式，避免任意字符串探测缓存
        if (!preg_match('/^[a-f0-9]{32}$/i', $sign)) {
            return null;
        }

        $signData = @unserialize((string)cache($sign), ['allowed_classes' => false]);
        if (empty($signData) || !is_array($signData)) {
            return null;
        }

        $version = !empty($signData['versionInfo']) && is_array($signData['versionInfo'])
            ? $signData['versionInfo'] : [];
        $auth = !empty($signData['authInfo']) && is_array($signData['authInfo'])
            ? $signData['authInfo'] : [];

        if (empty($version)) {
            return null;
        }

        // 老凭证一经使用即失效，避免继续被无限次重放
        cache($sign, null);

        Log::info('[QH-API][download] legacy sign consumed, version_id=' . ($version['id'] ?? '0'));

        return ['version' => $version, 'auth' => $auth];
    }

    /**
     * 下载时的授权复核。
     *
     * 与 AuthService 的判定保持同一口径：状态、期限。
     * 为避免误伤，这里不重复做 IP/authcode 判定（那些在 checkUpdate 阶段已判过），
     * 只拦截「票据签发后授权被吊销或已过期」这类状态变化。
     */
    private function authorizedToDownload(array $appInfo, array $authData): bool
    {
        // 应用处于监控模式时不因授权状态拦截下载，与 checkAuth 行为保持一致
        if (isset($appInfo['auth_enforce']) && (int)$appInfo['auth_enforce'] === 2) {
            return true;
        }

        // 无授权记录：仅当该应用完全不做授权校验时才允许
        if (empty($authData)) {
            return (int)$appInfo['pirate_msg_switch'] !== 1 && (int)$appInfo['authcode_switch'] !== 1;
        }

        if ((int)$authData['status'] !== 1) {
            return false;
        }

        if ((int)$authData['permanent_switch'] === 0) {
            if (empty($authData['endtime']) || strtotime((string)$authData['endtime']) <= time()) {
                return false;
            }
        }

        return true;
    }

    private function buildAuthDownloadZip($sourceZip, array $appInfo, array $versionData, $authcode)
    {
        if (!class_exists('ZipArchive')) {
            return message(t('version.zip_not_installed'), false);
        }

        $authCodeFile = $this->normalizeRelativeFile($appInfo['authcode_file'] ?? '');
        if ($authCodeFile === '') {
            return message(t('version.auth_path_invalid'), false);
        }

        $tempDir = RUNTIME_PATH . DS . 'download' . DS;
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $this->clearExpiredTempFiles($tempDir);

        $tempFile = $tempDir . 'QH_' . uniqid('', true) . '.zip';
        $workDir = $tempDir . 'work_' . uniqid('', true) . DS;
        $zip = new \ZipArchive();

        try {
            if (!copy($sourceZip, $tempFile) || !is_file($tempFile)) {
                return message(t('version.copy_file_failed'), false);
            }

            $template = APP_PATH . DS . 'common' . DS . 'download' . DS . 'AuthInfo.php';
            $fileContent = file_get_contents($template);
            if ($fileContent === false) {
                return message(t('version.auth_template_read_failed'), false);
            }

            $fileContent = str_replace('QH_AUTHCODE', (string)$authcode, $fileContent);
            $fileContent = str_replace('QH_VERSION', (string)($versionData['version'] ?? ''), $fileContent);
            $fileContent = str_replace('QH_EDITION', (string)($versionData['edition'] ?? ''), $fileContent);
            $fileContent = str_replace('QH_PUBLIC_KEY', (string)($appInfo['public_key'] ?? ''), $fileContent);

            $targetFile = $workDir . str_replace('/', DS, $authCodeFile);
            if (!is_dir(dirname($targetFile))) {
                @mkdir(dirname($targetFile), 0755, true);
            }
            if (file_put_contents($targetFile, $fileContent) === false) {
                return message(t('version.auth_file_gen_failed'), false);
            }

            if ($zip->open($tempFile) !== true) {
                return message(t('version.temp_zip_open_failed'), false);
            }
            $zip->addFile($targetFile, $authCodeFile);
            $zip->close();

            return message('success', true, ['file' => $tempFile]);
        } catch (\Throwable $e) {
            @$zip->close();
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
            // A-17：不再把异常原文回显给下载方
            return ApiErrorService::fail($e, 'version.auth_file_gen_failed', [
                'version_id' => $versionData['id'] ?? 0,
            ]);
        } finally {
            if (is_dir($workDir)) {
                rmdirs($workDir);
            }
        }
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
        foreach (glob($tempDir . 'QH_*.zip') ?: [] as $file) {
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
