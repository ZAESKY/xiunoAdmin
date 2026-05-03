<?php

namespace app\admin\service;

use app\admin\model\PluginOrderModel;
use app\common\service\BaseService;

class PluginOrderService extends BaseService
{
    public function __construct()
    {
        $this->model = new PluginOrderModel();
    }
}
