<?php
namespace app\user\service;

use app\admin\model\AppModel;
use app\user\model\CdkeyModel;
use app\common\service\UserBaseService;
use think\Exception;
class CdkeyService extends UserBaseService
{
    public function __construct(){
        $this->model = new CdkeyModel();
        $this->appModel = new AppModel();
    }

    public function exchange(){
        try{
            return $this->model->exchange();
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                $appInfo = $this->appModel->getInfo($res['appid']);
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