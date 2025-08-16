<?php
namespace app\admin\service;

use app\admin\model\AppModel;
use app\admin\model\CdkeyModel;
use app\common\service\BaseService;

class CdkeyService extends BaseService
{
    public function __construct()
    {
        $this->model = new CdkeyModel();
        $this->appModel = new AppModel();
    }

    public function setSort(){
        try{
            $result = $this->model->setSort();
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
                $res['info'] = json_decode($res['info'],true);
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}