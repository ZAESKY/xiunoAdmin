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

    public function __construct()
    {
        $this->ossEnabled = filter_var($this->configValue('oss_enabled', env('oss_enabled', false)), FILTER_VALIDATE_BOOLEAN);
        $this->bucket = $this->configValue('oss_bucket', '');
        $this->endpoint = $this->configValue('oss_endpoint', '');
        $this->accessKeyId = $this->configValue('oss_access_key_id', '');
        $this->accessKeySecret = $this->configValue('oss_access_key_secret', '');
        $this->publicBaseUrl = rtrim($this->configValue('oss_public_base_url', ''), '/');
        $this->privateBucket = filter_var($this->configValue('oss_use_private_bucket', env('oss_use_private_bucket', false)), FILTER_VALIDATE_BOOLEAN);
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
        return $this->ossEnabled && $this->bucket !== '' && $this->endpoint !== '' && $this->accessKeyId !== '' && $this->accessKeySecret !== '';
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
            throw new Exception('请选择上传文件');
        }

        $ext = strtolower((string)$file->getOriginalExtension());
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception('文件格式不支持，仅支持：' . implode('/', $allowedExt));
        }
        if ($file->getSize() > $maxSize) {
            throw new Exception('文件大小不能超过 ' . $this->formatSize($maxSize));
        }
        if ($this->hasIncompleteOssConfig()) {
            throw new Exception('OSS 配置不完整，请检查 oss_access_key_id、oss_access_key_secret、oss_bucket、oss_endpoint');
        }

        $originalName = $file->getOriginalName();
        $mimeType = $this->detectMimeType($file->getPathname(), $originalName);
        $objectKey = $this->buildObjectKey($resourceType, $ext);

        if ($this->isOssConfigured()) {
            $this->putObjectFromFile($objectKey, $file->getPathname(), $mimeType);
            return [
                'storage_driver' => 'oss',
                'path' => $this->buildPublicUrl($objectKey),
                'url' => $this->buildPublicUrl($objectKey),
                'object_key' => $objectKey,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $mimeType,
                'file_hash' => md5_file($file->getPathname()),
            ];
        }

        $baseDir = $resourceType === 'package'
            ? app()->getRootPath() . 'storage' . DIRECTORY_SEPARATOR . 'plugins'
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
            'file_hash' => md5_file($finalFile),
        ];
    }

    public function storeBytes(string $content, string $resourceType, string $fileName, string $mimeType): array
    {
        if ($this->hasIncompleteOssConfig()) {
            throw new Exception('OSS 配置不完整，请检查 OSS 环境变量');
        }
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: 'bin');
        $objectKey = $this->buildObjectKey($resourceType, $ext);

        if ($this->isOssConfigured()) {
            $this->putObject($objectKey, $content, $mimeType);
            return [
                'storage_driver' => 'oss',
                'path' => $this->buildPublicUrl($objectKey),
                'url' => $this->buildPublicUrl($objectKey),
                'object_key' => $objectKey,
                'file_name' => $fileName,
                'file_size' => strlen($content),
                'mime_type' => $mimeType,
                'file_hash' => md5($content),
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
            'file_hash' => md5($content),
        ];
    }

    public function getDownloadUrl(array $record, int $expires = 300): string
    {
        $driver = $record['storage_driver'] ?? 'local';
        if ($driver === 'oss') {
            $objectKey = $record['package_object_key'] ?? $record['object_key'] ?? '';
            if ($objectKey === '') {
                throw new Exception('该版本文件不存在，无法下载');
            }
            if (!$this->isOssConfigured()) {
                throw new Exception('OSS 配置不存在，无法生成历史版本下载链接');
            }
            if ($this->privateBucket) {
                return $this->buildSignedUrl($objectKey, $expires);
            }
            return $this->buildPublicUrl($objectKey);
        }

        $path = $record['package_path'] ?? $record['file_path'] ?? '';
        if ($path === '' || !file_exists($path)) {
            throw new Exception('该版本文件不存在，无法下载');
        }
        return $path;
    }

    private function buildObjectKey(string $resourceType, string $ext): string
    {
        return 'plugins/' . trim($resourceType, '/') . '/' . date('Y/m/d') . '/' . date('His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
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
        return rtrim($endpoint, '/');
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
            return $this->publicBaseUrl . '/' . ltrim($objectKey, '/');
        }
        return 'https://' . $this->buildHost() . '/' . ltrim($objectKey, '/');
    }

    private function putObjectFromFile(string $objectKey, string $filePath, string $mimeType): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('读取上传文件失败');
        }
        $this->putObject($objectKey, $content, $mimeType);
    }

    private function putObject(string $objectKey, string $content, string $mimeType): void
    {
        if (!function_exists('curl_init')) {
            throw new Exception('服务器未启用 cURL，无法上传到 OSS');
        }
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $resource = '/' . $this->bucket . '/' . ltrim($objectKey, '/');
        $stringToSign = "PUT\n\n{$mimeType}\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        $url = rtrim($this->normalizeEndpoint(), '/') . '/' . ltrim($objectKey, '/');
        if (strpos(parse_url($url, PHP_URL_HOST), $this->bucket . '.') !== 0) {
            $scheme = parse_url($this->normalizeEndpoint(), PHP_URL_SCHEME) ?: 'https';
            $url = $scheme . '://' . $this->buildHost() . '/' . ltrim($objectKey, '/');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Date: ' . $date,
                'Content-Type: ' . $mimeType,
                'Content-Length: ' . strlen($content),
                'Authorization: OSS ' . $this->accessKeyId . ':' . $signature,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            throw new Exception('OSS 上传失败' . ($error ? '：' . $error : '，HTTP ' . $httpCode));
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
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    private function formatSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? round($bytes / 1024 / 1024, 1) . 'MB' : round($bytes / 1024, 1) . 'KB';
    }
}
