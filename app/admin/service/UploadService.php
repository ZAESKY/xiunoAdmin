<?php

namespace app\admin\service;

use app\admin\model\VersionModel;
use app\common\service\BaseService;
use app\common\service\ReleasePackageService;
use app\common\service\PluginStorageService;
use app\common\service\SafeZipService;
use app\common\service\VersionReleaseService;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use think\facade\Db;

class UploadService extends BaseService
{

    public function __construct(){
        $this->versionModel = new VersionModel();
    }

    public function app(){
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $fileName = !empty($post['fileName'])?$post['fileName']:null;
        $fileExt = !empty($post['fileExt'])?$post['fileExt']:null;
        $totalPage = !empty($post['totalPage'])?intval($post['totalPage']):0;
        $page = !empty($post['page'])?intval($post['page']):0;
        if (!$this->isValidChunk($totalPage, $page, 2097) || empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            return message('upload.invalid_chunk', false, ['status' => 0, 'downUrl' => '']);
        }
        if(empty($id)){
            return message(t('validation.missing_id'),false, ['status' => 0, 'downUrl' => '']);
        }
        if(empty($fileName)){
            return message(t('validation.missing_filename'),false, ['status' => 0, 'downUrl' => '']);
        }
        if(empty($fileExt)){
            return message(t('validation.missing_fileext'),false, ['status' => 0, 'downUrl' => '']);
        }
        if($fileExt != 'zip'){
            return message(t('upload.please_upload_zip'),false, ['status' => 0, 'downUrl' => '']);
        }
        $chunkSize = (int)($_FILES['file']['size'] ?? 0);
        if ($chunkSize < 1 || $chunkSize > 500 * 1024) {
            return message('version_upload.file_or_chunk_invalid', false, ['status' => 0, 'downUrl' => '']);
        }
        try{
            $versionInfo = $this->versionModel->getInfo($id);
            if (!$versionInfo) {
                return message('version_upload.record_required', false, ['status' => 0, 'downUrl' => '']);
            }
            $download_catalogue = $versionInfo['download_catalogue'];
            if(empty($download_catalogue)){
                return message(t('version.get_download_dir_empty'), false, ['status' => 0, 'downUrl' => '']);
            }
            $filePath = ReleasePackageService::existingDir($versionInfo['type'], $download_catalogue);
            if($filePath === ''){
                return message(t('version.download_dir_not_exist'), false, ['status' => 0, 'downUrl' => '']);
            }

        } catch (\Exception $e) {
            return message(t('app.get_info_failed').$e->getMessage() ,false, ['status' => 0, 'downUrl' => '']);
        }
        //处理分片上传文件
        $status = 1;
        $publishResult = null;
        //上传文件要保存的路径
        $fname = $filePath . ReleasePackageService::PACKAGE_NAME;
        $data = file_get_contents($_FILES['file']['tmp_name']);
        if ($data === false || strlen($data) < 1 || strlen($data) > 500 * 1024) {
            return message('upload.read_failed', false, ['status' => 0, 'downUrl' => '']);
        }
        if (!$this->writeChunk($fname, $data, $page)) {
            return message('version_upload.chunk_order_invalid', false, ['status' => 0, 'downUrl' => '']);
        }
        clearstatcache(true, $fname);
        if ((int)filesize($fname) > 1024 * 1024 * 1024) {
            @unlink($fname);
            return message('version_upload.file_too_large', false, ['status' => 0, 'downUrl' => '']);
        }

        //最后一片文件
        if ($totalPage == $page) {
            $zipProblem = SafeZipService::validate($fname, [], 20000, 2147483648);
            if ($zipProblem !== null) {
                @unlink($fname);
                return message($zipProblem, false, ['status' => 0, 'downUrl' => '']);
            }
            $versionArray = is_object($versionInfo) && method_exists($versionInfo, 'toArray')
                ? $versionInfo->toArray() : (array)$versionInfo;
            $storage = new PluginStorageService();
            $resourceType = 'application_release';
            try {
                $stored = $storage->storePath(
                    $fname,
                    $resourceType,
                    'app_' . (int)$versionArray['appid'] . '_build_' . (int)$versionArray['version'] . '.zip',
                    'application/zip'
                );
            } catch (\Throwable $e) {
                return message(t('version_upload.save_failed', ['error' => $e->getMessage()]), false, ['status' => 0, 'downUrl' => '']);
            }
            $publishResult = VersionReleaseService::publishInstaller($versionArray, $fname);
            if (empty($publishResult['ok'])) {
                if (($stored['storage_driver'] ?? 'local') === 'oss') {
                    $storage->deleteObject((string)$stored['object_key']);
                }
                @unlink($fname);
                return message(t('version_upload.publish_failed', ['error' => $publishResult['msg']]), false, [
                    'status' => 0,
                    'downUrl' => '',
                ]);
            }
            try {
                $updated = Db::name('version')->where('id', $id)->update([
                    'storage_driver' => (string)$stored['storage_driver'],
                    'package_object_key' => (string)$stored['object_key'],
                    'package_sha256' => (string)$stored['file_hash'],
                    'package_size' => (int)$stored['file_size'],
                ]);
                if ((int)$updated !== 1) {
                    throw new \RuntimeException(t('version_upload.record_changed'));
                }
            } catch (\Throwable $e) {
                if (($stored['storage_driver'] ?? 'local') === 'oss') {
                    $storage->deleteObject((string)$stored['object_key']);
                }
                return message('version_upload.metadata_save_failed', false, ['status' => 0, 'downUrl' => '']);
            }
            $oldDriver = (string)($versionArray['storage_driver'] ?? 'local');
            $oldObjectKey = (string)($versionArray['package_object_key'] ?? '');
            if ($oldDriver === 'oss' && $oldObjectKey !== '' && $oldObjectKey !== $stored['object_key']) {
                $storage->deleteObject($oldObjectKey);
            }
            if (($stored['storage_driver'] ?? 'local') === 'oss' && is_file($fname) && !is_link($fname)) {
                @unlink($fname);
            }
            $status = 2;
        }
        //返回上传状态
        $res = [
            'status' => $status,
            'downUrl' => '',
            'published' => is_array($publishResult) ? !empty($publishResult['published']) : false,
            'publish_msg' => is_array($publishResult) ? (string)$publishResult['msg'] : '',
        ];
        return message("success",true, $res);
    }

    public function template($fileName, $fileExt, $file, $totalPage, $page){
        try{
            [$fileName, $fileExt] = $this->normalizeUploadTarget($fileName, $fileExt);
            if (!$this->isValidChunk($totalPage, $page, 410) || empty($file)) {
                return message('upload.invalid_chunk', false, ['status' => 0, 'downUrl' => '']);
            }
            $filePath = RUNTIME_PATH . DS . 'template' . DS . 'upload' . DS;
            if (!is_dir($filePath)) {
                @mkdir($filePath, 0755, true);
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage() === 'upload.invalid_filename' ? 'upload.invalid_filename' : t('upload.upload_failed').$e->getMessage();
            return message($msg ,false, ['status' => 0, 'downUrl' => '']);
        }
        //处理分片上传文件
        $status = 1;
        //上传文件要保存的路径
        $fname = sprintf($filePath . $fileName . '.' . $fileExt);
        $data = file_get_contents($file);
        if ($data === false || strlen($data) < 1 || strlen($data) > 500 * 1024) {
            return message('upload.read_failed', false, ['status' => 0, 'downUrl' => '']);
        }
        if (!$this->writeChunk($fname, $data, $page)) {
            return message('version_upload.chunk_order_invalid', false, ['status' => 0, 'downUrl' => '']);
        }

        //最后一片文件
        if ($totalPage == $page) {
            $status = 2;
            $zipProblem = SafeZipService::validate($fname, [
                'html', 'htm', 'tpl', 'css', 'less', 'scss', 'js', 'json', 'map',
                'txt', 'md', 'xml', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
                'ico', 'ttf', 'woff', 'woff2', 'eot', 'otf',
            ], 10000, 536870912);
            if ($zipProblem !== null) {
                @unlink($fname);
                return message($zipProblem, false, ['status' => 0]);
            }
            $zip = new ZipFile();
            try {

                // 打开插件压缩包
                try {
                    $zip->openFile($fname);
                } catch (ZipException $e) {
                    $zip->close();
                    rmdirs($filePath);
                    return message(t('upload.cannot_open_zip') ,false, ['status' => 0]);
                }

                $tempDir = $filePath . 'temp';
                //创建临时目录
                @mkdir($tempDir, 0755, true);

                // 解压到临时目录
                try {
                    $zip->extractTo($tempDir);
                } catch (ZipException $e) {
                    $zip->close();
                    rmdirs($filePath);
                    return message(t('upload.unzip_failed') ,false, ['status' => 0]);
                }
                $fileArray = scan_dir($tempDir);
                foreach ($fileArray as $res){
                    $fileinfo = pathinfo($res);
                    if($fileinfo['extension'] == 'php'){
                        $zip->close();
                        rmdirs($filePath);
                        return message(t('upload.php_in_template') ,false, ['status' => 0]);
                    }
                }
                copydirs($tempDir, ROOT_PATH);
            } catch (\Exception $e) {
                return message($e->getMessage() ,false, ['status' => 0]);
            } finally {
                $zip->close();
                rmdirs($filePath);
            }

        }
        //返回上传状态
        $res = ['status' => $status, 'downUrl' => 'http://localhost/data.dat'];
        return message("success",true, $res);
    }

    public function temp($fileName, $fileExt, $file, $totalPage, $page){
        try{
            [$fileName, $fileExt] = $this->normalizeUploadTarget($fileName, $fileExt);
            if (!$this->isValidChunk($totalPage, $page, 410) || empty($file)) {
                return message('upload.invalid_chunk', false, ['status' => 0, 'downUrl' => '']);
            }
            $filePath = RUNTIME_PATH . DS . 'temp' . DS . 'upload' . DS;
            if (!is_dir($filePath)) {
                @mkdir($filePath, 0755, true);
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage() === 'upload.invalid_filename' ? 'upload.invalid_filename' : t('upload.upload_failed').$e->getMessage();
            return message($msg ,false, ['status' => 0, 'downUrl' => '']);
        }
        //处理分片上传文件
        $status = 1;
        //上传文件要保存的路径
        $fname = sprintf($filePath . $fileName . '.' . $fileExt);
        $data = file_get_contents($file);
        if ($data === false || strlen($data) < 1 || strlen($data) > 500 * 1024) {
            return message('upload.read_failed', false, ['status' => 0, 'downUrl' => '']);
        }
        if (!$this->writeChunk($fname, $data, $page)) {
            return message('version_upload.chunk_order_invalid', false, ['status' => 0, 'downUrl' => '']);
        }

        //最后一片文件
        if ($totalPage == $page) {
            $status = 2;
        }
        //返回上传状态
        $res = ['status' => $status, 'filename' => $fileName . '.' . $fileExt];
        return message('success',true, $res);
    }

    private function isValidChunk($totalPage, $page, int $maxPages = 2100): bool
    {
        return $totalPage >= 1 && $totalPage <= $maxPages && $page >= 1 && $page <= $totalPage;
    }

    private function writeChunk(string $file, string $data, int $page): bool
    {
        if ($data === '' || strlen($data) > 500 * 1024 || is_link($file)) {
            return false;
        }
        $handle = @fopen($file, 'c+b');
        if ($handle === false) {
            return false;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }
            $stat = fstat($handle);
            $currentSize = (int)($stat['size'] ?? 0);
            $expectedOffset = ($page - 1) * 500 * 1024;
            if ($page === 1) {
                if (!ftruncate($handle, 0) || fseek($handle, 0) !== 0) {
                    return false;
                }
            } elseif ($currentSize !== $expectedOffset || fseek($handle, 0, SEEK_END) !== 0) {
                return false;
            }
            $length = strlen($data);
            $written = 0;
            while ($written < $length) {
                $result = fwrite($handle, substr($data, $written));
                if ($result === false || $result === 0) {
                    return false;
                }
                $written += $result;
            }
            return fflush($handle);
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function normalizeUploadTarget($fileName, $fileExt): array
    {
        // Keep uploaded chunk targets inside runtime upload directories.
        $fileName = pathinfo((string)$fileName, PATHINFO_FILENAME);
        $fileExt = strtolower(trim((string)$fileExt, ". \t\n\r\0\x0B"));
        if (!preg_match('/^[A-Za-z0-9_-]{1,120}$/', $fileName) || !preg_match('/^[A-Za-z0-9]{1,12}$/', $fileExt)) {
            throw new \InvalidArgumentException('upload.invalid_filename');
        }
        return [$fileName, $fileExt];
    }
}
