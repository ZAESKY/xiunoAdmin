<?php

namespace app\admin\service;

use app\common\service\BaseService;
use app\admin\model\VersionModel;
use think\Exception;

class VersionService extends BaseService
{
    public function __construct()
    {
        $this->model = new VersionModel();
    }

    public function setType(){
        try{
            $result = $this->model->setType();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setBeta(){
        try{
            $result = $this->model->setBeta();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function deleteFile(){
        try{
            $result = $this->model->deleteFile();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function checkFile(){
        try{
            $result = $this->model->checkFile();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list()
    {
        try{
            $result = $this->model->list();
            foreach($result as $res){
                $appInfo = parent::getAppInfo($res['appid']);
                $res['appName'] = $appInfo['name'];
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
