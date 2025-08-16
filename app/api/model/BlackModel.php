<?php

namespace app\api\model;

use app\common\model\BaseModel;

class BlackModel extends BaseModel
{
    protected $name = "black";

    public function initialize()
    {
        parent::initialize();
    }

    public function getInfo(array $map){
        try{
            $result = BlackModel::where($map)->find();
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