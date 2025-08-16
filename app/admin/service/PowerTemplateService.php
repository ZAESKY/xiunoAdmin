<?php

namespace app\admin\service;

use app\admin\model\PowerTemplateModel;
use app\common\service\BaseService;
use think\Exception;

class PowerTemplateService extends BaseService
{
    public function __construct(){
        $this->model = new PowerTemplateModel();
    }

    public function setSort(){
        try{
            $result = $this->model->setSort();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getList()
    {
        try{
            $list = $this->model->getList();
            return $list;
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