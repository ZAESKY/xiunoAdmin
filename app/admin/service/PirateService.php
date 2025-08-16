<?php
namespace app\admin\service;

use app\admin\model\PirateModel;
use app\common\service\BaseService;
use think\Exception;

class PirateService extends BaseService
{
    public function __construct()
    {
        $this->model = new PirateModel();
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