<?php
declare(strict_types=1);

namespace app\common\service;

use RuntimeException;

/**
 * Minimal local upload storage used after ThinkPHP 6.1 split Filesystem out.
 */
class LocalFilesystemService
{
    private string $disk;

    public function __construct(string $disk = '')
    {
        $this->disk = $disk !== '' ? $disk : (string)config('filesystem.default', 'local');
    }

    public function root(): string
    {
        $root = (string)config('filesystem.disks.' . $this->disk . '.root', '');
        if ($root === '') {
            throw new RuntimeException('上传存储目录未配置');
        }
        return rtrim($root, '/\\');
    }

    public function putFile(string $directory, $file): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || !preg_match('#^[A-Za-z0-9_/-]+$#D', $directory) || str_contains($directory, '..')) {
            throw new RuntimeException('上传目录不合法');
        }
        if (!$file || !method_exists($file, 'move')) {
            throw new RuntimeException('上传文件无效');
        }

        $extension = strtolower((string)$file->getOriginalExtension());
        if ($extension === '' || !preg_match('/^[a-z0-9]{1,12}$/D', $extension)) {
            throw new RuntimeException('上传文件扩展名无效');
        }

        $relativeDirectory = $directory . '/' . date('Ymd');
        $targetDirectory = $this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('无法创建上传目录');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $moved = $file->move($targetDirectory, $fileName);
        if (!$moved) {
            throw new RuntimeException('保存上传文件失败');
        }
        return $relativeDirectory . '/' . $fileName;
    }

    /**
     * Store a verified raster image. Extension, detected MIME and image
     * structure must agree so renamed HTML/PHP payloads are rejected.
     */
    public function putImage(string $directory, $file, int $maxBytes = 10485760): string
    {
        if (!$file || !method_exists($file, 'getPathname') || !method_exists($file, 'getSize')) {
            throw new RuntimeException('上传图片无效');
        }
        $size = (int)$file->getSize();
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('图片大小无效');
        }

        $path = (string)$file->getPathname();
        $imageInfo = @getimagesize($path);
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new RuntimeException('上传内容不是有效图片');
        }

        $extension = strtolower((string)$file->getOriginalExtension());
        $mime = strtolower((string)$imageInfo['mime']);
        $allowed = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp'],
            'webp' => ['image/webp'],
        ];
        if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
            throw new RuntimeException('图片扩展名与实际格式不一致');
        }

        return $this->putFile($directory, $file);
    }
}
