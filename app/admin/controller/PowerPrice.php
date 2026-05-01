<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PowerPriceService;
use think\facade\View;

class PowerPrice extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new PowerPriceService();
    }

    public function list($tid = ''){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message(t('common.list_success'), true, $result);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
        try{
            View::assign('tid', $tid);
            //View::assign('template_list', parent::getPowerTemplateList());
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }

    public function setPower(){
        try{
            if(IS_POST){
                $this->service->setPower();
                return message(t('power.change_success') ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function setDefaultPower(){
        try{
            if(IS_POST){
                $this->service->setDefaultPower();
                return message(t('power.set_default_success') ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function test($pid){
        return json($this->service->test($pid));
    }

}