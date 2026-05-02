<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use think\Exception;
class MyListService extends UserBaseService
{
    public function editBinding($type){
        switch ($type){
            case 'auth':
                try{
                    $model = new \app\user\model\AuthModel();
                    return $model->editBinding();
                }catch (\Exception $e){
                    throw new Exception($e->getMessage());
                }
            default:
                throw new Exception(t('common_ui.type_error'));
        }
    }

    public function unbind($id, $type){
        switch ($type){
            case 'auth':
                try{
                    $model = new \app\user\model\AuthModel();
                    $model->unbind($id);
                    return true;
                }catch (\Exception $e){
                    throw new Exception($e->getMessage());
                }
            default:
                throw new Exception(t('common_ui.type_error'));
        }
    }

    public function auth(){
        $model = new \app\user\model\AuthModel();
        try{
            $result = $model->myList();
            foreach($result as $res){
                try{
                    $appInfo = parent::getAppInfo($res['appid']);
                }catch (\Exception $e){
                    $appInfo = false;
                }
                if(!$appInfo){
                    $res['appName'] = t('common.load_failed');
                    $res['remainderReplaceNumber'] = t('common.load_failed');
                }else{
                    $res['appName'] = $appInfo['name'];
                    $remainderReplaceNumber = intval($appInfo['free_replace_number'] - $res['replace_number']);
                    $res['remainderReplacePrice'] = '￥ '.($remainderReplaceNumber > 0 ? 0 : $appInfo['replace_money']);
                }
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

}
