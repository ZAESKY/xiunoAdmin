<?php

namespace app\user\controller;

use app\common\controller\Backend;
use app\user\service\CdkeyService;
use think\facade\View;

class Cdkey extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new CdkeyService();
    }
    public function list($appid = ''){
        if(IS_POST){
            return $this->service->list();
        }
        View::assign('appid', $appid);
        View::assign('app_list', parent::getAppList());
        return $this->render();
    }

}