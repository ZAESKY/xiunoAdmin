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
                $infoArr = json_decode($res['info'], true) ?: [];
                if ($res['cdkey_type'] === 'user' && !empty($infoArr['power'])) {
                    $powerInfo = parent::getPowerPriceInfo(intval($infoArr['power']));
                    $infoArr['power_name'] = $powerInfo ? $powerInfo['name'] : '';
                }
                $res['info'] = $infoArr;
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}