<?php
namespace app\user\service;

use app\user\model\PirateModel;
use app\common\service\UserBaseService;
use think\Exception;

class PirateService extends UserBaseService
{
    public function __construct()
    {
        $this->model = new PirateModel();
    }
    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                if(empty($res['param'])){
                    $res['param'] = '无参数';
                }else{
                    $res['param'] = '******';
                }
                $res['ip'] = '******';
                $res['addtime'] = '******';
                $appInfo = parent::getAppInfo($res['appid']);
                $res['appName'] = $appInfo['name'];
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}