<?php

namespace app\admin\service;

use app\admin\model\OrderModel;
use app\common\service\BaseService;

class OrderService extends BaseService
{
    public function __construct(){
        $this->model = new OrderModel();
    }
}