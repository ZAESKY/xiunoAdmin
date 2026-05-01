<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\AuthService;
use think\facade\View;

class Auth extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new AuthService();
    }

    public function setBetaSwitch(){
        try{
            if(IS_POST){
                $this->service->setBetaSwitch();
                return message(t('auth.change_qualify_success'), true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function setPermanentSwitch(){
        try{
            if(IS_POST){
                $this->service->setPermanentSwitch();
                return message(t('auth.change_deadline_success'), true);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function list($appid = ''){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message(t('common.list_success') ,true ,['data' => $result]);
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