<?php
namespace app\api\controller;

use app\api\service\AuthService;
use app\common\controller\Frontend;
class Auth extends Frontend
{
    public function initialize(){
        parent::initialize();
        $this->service = new AuthService();
    }

    public function queueCheck(){
        return $this->service->queueCheck();
    }

    public function checkAuth(){
        return $this->service->checkAuth();
    }

    public function checkUpdate(){
        return $this->service->checkUpdate();
    }
}