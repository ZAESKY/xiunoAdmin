<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\UploadService;
use think\facade\Filesystem;
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
        $file = request()->file('file');
        // 移动到框架应用根目录/uploads/ 目录下
        try{
            // 验证
            validate(['imgFile'=>[
                'fileSize' => 410241024,
                'fileExt' => 'jpg,jpeg,png,bmp,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif', //这个一定要加上，很重要我认为！
            ]])->check(['imgFile' => $file]);
            // 上传图片到本地服务器
            $saveName = Filesystem::disk('public')->putFile('',$file);
            return message('success' ,true ,['path' => '/upload/'. $saveName]);
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
            try {
                validate([
                    'File' => [
                        'fileSize' => 410241024,
                        'fileMime' => 'zip,application/zip,application/x-gzip,application/x-rar,application/x-7z-compressed,application/octet-stream,application/x-dosexec',
                    ]
                ])->check(['File' => $file]);
            } catch (\Exception $e) {
                return message($e->getMessage().'|'.$file->getMime(),false, ['status' => 0, 'downUrl' => '']);
            }
            return $this->service->temp($fileName, $fileExt, $file, $totalPage, $page);
        }
    }
}
