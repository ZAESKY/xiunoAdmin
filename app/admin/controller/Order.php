<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\OrderService;

class Order extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new OrderService();
    }
}