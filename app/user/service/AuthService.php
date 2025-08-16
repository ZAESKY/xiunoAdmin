<?php
namespace app\user\service;

use app\user\model\AuthModel;
use app\common\service\UserBaseService;
use think\Exception;

class AuthService extends UserBaseService
{
    public function __construct() {
        $this->model = new AuthModel();
    }

    public function list() {
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