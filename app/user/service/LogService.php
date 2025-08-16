<?php

namespace app\user\service;

use app\user\model\LogModel;
use app\common\service\UserBaseService;

class LogService extends UserBaseService
{
    public function __construct()
    {
        $this->model = new LogModel();
    }
}