<?php

namespace app\install\controller;

use app\common\controller\Install;
use app\install\service\AjaxService;
class Ajax extends Install
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new AjaxService();
    }

    public function check(){
        if(IS_POST){
            return $this->service->check();
        }
    }

    public function install(){
        if(IS_POST){
            return $this->service->install();
        }
    }
    
    public function importSQL(){
        if(IS_POST){
            return $this->service->importSQL();
        }
    }

    public function bindingCheck(){
        if(IS_POST){
            return $this->service->bindingCheck();
        }
    }

    public function adminInfo(){
        if(IS_POST){
            return $this->service->adminInfo();
        }
    }
    
    public function putInstallLock(){
        if(IS_POST){
            return $this->service->putInstallLock();
        }
    }

    public function login(){
        if(IS_POST){
            return $this->service->login();
        }
    }
}