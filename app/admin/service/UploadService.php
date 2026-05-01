<?php

namespace app\admin\service;

use app\admin\model\VersionModel;
use app\common\service\BaseService;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;

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
        if (!$this->isValidChunk($totalPage, $page) || empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            return message('upload.invalid_chunk', false, ['status' => 0, 'downUrl' => '']);
        }
        if(empty($id)){
            return message("缺少ID参数",false, ['status' => 0, 'downUrl' => '']);
        }
        if(empty($fileName)){
            return message("缺少FILENAME参数",false, ['status' => 0, 'downUrl' => '']);
        }
        if(empty($fileExt)){
            return message("缺少FILEEXT参数",false, ['status' => 0, 'downUrl' => '']);
        }
        if($fileExt != 'zip'){
            return message("请上传zip格式的压缩包",false, ['status' => 0, 'downUrl' => '']);
        }
        try{
            $versionInfo = $this->versionModel->getInfo($id);
            $download_catalogue = $versionInfo['download_catalogue'];
            if(empty($download_catalogue)){
                return message("该版本的下载目录为空，请手动去数据库修改“download_catalogue”字段，并在app/common/download/".(($versionInfo['type'] == 0)?"release":"update")."目录下创建目录名为”你所修改的download_catalogue字段”的目录", false, ['status' => 0, 'downUrl' => '']);
            }
            $filePath = APP_PATH.'/common/download/'.(($versionInfo['type'] == 0)?'release':'update').'/'.$versionInfo['download_catalogue'];
            if(!is_dir($filePath)){
                return message("该应用的下载目录不存在，请手动在app/common/download/".(($versionInfo['type'] == 0)?"release":"update")."目录下创建目录”".$versionInfo['download_catalogue']."”", false, ['status' => 0, 'downUrl' => '']);
            }

        } catch (\Exception $e) {
            return message("获取信息失败！".$e->getMessage() ,false, ['status' => 0, 'downUrl' => '']);
        }
        //处理分片上传文件
        $status = 1;
        //上传文件要保存的路径
        $fname = sprintf($filePath . '/SF.zip');
        $data = file_get_contents($_FILES['file']['tmp_name']);
        if ($data === false) {
            return message('upload.read_failed', false, ['status' => 0, 'downUrl' => '']);
        }

        if ($page == 1) {
            $written = file_put_contents($fname, $data);
        } else {
            //其余文件追加到文件末尾
            $written = file_put_contents($fname, $data, FILE_APPEND);
        }
        if ($written === false) {
            return message('upload.write_failed', false, ['status' => 0, 'downUrl' => '']);
        }

        //最后一片文件
        if ($totalPage == $page) {
            $status = 2;
        }
        //返回上传状态
        $res = ['status' => $status, 'downUrl' => 'http://localhost/data.dat'];
        return message("success",true, $res);
    }

    public function template($fileName, $fileExt, $file, $totalPage, $page){
        try{
            [$fileName, $fileExt] = $this->normalizeUploadTarget($fileName, $fileExt);
            if (!$this->isValidChunk($totalPage, $page) || empty($file)) {
                return message('upload.invalid_chunk', false, ['status' => 0, 'downUrl' => '']);
            }
            $filePath = RUNTIME_PATH . DS . 'template' . DS . 'upload' . DS;
            if (!is_dir($filePath)) {
                @mkdir($filePath, 0755, true);
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage() === 'upload.invalid_filename' ? 'upload.invalid_filename' : '上传失败！'.$e->getMessage();
            return message($msg ,false, ['status' => 0, 'downUrl' => '']);
        }
        //处理分片上传文件
        $status = 1;
        //上传文件要保存的路径
        $fname = sprintf($filePath . $fileName . '.' . $fileExt);
        $data = file_get_contents($file);
        if ($data === false) {
            return message('upload.read_failed', false, ['status' => 0, 'downUrl' => '']);
        }

        if ($page == 1) {
            $written = file_put_contents($fname, $data);
        } else {
            //其余文件追加到文件末尾
            $written = file_put_contents($fname, $data, FILE_APPEND);
        }
        if ($written === false) {
            return message('upload.write_failed', false, ['status' => 0, 'downUrl' => '']);
        }

        //最后一片文件
        if ($totalPage == $page) {
            $status = 2;
            $zip = new ZipFile();
            try {

                // 打开插件压缩包
                try {
                    $zip->openFile($fname);
                } catch (ZipException $e) {
                    $zip->close();
                    rmdirs($filePath);
                    return message('无法打开压缩文件！' ,false, ['status' => 0]);
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
                    return message('解压文件失败！' ,false, ['status' => 0]);
                }
                $fileArray = scan_dir($tempDir);
                foreach ($fileArray as $res){
                    $fileinfo = pathinfo($res);
                    if($fileinfo['extension'] == 'php'){
                        $zip->close();
                        rmdirs($filePath);
                        return message('该模板中存在PHP文件，请删除后重新上传！' ,false, ['status' => 0]);
                    }
                }
                copydirs($tempDir, ROOT_PATH);
            } catch (Exception $e) {
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
            if (!$this->isValidChunk($totalPage, $page) || empty($file)) {
                return message('upload.invalid_chunk', false, ['status' => 0, 'downUrl' => '']);
            }
            $filePath = RUNTIME_PATH . DS . 'temp' . DS . 'upload' . DS;
            if (!is_dir($filePath)) {
                @mkdir($filePath, 0755, true);
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage() === 'upload.invalid_filename' ? 'upload.invalid_filename' : '上传失败！'.$e->getMessage();
            return message($msg ,false, ['status' => 0, 'downUrl' => '']);
        }
        //处理分片上传文件
        $status = 1;
        //上传文件要保存的路径
        $fname = sprintf($filePath . $fileName . '.' . $fileExt);
        $data = file_get_contents($file);
        if ($data === false) {
            return message('upload.read_failed', false, ['status' => 0, 'downUrl' => '']);
        }

        if ($page == 1) {
            $written = file_put_contents($fname, $data);
        } else {
            //其余文件追加到文件末尾
            $written = file_put_contents($fname, $data, FILE_APPEND);
        }
        if ($written === false) {
            return message('upload.write_failed', false, ['status' => 0, 'downUrl' => '']);
        }

        //最后一片文件
        if ($totalPage == $page) {
            $status = 2;
        }
        //返回上传状态
        $res = ['status' => $status, 'filename' => $fileName . '.' . $fileExt];
        return message('success',true, $res);
    }

    private function isValidChunk($totalPage, $page): bool
    {
        return $totalPage >= 1 && $page >= 1 && $page <= $totalPage;
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
