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
            case 'payment':
                try{
                    $model = new \app\user\model\PaymentModel();
                    return $model->editBinding();
                }catch (\Exception $e){
                    throw new Exception($e->getMessage());
                }
            default:
                throw new Exception('不存在此类型！');
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
            case 'payment':
                try{
                    $model = new \app\user\model\PaymentModel();
                    $model->unbind($id);
                    return true;
                }catch (\Exception $e){
                    throw new Exception($e->getMessage());
                }
            default:
                throw new Exception('不存在此类型！');
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
                    $res['appName'] = '获取失败';
                    $res['remainderReplaceNumber'] = '获取失败';
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

    public function payment(){
        $model = new \app\user\model\PaymentModel();
        try{
            $result = $model->myList();
            foreach($result as $res){
                try{
                    $appInfo = parent::getAppInfo($res['appid']);
                }catch (\Exception $e){
                    $appInfo = false;
                }
                if(!$appInfo){
                    $res['appName'] = '获取失败';
                    $res['remainderReplaceNumber'] = '获取失败';
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