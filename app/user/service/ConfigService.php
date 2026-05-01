<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\user\model\User;

class ConfigService extends UserBaseService
{

    public function __construct(){
        $this->model = new User();
    }

    public function editConfig(){
        $result = $this->model->editConfig();
        if($result){
            return message(t('system.save_success'), true);
        }else{
            return message(t('system.save_failed').'[errorCode:EditUserConfigError]', false);
        }
    }
}
