<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\AuthPriceService;
use think\facade\View;

class AuthPrice extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new AuthPriceService();
    }

    public function list($tid = 0){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message('获取列表成功！' ,true ,['data' => $result]);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
        try{
            View::assign('tid', $tid);
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }

    public function setSort(){
        try{
            if(IS_POST){
                $this->service->setSort();
                return message('排序成功！' ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }
}