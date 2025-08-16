<?php

namespace app\api\model;

use app\common\model\BaseModel;
use think\facade\Cache;

class VersionModel extends BaseModel
{
    protected $name = 'version';

    public function initialize()
    {
        parent::initialize();
    }

    public function getInfo($map){
        $result = VersionModel::where($map)->find();
        if($result){
            return $result;
        }else{
            return false;
        }
    }

    public function getAppUpdateVersionList($appid,$version,$beta = 0){
        if(!empty(Cache::get('VersionList'.$appid.$version.$beta))){
            return Cache::get('VersionList'.$appid.$version.$beta);
        }else{
            $list = VersionModel::where([['appid', '=', $appid],['status', '=', 1],['version', '>', $version],['beta', '=', $beta],['type', '=', 1]])->select();
            $count = count($list);
            $data = ['list' => $list, 'count' => $count];
            Cache::tag('SF_Version')->set('VersionList'.$appid.$version.$beta, $data);
            return $data;
        }
    }
}