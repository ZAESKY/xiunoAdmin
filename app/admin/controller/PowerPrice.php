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
                return message('获取列表成功！',true, $result);
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
                return message('更改权限成功！' ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function setDefaultPower(){
        try{
            if(IS_POST){
                $this->service->setDefaultPower();
                return message('设置默认权限成功！' ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function test($pid){
        return json($this->service->test($pid));
    }

}