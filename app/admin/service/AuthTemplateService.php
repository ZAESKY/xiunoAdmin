<?php
namespace app\admin\service;

use app\admin\model\AuthTemplateModel;
use app\admin\model\CheckTypeModel;
use app\common\service\BaseService;
use think\Exception;

class AuthTemplateService extends BaseService
{
    public function __construct(){
        $this->model = new AuthTemplateModel();
        $this->checkTypeModel = new CheckTypeModel();
    }

    public function setSort(){
        try{
            $result = $this->model->setSort();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getList(){
        try{
            $result = $this->model->getList();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $result = $this->model->list();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}