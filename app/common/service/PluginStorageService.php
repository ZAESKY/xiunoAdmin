<?php

namespace app\common\service;

use think\Exception;

/**
 * 插件资源存储服务：优先走阿里云 OSS，未配置时保留本地开发上传能力。
 */
class PluginStorageService
{
    private $bucket;
    private $endpoint;
    private $accessKeyId;
    private $accessKeySecret;
    private $publicBaseUrl;
    private $privateBucket;
    private $ossEnabled;
    private $localPackagePath;

    public function __construct()
    {
        $this->ossEnabled = filter_var($this->configValue('oss_enabled', env('oss_enabled', false)), FILTER_VALIDATE_BOOLEAN);
        $this->bucket = $this->configValue('oss_bucket', '');
        $this->endpoint = $this->configValue('oss_endpoint', '');
        $this->accessKeyId = $this->configValue('oss_access_key_id', '');
        $this->accessKeySecret = $this->configValue('oss_access_key_secret', '');
        $this->publicBaseUrl = rtrim($this->configValue('oss_public_base_url', ''), '/');
        $this->privateBucket = filter_var($this->configValue('oss_use_private_bucket', env('oss_use_private_bucket', false)), FILTER_VALIDATE_BOOLEAN);
        $this->localPackagePath = $this->configValue('plugin_storage_path', '');
    }

    private function configValue(string $name, $default = ''): string
    {
        $value = null;
        if (function_exists('conf')) {
            $value = conf($name);
        }
        if ($value === null || $value === '') {
            $value = env($name, $default);
        }
        return trim((string)$value);
    }

    public function isOssConfigured(): bool
    {
        return $this->ossEnabled
            && (bool)preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/D', $this->bucket)
            && $this->endpoint !== ''
            && $this->accessKeyId !== ''
            && $this->accessKeySecret !== '';
    }

    public function hasIncompleteOssConfig(): bool
    {
        return $this->ossEnabled && !$this->isOssConfigured();
    }

    public function isOssEnabled(): bool
    {
        return $this->ossEnabled;
    }

    public function storeUploadedFile($file, string $resourceType, array $allowedExt, int $maxSize): array
    {
        if (!$file) {
            throw new Exception(t('plugin_storage.file_required'));
        }

        $ext = strtolower((string)$file->getOriginalExtension());
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception(t('plugin_storage.extension_not_supported', ['extensions' => implode('/', $allowedExt)]));
        }
        if ($file->getSize() <= 0 || $file->getSize() > $maxSize) {
            throw new Exception(t('plugin_storage.file_too_large', ['size' => $this->formatSize($maxSize)]));
        }
        if ($this->hasIncompleteOssConfig()) {
            throw new Exception(t('plugin_storage.oss_config_incomplete'));
        }

        $originalName = $file->getOriginalName();
        $mimeType = $this->detectMimeType($file->getPathname(), $originalName);
        if (in_array($resourceType, ['icon', 'cover', 'image', 'feedback', 'richtext'], true)) {
            $this->assertValidImageFile($file->getPathname(), $ext);
        }
        if ($resourceType === 'package') {
            $zipProblem = SafeZipService::validate($file->getPathname(), [], 10000, 1073741824);
            if ($zipProblem !== null) {
                throw new Exception($zipProblem);
            }
        }
        $objectKey = $this->buildObjectKey($resourceType, $ext);

        if ($this->isOssConfigured()) {
            $this->putObjectFromFile($objectKey, $file->getPathname(), $mimeType);
            $url = $this->isPublicMediaObjectKey($objectKey) ? $this->getMediaUrl($objectKey) : '';
            return [
                'storage_driver' => 'oss',
                'path' => $url,
                'url' => $url,
                'object_key' => $objectKey,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $mimeType,
                'file_hash' => hash_file('sha256', $file->getPathname()),
            ];
        }

        $baseDir = $resourceType === 'package'
            ? $this->localPackageDirectory()
            : app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . date('Ymd');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $finalName = basename($objectKey);
        $file->move($baseDir, $finalName);
        $finalFile = $baseDir . DIRECTORY_SEPARATOR . $finalName;
        $publicPath = $resourceType === 'package'
            ? $finalFile
            : '/upload/plugins/' . date('Ymd') . '/' . $finalName;

        return [
            'storage_driver' => 'local',
            'path' => $publicPath,
            'url' => $publicPath,
            'object_key' => '',
            'file_name' => $originalName,
            'file_size' => filesize($finalFile),
            'mime_type' => $this->detectMimeType($finalFile, $originalName),
            'file_hash' => hash_file('sha256', $finalFile),
        ];
    }

    public function storeBytes(string $content, string $resourceType, string $fileName, string $mimeType): array
    {
        if ($this->hasIncompleteOssConfig()) {
            throw new Exception(t('plugin_storage.oss_environment_incomplete'));
        }
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: 'bin');
        if (in_array($resourceType, ['icon', 'cover', 'image', 'feedback', 'richtext'], true)) {
            if (strlen($content) === 0 || strlen($content) > 5 * 1024 * 1024) {
                throw new Exception(t('plugin_storage.image_size_invalid'));
            }
            $info = @getimagesizefromstring($content);
            $actualMime = strtolower((string)($info['mime'] ?? ''));
            $expected = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ];
            if (!isset($expected[$ext]) || $actualMime !== $expected[$ext]) {
                throw new Exception(t('plugin_storage.image_extension_mismatch'));
            }
            $mimeType = $actualMime;
        }
        $objectKey = $this->buildObjectKey($resourceType, $ext);

        if ($this->isOssConfigured()) {
            $this->putObject($objectKey, $content, $mimeType);
            $url = $this->isPublicMediaObjectKey($objectKey) ? $this->getMediaUrl($objectKey) : '';
            return [
                'storage_driver' => 'oss',
                'path' => $url,
                'url' => $url,
                'object_key' => $objectKey,
                'file_name' => $fileName,
                'file_size' => strlen($content),
                'mime_type' => $mimeType,
                'file_hash' => hash('sha256', $content),
            ];
        }

        $dir = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . date('Ymd');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $finalName = basename($objectKey);
        file_put_contents($dir . DIRECTORY_SEPARATOR . $finalName, $content);
        return [
            'storage_driver' => 'local',
            'path' => '/upload/plugins/' . date('Ymd') . '/' . $finalName,
            'url' => '/upload/plugins/' . date('Ymd') . '/' . $finalName,
            'object_key' => '',
            'file_name' => $fileName,
            'file_size' => strlen($content),
            'mime_type' => $mimeType,
            'file_hash' => hash('sha256', $content),
        ];
    }

    public function getDownloadUrl(array $record, int $expires = 300): string
    {
        $driver = $record['storage_driver'] ?? 'local';
        if ($driver === 'oss') {
            $objectKey = $record['package_object_key'] ?? $record['object_key'] ?? '';
            if (!$this->isValidPackageObjectKey((string)$objectKey)) {
                throw new Exception(t('plugin_storage.version_file_missing'));
            }
            if (!$this->isOssConfigured()) {
                throw new Exception(t('plugin_storage.oss_history_url_unavailable'));
            }
            return $this->buildSignedUrl($objectKey, max(30, min($expires, 300)));
        }

        if ($driver !== 'local') {
            throw new Exception(t('plugin_storage.driver_invalid'));
        }
        $path = (string)($record['package_path'] ?? $record['file_path'] ?? '');
        return $this->resolveLocalPackagePath($path);
    }

    /**
     * Resolve a plugin package to a local file. OSS objects are materialized
     * into a verified 0600 runtime file so clients never receive an OSS URL.
     */
    public function getDownloadFile(array $record): string
    {
        if (($record['storage_driver'] ?? 'local') !== 'oss') {
            return $this->getDownloadUrl($record);
        }
        $objectKey = (string)($record['package_object_key'] ?? $record['object_key'] ?? '');
        if (!$this->isValidPackageObjectKey($objectKey)) {
            throw new Exception(t('plugin_storage.version_file_missing'));
        }
        $hash = strtolower((string)($record['package_sha256'] ?? $record['package_hash'] ?? $record['file_hash'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            $hash = '';
        }
        $size = (int)($record['package_size'] ?? $record['package_file_size'] ?? $record['file_size'] ?? 0);
        return $this->downloadToTemporaryFile($objectKey, $hash, $size);
    }

    public function deleteTemporaryDownloadFile(string $path): bool
    {
        $real = realpath($path);
        $base = realpath(app()->getRuntimePath() . 'oss_download');
        if ($real === false || $base === false || !is_file($real) || is_link($real)) {
            return false;
        }
        $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($real, $prefix) && @unlink($real);
    }

    /**
     * Validate user-submitted package metadata without exposing a local path.
     */
    public function assertValidPackageRecord(array $record): void
    {
        $driver = (string)($record['storage_driver'] ?? 'local');
        if ($driver === 'oss') {
            $objectKey = (string)($record['package_object_key'] ?? $record['object_key'] ?? '');
            if (!$this->isValidPackageObjectKey($objectKey)) {
                throw new Exception(t('plugin_storage.package_object_key_invalid'));
            }
            return;
        }
        if ($driver !== 'local') {
            throw new Exception(t('plugin_storage.driver_invalid'));
        }
        $this->resolveLocalPackagePath((string)($record['package_path'] ?? $record['file_path'] ?? ''));
    }

    public function deleteLocalPackage(array $record): bool
    {
        if (($record['storage_driver'] ?? 'local') !== 'local') {
            return false;
        }
        try {
            $path = $this->resolveLocalPackagePath((string)($record['package_path'] ?? $record['file_path'] ?? ''));
        } catch (\Throwable $e) {
            return false;
        }
        return @unlink($path);
    }

    public function deletePackage(array $record): bool
    {
        if (($record['storage_driver'] ?? 'local') === 'oss') {
            return $this->deleteObject((string)($record['package_object_key'] ?? $record['object_key'] ?? ''));
        }
        return $this->deleteLocalPackage($record);
    }

    /**
     * Upload an already assembled server-side file without reading the entire
     * package into PHP memory. Chunked application and release uploads use it.
     */
    public function storePath(string $filePath, string $resourceType, string $fileName = '', string $mimeType = ''): array
    {
        if ($filePath === '' || !is_file($filePath) || is_link($filePath)) {
            throw new Exception(t('plugin_storage.source_file_missing'));
        }
        if ($this->hasIncompleteOssConfig()) {
            throw new Exception(t('plugin_storage.oss_config_incomplete'));
        }
        $fileName = $fileName !== '' ? basename(str_replace('\\', '/', $fileName)) : basename($filePath);
        $ext = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext === '' || !preg_match('/^[a-z0-9]{1,12}$/D', $ext)) {
            throw new Exception(t('plugin_storage.extension_invalid'));
        }
        $mimeType = $mimeType !== '' ? $mimeType : $this->detectMimeType($filePath, $fileName);
        $objectKey = $this->buildObjectKey($resourceType, $ext);
        $size = (int)filesize($filePath);
        $hash = (string)hash_file('sha256', $filePath);

        if ($this->isOssConfigured()) {
            $this->putObjectFromFile($objectKey, $filePath, $mimeType);
            return [
                'storage_driver' => 'oss',
                'path' => '',
                'url' => '',
                'object_key' => $objectKey,
                'file_name' => $fileName,
                'file_size' => $size,
                'mime_type' => $mimeType,
                'file_hash' => $hash,
            ];
        }

        return [
            'storage_driver' => 'local',
            'path' => $filePath,
            'url' => $filePath,
            'object_key' => '',
            'file_name' => $fileName,
            'file_size' => $size,
            'mime_type' => $mimeType,
            'file_hash' => $hash,
        ];
    }

    /** Return a stable application URL while keeping the OSS URL private. */
    public function getMediaUrl(string $objectKey): string
    {
        if (!$this->isPublicMediaObjectKey($objectKey)) {
            throw new Exception(t('plugin_storage.media_object_key_invalid'));
        }
        $token = hash_hmac('sha256', $objectKey, $this->mediaTokenKey());
        return '/index.php/Storage/media.html?key=' . rawurlencode($objectKey)
            . '&token=' . rawurlencode($token);
    }

    /** Validate the stable URL and issue a very short-lived OSS URL. */
    public function getMediaRedirectUrl(string $objectKey, string $token, int $expires = 60): string
    {
        if (!$this->isOssConfigured() || !$this->isPublicMediaObjectKey($objectKey)) {
            throw new Exception(t('plugin_storage.media_file_missing'));
        }
        $expected = hash_hmac('sha256', $objectKey, $this->mediaTokenKey());
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($expected, $token)) {
            throw new Exception(t('plugin_storage.media_credential_invalid'));
        }
        return $this->buildSignedUrl($objectKey, max(30, min($expires, 120)));
    }

    public function objectDownloadUrl(string $objectKey, int $expires = 120): string
    {
        if (!$this->isOssConfigured() || !$this->isValidManagedObjectKey($objectKey)) {
            throw new Exception(t('plugin_storage.object_missing'));
        }
        return $this->buildSignedUrl($objectKey, max(30, min($expires, 300)));
    }

    /** Download a protected package for server-side processing (e.g. legacy injection). */
    public function downloadToTemporaryFile(string $objectKey, string $expectedHash = '', int $expectedSize = 0): string
    {
        if (!$this->isOssConfigured() || !$this->isValidProtectedObjectKey($objectKey) || !function_exists('curl_init')) {
            throw new Exception(t('plugin_storage.cloud_package_unavailable'));
        }
        if ($expectedHash !== '' && !preg_match('/^[a-f0-9]{64}$/D', strtolower($expectedHash))) {
            throw new Exception(t('plugin_storage.package_hash_invalid'));
        }
        if ($expectedSize < 0 || $expectedSize > 1073741824) {
            throw new Exception(t('plugin_storage.package_size_invalid'));
        }

        [$temp, $stream] = $this->createPrivateDownloadStream();

        $ok = false;
        $status = 0;
        try {
            $ch = curl_init($this->buildSignedUrl($objectKey, 300));
            if ($ch === false) {
                throw new Exception(t('plugin_storage.cloud_download_init_failed'));
            }
            curl_setopt_array($ch, [
                CURLOPT_FILE => $stream,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } catch (\Throwable $e) {
            @unlink($temp);
            throw $e;
        } finally {
            fclose($stream);
        }

        // File creation and the streamed write can leave the initial zero-byte
        // stat result in PHP's cache. Clear it before integrity checks.
        clearstatcache(true, $temp);
        $actualSize = is_file($temp) ? (int)filesize($temp) : 0;
        $actualHash = is_file($temp) ? strtolower((string)hash_file('sha256', $temp)) : '';
        if ($ok === false || $status < 200 || $status >= 300
            || $actualSize <= 0
            || ($expectedSize > 0 && $actualSize !== $expectedSize)
            || ($expectedHash !== '' && !hash_equals(strtolower($expectedHash), $actualHash))) {
            @unlink($temp);
            throw new Exception(t('plugin_storage.cloud_download_failed'));
        }
        register_shutdown_function([$this, 'deleteTemporaryDownloadFile'], $temp);
        return $temp;
    }

    /** Create an exclusive 0600 stream below the resolved private runtime directory. */
    private function createPrivateDownloadStream(): array
    {
        $runtime = rtrim((string)app()->getRuntimePath(), '/\\');
        $runtimeReal = $runtime === '' ? false : realpath($runtime);
        if ($runtimeReal === false || !is_dir($runtimeReal) || !is_writable($runtimeReal)) {
            throw new Exception(t('plugin_storage.runtime_directory_unavailable'));
        }

        $directory = $runtimeReal . DIRECTORY_SEPARATOR . 'oss_download';
        if (is_link($directory)
            || (!is_dir($directory) && !@mkdir($directory, 0700, true))
            || !is_dir($directory)
            || is_link($directory)) {
            throw new Exception(t('plugin_storage.temp_directory_create_failed'));
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
            throw new Exception(t('plugin_storage.temp_directory_unsafe'));
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $directoryReal . DIRECTORY_SEPARATOR . 'oss_' . bin2hex(random_bytes(16));
            $stream = @fopen($candidate, 'x+b');
            if ($stream === false) {
                continue;
            }
            @chmod($candidate, 0600);
            clearstatcache(true, $candidate);
            $fileReal = realpath($candidate);
            $filePermissions = @fileperms($candidate);
            if ($fileReal !== false
                && dirname($fileReal) === $directoryReal
                && is_file($fileReal)
                && !is_link($fileReal)
                && $filePermissions !== false
                && ($filePermissions & 0777) === 0600) {
                return [$fileReal, $stream];
            }
            fclose($stream);
            if (is_file($candidate) && !is_link($candidate)) {
                @unlink($candidate);
            }
            throw new Exception(t('plugin_storage.temp_file_permissions_unsafe'));
        }

        throw new Exception(t('plugin_storage.temp_file_create_failed'));
    }

    public function deleteObject(string $objectKey): bool
    {
        if (!$this->isOssConfigured() || !$this->isValidManagedObjectKey($objectKey) || !function_exists('curl_init')) {
            return false;
        }
        $date = gmdate('D, d M Y H:i:s \\G\\M\\T');
        $resource = '/' . $this->bucket . '/' . ltrim($objectKey, '/');
        $stringToSign = "DELETE\n\n\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        $ch = curl_init('https://' . $this->buildHost() . '/' . ltrim($objectKey, '/'));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Date: ' . $date,
                'Authorization: OSS ' . $this->accessKeyId . ':' . $signature,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response !== false && in_array($status, [200, 204, 404], true);
    }

    private function buildObjectKey(string $resourceType, string $ext): string
    {
        $prefixes = [
            'package' => 'plugins/package',
            'icon' => 'plugins/icon',
            'cover' => 'plugins/cover',
            'image' => 'uploads/images',
            'feedback' => 'uploads/feedback',
            'richtext' => 'uploads/richtext',
            'file' => 'uploads/files',
            'application_installer' => 'packages/installers',
            'application_release' => 'packages/releases',
            'application_update' => 'packages/updates',
            'program_patch' => 'packages/patches',
        ];
        if (!isset($prefixes[$resourceType])) {
            throw new Exception(t('plugin_storage.resource_type_invalid'));
        }
        return $prefixes[$resourceType] . '/' . date('Y/m/d') . '/' . date('His') . '_'
            . bin2hex(random_bytes(8)) . '.' . $ext;
    }

    private function localPackageDirectory(): string
    {
        if ($this->localPackagePath !== '') {
            return rtrim($this->localPackagePath, '/\\');
        }
        return app()->getRootPath() . 'storage' . DIRECTORY_SEPARATOR . 'plugins';
    }

    private function resolveLocalPackagePath(string $path): string
    {
        if ($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'zip') {
            throw new Exception(t('plugin_storage.version_file_missing'));
        }
        $realPath = realpath($path);
        $basePath = realpath($this->localPackageDirectory());
        if ($realPath === false || $basePath === false || !is_file($realPath)) {
            throw new Exception(t('plugin_storage.version_file_missing'));
        }
        $prefix = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realPath, $prefix)) {
            throw new Exception(t('plugin_storage.path_outside_root'));
        }
        return $realPath;
    }

    private function isValidPackageObjectKey(string $objectKey): bool
    {
        return (bool)preg_match('#^plugins/package/[0-9]{4}/[0-9]{2}/[0-9]{2}/[A-Za-z0-9._-]+\.zip$#D', $objectKey);
    }

    private function isValidProtectedObjectKey(string $objectKey): bool
    {
        return $this->isValidPackageObjectKey($objectKey)
            || (bool)preg_match('#^packages/(?:installers|releases|updates|patches)/[0-9]{4}/[0-9]{2}/[0-9]{2}/[A-Za-z0-9._-]+\.zip$#D', $objectKey);
    }

    private function isPublicMediaObjectKey(string $objectKey): bool
    {
        return (bool)preg_match('#^(?:plugins/(?:icon|cover)|uploads/(?:images|feedback|richtext|files))/[0-9]{4}/[0-9]{2}/[0-9]{2}/[A-Za-z0-9._-]+\.[A-Za-z0-9]{1,12}$#D', $objectKey);
    }

    private function isValidManagedObjectKey(string $objectKey): bool
    {
        return $this->isValidProtectedObjectKey($objectKey) || $this->isPublicMediaObjectKey($objectKey);
    }

    private function mediaTokenKey(): string
    {
        $pepper = trim((string)env('security_pepper', ''));
        $key = $pepper !== '' ? $pepper : $this->accessKeySecret;
        if ($key === '') {
            throw new Exception(t('plugin_storage.media_signing_key_missing'));
        }
        return hash('sha256', 'oss-media-v1|' . $key, true);
    }

    private function assertValidImageFile(string $path, string $extension): void
    {
        $info = @getimagesize($path);
        $mime = strtolower((string)($info['mime'] ?? ''));
        $allowed = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
        ];
        if (!isset($allowed[$extension]) || $mime !== $allowed[$extension]) {
            throw new Exception(t('plugin_storage.image_extension_mismatch'));
        }
    }

    private function normalizeEndpoint(): string
    {
        $endpoint = $this->endpoint;
        if ($endpoint === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $endpoint)) {
            $endpoint = 'https://' . $endpoint;
        }
        $endpoint = rtrim($endpoint, '/');
        $parts = parse_url($endpoint);
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || $host === ''
            || (!str_ends_with($host, '.aliyuncs.com') && $host !== 'aliyuncs.com')
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['port'])
            || !empty($parts['path'])
        ) {
            throw new Exception(t('plugin_storage.endpoint_invalid'));
        }
        return $endpoint;
    }

    private function buildHost(): string
    {
        $endpoint = preg_replace('#^https?://#i', '', $this->normalizeEndpoint());
        if (strpos($endpoint, $this->bucket . '.') === 0) {
            return $endpoint;
        }
        return $this->bucket . '.' . $endpoint;
    }

    private function buildPublicUrl(string $objectKey): string
    {
        if ($this->publicBaseUrl !== '') {
            $url = $this->publicBaseUrl . '/' . ltrim($objectKey, '/');
            $safeUrl = qh_safe_url($url, false);
            if ($safeUrl === '' || strtolower((string)parse_url($safeUrl, PHP_URL_SCHEME)) !== 'https') {
                throw new Exception(t('plugin_storage.public_url_invalid'));
            }
            return $safeUrl;
        }
        return 'https://' . $this->buildHost() . '/' . ltrim($objectKey, '/');
    }

    private function putObjectFromFile(string $objectKey, string $filePath, string $mimeType): void
    {
        $size = @filesize($filePath);
        $stream = @fopen($filePath, 'rb');
        if ($size === false || $size < 1 || $stream === false) {
            throw new Exception(t('upload.read_failed'));
        }
        try {
            $this->executePut($objectKey, $mimeType, $stream, (int)$size, null);
        } finally {
            fclose($stream);
        }
    }

    private function putObject(string $objectKey, string $content, string $mimeType): void
    {
        $this->executePut($objectKey, $mimeType, null, strlen($content), $content);
    }

    private function executePut(string $objectKey, string $mimeType, $stream, int $size, ?string $content): void
    {
        if (!function_exists('curl_init')) {
            throw new Exception(t('plugin_storage.curl_missing'));
        }
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $resource = '/' . $this->bucket . '/' . ltrim($objectKey, '/');
        $stringToSign = "PUT\n\n{$mimeType}\n{$date}\nx-oss-object-acl:private\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        $url = rtrim($this->normalizeEndpoint(), '/') . '/' . ltrim($objectKey, '/');
        if (strpos(parse_url($url, PHP_URL_HOST), $this->bucket . '.') !== 0) {
            $scheme = parse_url($this->normalizeEndpoint(), PHP_URL_SCHEME) ?: 'https';
            $url = $scheme . '://' . $this->buildHost() . '/' . ltrim($objectKey, '/');
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Date: ' . $date,
                'Content-Type: ' . $mimeType,
                'Content-Length: ' . $size,
                'x-oss-object-acl: private',
                'Authorization: OSS ' . $this->accessKeyId . ':' . $signature,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if (is_resource($stream)) {
            $options[CURLOPT_UPLOAD] = true;
            $options[CURLOPT_INFILE] = $stream;
            $options[CURLOPT_INFILESIZE] = $size;
        } else {
            $options[CURLOPT_POSTFIELDS] = (string)$content;
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            throw new Exception(t('plugin_storage.oss_upload_failed', [
                'detail' => $error ?: 'HTTP ' . $httpCode,
            ]));
        }
    }

    private function buildSignedUrl(string $objectKey, int $expires): string
    {
        $expireAt = time() + $expires;
        $resource = '/' . $this->bucket . '/' . ltrim($objectKey, '/');
        $stringToSign = "GET\n\n\n{$expireAt}\n{$resource}";
        $signature = rawurlencode(base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true)));
        return 'https://' . $this->buildHost() . '/' . ltrim($objectKey, '/') .
            '?OSSAccessKeyId=' . rawurlencode($this->accessKeyId) .
            '&Expires=' . $expireAt .
            '&Signature=' . $signature;
    }

    private function detectMimeType(string $path, string $fileName): string
    {
        if (function_exists('mime_content_type') && is_file($path)) {
            $mime = mime_content_type($path);
            if ($mime) {
                return $mime;
            }
        }
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $map = [
            'zip' => 'application/zip',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    private function formatSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? round($bytes / 1024 / 1024, 1) . 'MB' : round($bytes / 1024, 1) . 'KB';
    }
}
