<?php
namespace app\admin\service;

use app\admin\model\SetModel;
use app\common\service\BaseService;
use think\Exception;
use think\facade\Cache;

class SetService extends BaseService
{
    public function __construct()
    {
        $this->model = new SetModel();
    }

    public function saveAll($data)
    {
        try{
            $this->model->saveAll($data);
            Cache::tag('SF_Set')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function all()
    {
        try{
            $result = $this->model->all();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public static function getGroupList(){
        return config('site.groupList');
    }
}