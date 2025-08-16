<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\SafeService;

class Safe extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new SafeService();
    }

    public function index()
    {
        return $this->render();
    }

    public function check(){
        if(IS_POST){
            $result = $this->service->check();
            return json($result);
        }
    }
}