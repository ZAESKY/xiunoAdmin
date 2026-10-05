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
        $cacheKey = 'VersionList'.$appid.$version.(is_array($beta) ? implode(',', $beta) : $beta);
        if(!empty(Cache::get($cacheKey))){
            return Cache::get($cacheKey);
        }else{
            if(is_array($beta)){
                $list = VersionModel::where([['appid', '=', $appid],['status', '=', 1],['version', '>', $version],['type', '=', 0]])
                    ->whereIn('beta', $beta)->order('version', 'desc')->limit(1)->select();
            }else{
                $list = VersionModel::where([['appid', '=', $appid],['status', '=', 1],['version', '>', $version],['beta', '=', $beta],['type', '=', 0]])
                    ->order('version', 'desc')->limit(1)->select();
            }
            $count = count($list);
            $data = ['list' => $list, 'count' => $count];
            Cache::tag('QH_Version')->set($cacheKey, $data);
            return $data;
        }
    }
}
