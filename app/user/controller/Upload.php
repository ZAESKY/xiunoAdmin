<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use think\facade\Filesystem;

class Upload extends UserBackend
{
    public function image()
    {
        $file = request()->file('file');
        try {
            validate(['imgFile' => [
                'fileSize' => 410241024,
                'fileExt' => 'jpg,jpeg,png,bmp,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif',
            ]])->check(['imgFile' => $file]);
            $saveName = Filesystem::disk('public')->putFile('', $file);
            return json(['code' => 0, 'msg' => 'success', 'data' => ['path' => '/upload/' . $saveName]]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '上传失败：' . $e->getMessage(), 'data' => []]);
        }
    }
}
