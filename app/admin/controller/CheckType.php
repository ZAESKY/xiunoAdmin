<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\CheckTypeService;

class CheckType extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new CheckTypeService();
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