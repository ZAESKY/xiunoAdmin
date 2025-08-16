<?php
namespace app\user\service;

use app\user\model\PaymentModel;
use app\common\service\UserBaseService;
use think\Exception;

class PaymentService extends UserBaseService
{
    public function __construct(){
        $this->model = new PaymentModel();
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