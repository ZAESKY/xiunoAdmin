<?php
namespace app\admin\service;

use app\common\service\BaseService;

class AddonService extends BaseService
{
    public function __construct(){

    }

    public function list(){
        try{
            $list = get_addon_list();
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}