<?php

namespace app\pay\service;

use app\admin\model\PayModel;
use app\common\service\BaseService;
use app\admin\model\OrderModel;
use think\Exception;
use think\facade\Db;

class CommonService extends BaseService
{
    public function __construct(){
        $this->payModel = new PayModel();
        $this->orderModel = new OrderModel();
    }

    public function processOrder($srow){
        try{
            //if(!is_array($srow)) return false;
            $srow = $this->payModel->getInfo($srow['trade_no']);
            if(!$srow) return false;
            $srow = $srow->toArray();
            //$input = unserialize($srow['input']);
            //throw new Exception(gettype($srow));
            switch ($srow['buy_type']){
                case 'recharge':
                    try{
                        $userId = !empty($srow['userid']) ? intval($srow['userid']) : null;
                        $money = !empty($srow['money']) ? round($srow['money'], 2) : null;
                        if(empty($userId)){
                            return false;
                        }
                        if(empty($money)){
                            return false;
                        }
                        try{
                            Db::name('user')
                                ->where('id', $srow['userid'])
                                ->inc('balance', $srow['money'])
                                ->update();
                            $srow['status'] = 1;
                        }catch (\Exception $e){
                            $srow['status'] = 3;
                            $srow['return'] = '执行充值操作失败！';
                        }
                        $this->orderModel->edit($srow);
                    }catch (\Exception $e){
                        $srow['status'] = 3;
                        $srow['return'] = $e->getMessage();
                        $this->orderModel->edit($srow);
                        return false;
                    }
                    break;
                default:
                    return false;
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}