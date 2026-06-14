<?php

namespace app\admin\service;

use app\admin\model\PluginModel;
use app\common\service\BaseService;
use app\common\service\PluginStorageService;
use think\Exception;

class PluginService extends BaseService
{
    public function __construct()
    {
        $this->model = new PluginModel();
    }

    /**
     * 上传插件文件
     * @param mixed $file 上传的文件(若为空则从 request 中取)
     */
    public function uploadFile($file = null)
    {
        try {
            if (!$file) $file = request()->file('file');
            if (!$file) return message('请先选择插件文件', false, ['status' => 0]);

            // 验证文件
            try {
                validate([
                    'File' => [
                        'fileSize' => 410241024, // ~410MB
                        'fileExt' => 'zip',
                        'fileMime' => 'application/zip,application/x-zip-compressed,application/octet-stream',
                    ]
                ])->check(['File' => $file]);
            } catch (\Exception $e) {
                return message('文件验证失败: ' . $e->getMessage(), false, ['status' => 0]);
            }

            $originalName = $file->getOriginalName();
            if (preg_match('/[\x{4e00}-\x{9fff}]/u', $originalName)) {
                return message('压缩包名称不能包含中文，请重命名后再上传', false, ['status' => 0]);
            }

            // 尝试解析压缩包内的 conf.json 和 icon.png
            $autoData = [];
            try {
                $zip = new \ZipArchive();
                if ($zip->open($file->getPathname()) === true) {
                    $confContent = false;
                    $iconContent = false;
                    $entries = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        if ($name !== false) $entries[] = rtrim($name, '/');
                    }
                    foreach ($entries as $f) {
                        if (strcasecmp(basename($f), 'conf.json') === 0) { $confContent = $zip->getFromName($f); break; }
                    }
                    foreach ($entries as $f) {
                        if (strcasecmp(basename($f), 'icon.png') === 0) { $iconContent = $zip->getFromName($f); break; }
                    }
                    if ($confContent !== false) {
                        $conf = json_decode($confContent, true);
                        if (is_array($conf)) {
                            if (!empty($conf['name']))    $autoData['name'] = trim($conf['name']);
                            if (!empty($conf['brief']))   $autoData['description'] = trim($conf['brief']);
                            if (!empty($conf['version'])) $autoData['version'] = trim($conf['version']);
                        }
                    }
                    if ($iconContent !== false) {
                        $iconMeta = (new PluginStorageService())->storeBytes($iconContent, 'icon', 'icon.png', 'image/png');
                        $autoData['icon'] = $iconMeta['url'];
                        $autoData['icon_object_key'] = $iconMeta['object_key'];
                        $autoData['icon_storage_driver'] = $iconMeta['storage_driver'];
                        $autoData['icon_file_name'] = $iconMeta['file_name'];
                        $autoData['icon_file_size'] = $iconMeta['file_size'];
                        $autoData['icon_mime_type'] = $iconMeta['mime_type'];
                    }
                    $zip->close();
                }
            } catch (\Throwable $e) {}

            $stored = (new PluginStorageService())->storeUploadedFile($file, 'package', ['zip'], 410241024);

            return message('上传成功', true, [
                'status' => 1,
                'file_path' => $stored['path'],
                'file_hash' => $stored['file_hash'],
                'file_size' => $stored['file_size'],
                'original_name' => $originalName,
                'storage_driver' => $stored['storage_driver'],
                'package_object_key' => $stored['object_key'],
                'package_file_name' => $stored['file_name'],
                'package_mime_type' => $stored['mime_type'],
                'auto' => $autoData,
            ]);
        } catch (\Exception $e) {
            return message('上传失败: ' . $e->getMessage(), false, ['status' => 0]);
        }
    }

    /**
     * 上传插件图标和封面
     */
    public function uploadResource($file = null)
    {
        $type = input('get.type', 'icon', 'trim');
        $type = in_array($type, ['icon', 'cover'], true) ? $type : 'icon';
        try {
            if (!$file) $file = request()->file('file');
            if (!$file) return message('请先选择资源文件', false, ['status' => 0]);
            $stored = (new PluginStorageService())->storeUploadedFile($file, $type, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            return message('上传成功', true, [
                'status' => 1,
                'path' => $stored['url'],
                'url' => $stored['url'],
                'src' => $stored['url'],
                'storage_driver' => $stored['storage_driver'],
                'object_key' => $stored['object_key'],
                'file_name' => $stored['file_name'],
                'file_size' => $stored['file_size'],
                'mime_type' => $stored['mime_type'],
            ]);
        } catch (\Throwable $e) {
            return message('上传失败: ' . $e->getMessage(), false, ['status' => 0]);
        }
    }

    /**
     * 保存插件信息（包含文件信息）
     */
    public function saveWithFile($file = null)
    {
        try {
            $post = request()->post();
            // 文件可选,只在真正上传时校验
            if ($file) {
                $post['file_path'] = $file->getPathname();
            }
            $file_path = !empty($post['file_path']) ? $post['file_path'] : '';
            $file_hash = !empty($post['file_hash']) ? $post['file_hash'] : '';
            $file_size = !empty($post['file_size']) ? intval($post['file_size']) : 0;

            // 调用model的edit方法
            $result = $this->model->edit();

            // 如果成功且有文件信息，更新文件字段
            if ($result['code'] == 0 && !empty($file_path)) {
                $id = !empty($post['id']) ? intval($post['id']) : null;
                if ($id) {
                    \think\facade\Db::name('plugin')
                        ->where('id', $id)
                        ->update([
                            'file_path' => $file_path,
                            'file_hash' => $file_hash,
                            'file_size' => $file_size,
                        ]);
                }
            }

            return $result;
        } catch (\Exception $e) {
            return message('保存失败: ' . $e->getMessage(), false);
        }
    }
}
