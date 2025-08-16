<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\user\model\OrderModel;

class OrderService extends UserBaseService
{

    public function __construct()
    {
        $this->model = new OrderModel();
    }
}