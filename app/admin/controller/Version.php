<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\VersionService;
use think\facade\View;
class Version extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new VersionService();
    }

    public function setType(){
        try{
            if(IS_POST){
                $result = $this->service->setType();
                return message(t('version.change_type_success') ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function setBeta(){
        try{
            if(IS_POST){
                $this->service->setBeta();
                return message(t('version.change_qualify_success') ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function deleteFile(){
        try{
            if(IS_POST){
                $this->service->deleteFile();
                return message(t('user.delete_success') ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function checkFile(){
        try{
            if(IS_POST){
                $this->service->checkFile();
                return message(t('common.success') ,true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
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
