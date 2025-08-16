<?php

namespace app\api\service;

use app\api\model\AuthModel;
use app\api\model\BlackModel;
use app\common\service\BaseService;

class QueryService extends BaseService
{
    public function __construct()
    {
        $this->model = new AuthModel();
        $this->blackModel = new BlackModel();
    }
}