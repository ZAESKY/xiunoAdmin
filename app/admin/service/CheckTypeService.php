<?php

namespace app\admin\service;

use app\admin\model\CheckTypeModel;
use app\common\service\BaseService;
use think\Exception;

class CheckTypeService extends BaseService
{
    public function __construct()
    {
        $this->model = new CheckTypeModel();
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