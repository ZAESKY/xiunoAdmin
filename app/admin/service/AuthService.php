<?php
namespace app\admin\service;

use app\admin\model\AuthModel;
use app\common\service\BaseService;
use think\Exception;

class AuthService extends BaseService
{
    public function __construct(){
        $this->model = new AuthModel();
    }

    public function setBetaSwitch(){
        try{
            $this->model->setBetaSwitch();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setPermanentSwitch(){
        try{
            $this->model->setPermanentSwitch();
            return true;
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