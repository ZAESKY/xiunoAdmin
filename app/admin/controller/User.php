<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\UserService;
use think\facade\View;

class User extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new UserService();
    }

    public function getAppUserList(){
        try{
            if(IS_POST){
                $appid = $this->request->post('appid/d');
                $result = $this->service->getAppUserList($appid);
                return message('获取列表成功！' ,true, ['data' => $result]);
            }
        }catch (\Exception $e){
            return message($e->getMessage(), false);
        }
    }

    public function list($appid = ''){
        try{
            if(IS_POST){
                $result = $this->service->list();
                return message('获取列表成功！' ,true, ['data' => $result]);
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