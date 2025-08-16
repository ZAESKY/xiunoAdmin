<?php

namespace app\api\service;

use app\api\model\AuthModel;
use app\api\model\PirateModel;
use app\api\model\VersionModel;
use app\common\service\BaseService;
use think\facade\Db;

class DownloadService extends BaseService
{
    public function __construct()
    {
        $this->model = new AuthModel();
        $this->pirateModel = new PirateModel();
        $this->versionModel = new VersionModel();
        $this->authService = new AuthService();
    }

    public function download(){
        $param = request()->param();
        $sign = !empty($param['sign'])?$param['sign']:null;
        if(!$sign){
            return json(message('请提交SIGN！',false));
        }
        $signData = unserialize(cache($sign));
        // cache($sign,null);
        if(empty($signData)){
            return json(message('不存在此SIGN！',false));
        }
        $versionData = $signData['versionInfo'];
        if(empty($versionData)){
            return json(message('SIGN损坏，请重新生成1！',false));
        }
        $appInfo = Db::name('app')
            ->where([
                'id' => $versionData['appid'],
                'status' => 2
            ])
            ->field('authcode_file,public_key,pirate_msg_switch')
            ->find();
        if(empty($appInfo)){
            return json(message('不存在此版本', false));
        }
        $type = $versionData['type'];
        $download_catalogue = $versionData['download_catalogue'];
        if(empty($versionData['download_catalogue'])){
            return json(message('该版本未配置下载目录', false));
        }
        $downloadCatalogue = APP_PATH . DS . 'common' . DS . 'download' . DS . (($type == 0)?'release':'update') . DS . $download_catalogue . DS;
        $filePath = $downloadCatalogue . 'SF.zip';
        if(!is_dir($downloadCatalogue)){
            return json(message('该版本下载目录不存在', false));
        }

        if(!is_file($filePath)){
            return json(message('该版本更新文件不存在', false));
        }
        if($appInfo['pirate_msg_switch'] == 1) {
            $authData = $signData['authInfo'];
            if(empty($authData)){
                return json(message('SIGN损坏，请重新生成2！',false,$signData));
            }
            if(!empty($appInfo['authcode_file'])){
                $zip = new \ZipArchive;
                try {
                    $tempDir = RUNTIME_PATH . DS . 'download' . DS;
                    $tempFile = $tempDir . 'SF_'.uniqid().'.zip';
                    //创建临时目录
                    if(!is_dir($tempDir)){
                        @mkdir($tempDir, 0755, true);
                    }
                    copy($filePath, $tempFile);
                    if(!is_file($tempFile)){
                        return json(message('复制文件失败', false));
                    }
                    $authCode_relativePath = dirname($appInfo['authcode_file']).'/';
                    $authCodeDir = $tempDir . $authCode_relativePath;
                    if(!is_dir($authCodeDir)){
                        @mkdir($authCodeDir, 0755, true);
                    }
                    $file_str = file_get_contents(APP_PATH . DS . 'common' . DS . 'download' . DS . 'AuthInfo.php');
                    $file_str = str_replace('SF_AUTHCODE', $authData['authcode'], $file_str);
                    $file_str = str_replace('SF_VERSION', $versionData['version'], $file_str);
                    $file_str = str_replace('SF_EDITION', $versionData['edition'], $file_str);
                    $file_str = str_replace('SF_PUBLIC_KEY', $appInfo['public_key'], $file_str);
                    file_put_contents($tempDir.$appInfo['authcode_file'], $file_str);
                    if (!class_exists('ZipArchive')) {
                        return message('ZinArchive 没有安装',false);
                    }

                    $zip->open($tempFile);
                    try {
                        $files = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($authCodeDir), \RecursiveIteratorIterator::LEAVES_ONLY
                        );

                        foreach ($files as $filename => $file) {
                            if (!$file->isDir()) {
                                $filePath2 = $file->getRealPath();
                                $relativePath = str_replace(DS, '/', substr($filePath2, strlen($authCodeDir)));
                                $zip->addFile($filePath2, $authCode_relativePath . $relativePath);
                            }
                        }
                        unset($files);
                    } catch (\Exception $e) {
                        return message($e->getMessage(),false);
                    }
                } catch (Exception $e) {
                    return message($e->getMessage() ,false, ['status' => 0]);
                }
                $downloadZip = $tempFile;
                $zip->close();
            }else{
                $downloadZip = $filePath;
            }
        }else{
            if(!empty($appInfo['authcode_file'])){
                $zip = new \ZipArchive;
                try {
                    $tempDir = RUNTIME_PATH . DS . 'download' . DS;
                    $tempFile = $tempDir . 'SF_'.uniqid().'.zip';
                    //创建临时目录
                    if(!is_dir($tempDir)){
                        @mkdir($tempDir, 0755, true);
                    }
                    copy($filePath, $tempFile);
                    if(!is_file($tempFile)){
                        return json(message('复制文件失败', false));
                    }
                    $authCode_relativePath = dirname($appInfo['authcode_file']).'/';
                    $authCodeDir = $tempDir . $authCode_relativePath;
                    if(!is_dir($authCodeDir)){
                        @mkdir($authCodeDir, 0755, true);
                    }

                    $file_str = file_get_contents(APP_PATH . DS . 'common' . DS . 'download' . DS . 'AuthInfo.php');
                    $file_str = str_replace('SF_AUTHCODE', '', $file_str);
                    $file_str = str_replace('SF_VERSION', $versionData['version'], $file_str);
                    $file_str = str_replace('SF_EDITION', $versionData['edition'], $file_str);
                    $file_str = str_replace('SF_PUBLIC_KEY', $appInfo['public_key'], $file_str);
                    file_put_contents($tempDir.$appInfo['authcode_file'], $file_str);
                    if (!class_exists('ZipArchive')) {
                        return message('ZinArchive 没有安装',false);
                    }

                    $zip->open($tempFile);
                    try {
                        $files = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($authCodeDir), \RecursiveIteratorIterator::LEAVES_ONLY
                        );

                        foreach ($files as $filename => $file) {
                            if (!$file->isDir()) {
                                $filePath2 = $file->getRealPath();
                                $relativePath = str_replace(DS, '/', substr($filePath2, strlen($authCodeDir)));
                                $zip->addFile($filePath2, $authCode_relativePath . $relativePath);
                            }
                        }
                        unset($files);
                    } catch (\Exception $e) {
                        return message($e->getMessage(),false);
                    }
                } catch (Exception $e) {
                    return message($e->getMessage() ,false, ['status' => 0]);
                }
                $downloadZip = $tempFile;
                $zip->close();
            }else{
                $downloadZip = $filePath;
            }
        }
        return download($downloadZip,'SF_'.uniqid().'.zip');
    }
}