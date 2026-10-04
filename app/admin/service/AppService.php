<?php

namespace app\admin\service;

use app\admin\model\AppModel;
use app\admin\model\CheckTypeModel;
use app\common\service\BaseService;
use app\common\service\ApplicationInstallerService;
use think\Exception;
use think\facade\Log;

class AppService extends BaseService
{
    public function __construct(){
        $this->model = new AppModel();
        $this->checkTypeModel = new CheckTypeModel();
    }

    public function getCheckTypeList(){
        try{
            $result = $this->checkTypeModel->getCheckTypeList();
            return $result;
        }catch (\Exception $e){
            return [];
        }
    }

    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                $checkTypeInfo = $this->checkTypeModel->getCheckTypeName($res['check_type']);
                $res['checkTypeName'] = t('auth.get_check_type_failed');
                if($checkTypeInfo){
                    $res['checkTypeName'] = $checkTypeInfo['name'];
                }
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function uploadInstaller(int $appId, $file)
    {
        if ($appId <= 0) {
            return message('app_package.save_app_first', false);
        }
        if (!$file) {
            return message('app_package.select_zip', false);
        }
        try {
            $meta = ApplicationInstallerService::storeUploadedFile($appId, $file);
            return message('app_package.upload_success', true, $meta);
        } catch (\RuntimeException $e) {
            return message($e->getMessage(), false);
        } catch (\Throwable $e) {
            Log::error('Application installer upload failed', ['exception' => $e, 'app_id' => $appId]);
            return message('app_package.upload_failed', false);
        }
    }
}
