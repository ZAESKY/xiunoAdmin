<?php
namespace app\admin\service;

use app\admin\model\PaymentModel;
use app\common\service\BaseService;
use think\Exception;

class PaymentService extends BaseService
{
    public function __construct(){
        $this->model = new PaymentModel();
    }

    public function setPermanentSwitch(){
        try{
            $result = $this->model->setPermanentSwitch();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                $appInfo = parent::getAppInfo($res['appid']);
                $res['appName'] = $appInfo['name'];
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}