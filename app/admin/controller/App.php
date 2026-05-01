<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use think\facade\View;
use app\admin\service\AppService;
class App extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new AppService();
    }

    public function edit(){
        if (IS_POST) {
            return $this->service->edit();
        }
        View::assign('check_type_list',  $this->service->getCheckTypeList());
        return $this->render();
    }

    public function list($id = ''){
        try{
            if (IS_POST){
                $result = $this->service->list();
                return message(t('common.list_success') ,true, ['data' => $result]);
            }
        }catch (\Exception $e){
            return message($e->getMessage() ,false);
        }
        try{
            View::assign('check_type_list',  $this->service->getCheckTypeList());
            View::assign('id', $id);
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }

}