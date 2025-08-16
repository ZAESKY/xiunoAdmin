<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PowerTemplateService;

class PowerTemplate extends Backend
{
    public function initialize(){
        parent::initialize();
        $this->service = new PowerTemplateService();
    }

    public function getList(){
        try{
            if(IS_POST){
                $result = $this->service->getList();
                return message('获取列表成功！', true, ['data' => $result]);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function list(){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message('获取列表成功！', true, ['data' => $result]);
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