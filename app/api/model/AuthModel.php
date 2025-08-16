<?php

namespace app\api\model;

use app\common\model\BaseModel;

class AuthModel extends BaseModel
{
    protected $name = "auth";

    public function initialize()
    {
        parent::initialize();
    }

    public function getInfo(array $map){
        try{
            $result = AuthModel::where($map)->find();
            if($result){
                return $result;
            }else{
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}