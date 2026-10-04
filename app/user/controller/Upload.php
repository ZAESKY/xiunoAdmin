<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\service\PluginStorageService;

class Upload extends UserBackend
{
    public function image()
    {
        if (!IS_POST) {
            return json(['code' => -1, 'msg' => t('common.illegal_request'), 'data' => []], 405);
        }
        $file = request()->file('file');
        try {
            validate(['imgFile' => [
                'fileSize' => 10 * 1024 * 1024,
                'fileExt' => 'jpg,jpeg,png,bmp,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif',
            ]])->check(['imgFile' => $file]);
            $stored = (new PluginStorageService())->storeUploadedFile(
                $file,
                'image',
                ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp'],
                10 * 1024 * 1024
            );
            return json(['code' => 0, 'msg' => 'success', 'data' => ['path' => $stored['url']]]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => t('upload.failed_with_error', ['error' => $e->getMessage()]), 'data' => []]);
        }
    }
}
