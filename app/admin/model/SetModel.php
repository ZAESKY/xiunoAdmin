<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;
use think\facade\Request;
use think\facade\Cache;
/**
 * 系统配置-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class SetModel
 * @package app\admin\model
 */
class SetModel extends BaseModel
{
    // 设置数据表名
    protected $name = 'config';

    public function all(){
        try{
            $all = self::select();
            Cache::tag('SF_Set')->set('SF_SiteAllList', $all);
            return $all;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}