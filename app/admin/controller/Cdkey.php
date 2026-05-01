<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\CdkeyService;
use think\facade\View;

class Cdkey extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new CdkeyService();
    }

    public function list($appid = ''){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message(t('common.list_success') ,true, ['data' => $result]);
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