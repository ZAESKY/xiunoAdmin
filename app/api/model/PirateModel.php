<?php

namespace app\api\model;

use app\common\model\BaseModel;

class PirateModel extends BaseModel
{
    protected $name = 'pirate';

    public function initialize()
    {
        parent::initialize();
    }

    public function getInfo($map, $field = '*'){
        $result = PirateModel::where($map)->field($field)->find();
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    public function edit($param){
        $appid = !empty($param['appid'])?intval($param['appid']):null;
        if(!$appid){
            return message('请提交APPID！',false);
        }
        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return message('不存在此应用！',false);
            }
        }catch (\Exception $e){
            return message('服务器错误！'.$e->getMessage() ,false);
        }
        
        $auth_info = !empty($param['auth_info'])?$param['auth_info']:null;
        $param = !empty($param)?json_encode($param):null;

        $pirateInfo = PirateModel::getInfo([['pirate_info', '=', $auth_info],['appid', '=', $appid]], 'id');
        if($pirateInfo){
            $data = [
                'ip' => get_client_ip(),
                'param' => $param,
                'addtime' => datetime(),
            ];
            try{
                PirateModel::where('id', $pirateInfo['id'])
                    ->data($data)
                    ->update();
                return message('更新成功！' ,true);
            } catch (\Exception $e) {
                return message('更新失败！'.$e->getMessage() ,false);
            }
        }else{
            $data = [
                'pirate_info' => $auth_info,
                'ip' => get_client_ip(),
                'param' => $param,
                'addtime' => datetime(),
                'appid' => $appid,
            ];

            try{
                PirateModel::insert($data);
                return message('入库成功！' ,true);
            } catch (\Exception $e) {
                return message('入库失败！'.$e->getMessage(),false);
            }
        }
    }
}