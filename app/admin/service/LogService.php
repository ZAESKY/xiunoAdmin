<?php

namespace app\admin\service;

use app\admin\model\LogModel;
use app\common\service\BaseService;
use think\Exception;

class LogService extends BaseService
{
    public function __construct()
    {
        $this->model = new LogModel();
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