<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\SafeService;
use think\facade\View;

class Safe extends Backend
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