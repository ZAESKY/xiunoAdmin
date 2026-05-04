<?php

namespace app\api\controller;

use app\api\service\QueryService;
use app\common\controller\Frontend;

class Query extends Frontend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new QueryService();
    }

    public function black(){
        return $this->service->black();
    }

    public function index(){
        return json(['msg' => 'Query API'])->send();
    }
}