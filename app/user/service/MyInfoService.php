<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\user\model\User;

class MyInfoService extends UserBaseService
{
    public function __construct(){
        $this->model = new User();
    }

    public function updatePower(){
        return $this->model->updatePower();
    }

    public function editUserInfo(){
        return $this->model->editUserInfo();
    }
}