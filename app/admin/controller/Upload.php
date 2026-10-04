<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\service\PluginStorageService;
use app\admin\service\UploadService;
use think\Exception;

class Upload extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new UploadService();
    }
    /**图片上传*/
    public function image()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false), 405);
        }
        $file = request()->file('file');
        if (!$file) {
            return message('upload.select_file', false);
        }
        // 移动到框架应用根目录/uploads/ 目录下
        try{
            // 验证 — 表单字段名是 'file' 而不是 'imgFile'
            validate(['file'=>[
                'fileSize' => 10 * 1024 * 1024,
                'fileExt' => 'jpg,jpeg,png,bmp,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif',
            ]])->check(['file' => $file]);
            $stored = (new PluginStorageService())->storeUploadedFile(
                $file,
                'image',
                ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp'],
                10 * 1024 * 1024
            );
            if (empty($stored['url'])) {
                return message('upload.storage_save_failed', false);
            }
            return message('success', true, ['path' => $stored['url']]);
        } catch (\Exception $e) {
            // 验证失败 输出错误信息
            return message(t('upload.upload_failed') . $e->getMessage(), false);
        }
    }
    /**应用上传**/

    public function app()
    {
        if(IS_POST){
            return $this->service->app();
        }
    }

    public function temp()
    {
        if(IS_POST){
            $post = $this->request->post();
            $fileName = !empty($post['fileName'])?$post['fileName']:null;
            $fileExt = !empty($post['fileExt'])?$post['fileExt']:null;
            $totalPage = !empty($post['totalPage'])?intval($post['totalPage']):0;
            $page = !empty($post['page'])?intval($post['page']):0;
            $file = $this->request->file('file');
            if(empty($fileName)){
                return message(t('validation.missing_filename'),false, ['status' => 0, 'downUrl' => '']);
            }
            if(empty($fileExt)){
                return message(t('validation.missing_fileext'),false, ['status' => 0, 'downUrl' => '']);
            }
            if (!$file) {
                return message('upload.chunk_file_required', false, ['status' => 0, 'downUrl' => '']);
            }
            try {
                validate([
                    'File' => [
                        'fileSize' => 200 * 1024 * 1024,
                        'fileMime' => 'zip,application/zip,application/x-gzip,application/x-rar,application/x-7z-compressed,application/octet-stream,application/x-dosexec',
                    ]
                ])->check(['File' => $file]);
            } catch (\Exception $e) {
                $mime = $file ? $file->getMime() : 'null';
                return message($e->getMessage().'|'.$mime,false, ['status' => 0, 'downUrl' => '']);
            }
            return $this->service->temp($fileName, $fileExt, $file, $totalPage, $page);
        }
    }
}
