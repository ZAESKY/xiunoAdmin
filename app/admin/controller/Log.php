<?php

namespace app\admin\controller;

use app\admin\service\LogService;
use app\common\controller\Backend;

class Log extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new LogService();
    }

    public function list(){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message(t('common.list_success') ,true, ['data' => $result]);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
        try{
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }
}