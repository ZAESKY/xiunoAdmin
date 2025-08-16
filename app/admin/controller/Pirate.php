<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PirateService;
use think\facade\View;

class Pirate extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new PirateService();
    }

    public function list($appid = ''){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message('获取列表成功！' ,true, ['data' => $result]);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
        try{
            View::assign('appid', $appid);
            View::assign('app_list', parent::getAppList());
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }
}