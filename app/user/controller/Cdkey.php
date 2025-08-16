<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\CdkeyService;
use think\facade\View;

class Cdkey extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new CdkeyService();
    }

    public function exchange(){
        try{
            if(IS_POST){
                $step = $this->request->post('step')??0;
                $cdkey_type = $this->request->post('cdkey_type')??null;
                return $this->service->exchange($step, $cdkey_type);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
        try{
            View::assign('cdkey_notice', $this->myAppInfo['cdkey_notice']);
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }
}