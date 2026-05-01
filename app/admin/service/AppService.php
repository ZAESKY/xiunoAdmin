<?php

namespace app\admin\service;

use app\admin\model\AppModel;
use app\admin\model\CheckTypeModel;
use app\common\service\BaseService;
use think\Exception;

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
}