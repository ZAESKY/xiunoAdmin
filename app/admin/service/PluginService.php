<?php

namespace app\admin\service;

use app\admin\model\PluginModel;
use app\common\service\BaseService;
use think\Exception;
use think\facade\Filesystem;

class PluginService extends BaseService
{
    public function __construct()
    {
        $this->model = new PluginModel();
    }

    /**
     * 上传插件文件
     */
    public function uploadFile()
    {
        try {
            $post = request()->post();
            $fileName = !empty($post['fileName']) ? $post['fileName'] : null;
            $fileExt = !empty($post['fileExt']) ? $post['fileExt'] : null;
            $totalPage = !empty($post['totalPage']) ? intval($post['totalPage']) : 0;
            $page = !empty($post['page']) ? intval($post['page']) : 0;
            $file = request()->file('file');

            if (empty($fileName)) {
                return message('文件名不能为空', false, ['status' => 0]);
            }
            if (empty($fileExt)) {
                return message('文件扩展名不能为空', false, ['status' => 0]);
            }

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

            // 创建私有存储目录
            $uploadPath = app()->getRootPath() . 'storage' . DIRECTORY_SEPARATOR . 'plugins';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // 分片上传处理
            $tempPath = $uploadPath . DIRECTORY_SEPARATOR . 'temp';
            if (!is_dir($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $chunkFile = $tempPath . DIRECTORY_SEPARATOR . $fileName . '.part' . $page;
            $file->move($tempPath, $fileName . '.part' . $page);

            // 如果是最后一片，合并文件
            if ($page == $totalPage - 1) {
                $finalFile = $uploadPath . DIRECTORY_SEPARATOR . date('Ymd') . '_' . uniqid() . '.' . $fileExt;
                $fp = fopen($finalFile, 'wb');

                for ($i = 0; $i < $totalPage; $i++) {
                    $partFile = $tempPath . DIRECTORY_SEPARATOR . $fileName . '.part' . $i;
                    if (!file_exists($partFile)) {
                        fclose($fp);
                        @unlink($finalFile);
                        return message('分片文件缺失', false, ['status' => 0]);
                    }
                    $content = file_get_contents($partFile);
                    fwrite($fp, $content);
                    @unlink($partFile);
                }

                fclose($fp);

                // 计算文件哈希和大小
                $fileHash = md5_file($finalFile);
                $fileSize = filesize($finalFile);

                // 尝试解析压缩包内的 conf.json 和 icon.png
                $autoData = [];
                try {
                    $zip = new \ZipArchive();
                    if ($zip->open($finalFile) === true) {
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
                            $iconDir = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . date('Ymd');
                            if (!is_dir($iconDir)) mkdir($iconDir, 0755, true);
                            $iconName = 'plugin_icon_' . uniqid() . '.png';
                            file_put_contents($iconDir . DIRECTORY_SEPARATOR . $iconName, $iconContent);
                            $autoData['icon'] = '/upload/' . date('Ymd') . '/' . $iconName;
                        }
                        $zip->close();
                    }
                } catch (\Throwable $e) {}

                return message('上传成功', true, [
                    'status' => 1,
                    'file_path' => $finalFile,
                    'file_hash' => $fileHash,
                    'file_size' => $fileSize,
                    'original_name' => $fileName,
                    'auto' => $autoData,
                ]);
            }

            return message('分片上传成功', true, ['status' => 2]);
        } catch (\Exception $e) {
            return message('上传失败: ' . $e->getMessage(), false, ['status' => 0]);
        }
    }

    /**
     * 保存插件信息（包含文件信息）
     */
    public function saveWithFile()
    {
        try {
            $post = request()->post();
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
