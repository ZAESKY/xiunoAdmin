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
            return message('保存配置成功！', true);
        }else{
            return message('保存配置失败！[errorCode:EditUserConfigError]', false);
        }
    }
}