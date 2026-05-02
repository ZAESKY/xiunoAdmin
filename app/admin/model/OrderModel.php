<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;
use think\facade\Db;

class OrderModel extends BaseModel
{
    protected $name = 'order';

    public function getInfo($id)
    {
        try{
            $result = self::where('id', $id)->find();
            if($result){
                return $result;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function edit(array $wap = []){

//            $trade_no = !empty($wap['trade_no']) ? $wap['trade_no'] : null;
//            $type = !empty($wap['type']) ? $wap['type'] : null;
//            $name = !empty($wap['name']) ? $wap['name'] : null;
//            $money = !empty($wap['money']) ? round($wap['money'], 2) : 0;
//            $input = !empty($wap['input']) ? $wap['input'] : '';
//            $num = !empty($wap['num']) ? intval($wap['num']) : 1;
//            $addtime = !empty($wap['addtime']) ? $wap['addtime'] : null;
//            $endtime = !empty($wap['addtime']) ? $wap['addtime'] : null;
//            $userid = !empty($wap['userid']) ? intval($wap['userid']) : null;
//            $status = !empty($wap['status']) ? 1 : 0;
//            $return = !empty($wap['return']) ? $wap['return'] : '无';
//
//            if(empty($trade_no)){
//                return false;
//            }
//            if(empty($type)){
//                return false;
//            }
//            if(empty($name)){
//                return false;
//            }
//            if(empty($money)){
//                return false;
//            }
//            if(empty($input)){
//                return false;
//            }
//            if(empty($addtime)){
//                return false;
//            }
//            if(empty($endtime)){
//                return false;
//            }
//            if(empty($num)){
//                return false;
//            }
//            if(empty($userid)){
//                return false;
//            }
//            if(empty($status)){
//                return false;
//            }
//            if(empty($return)){
//                return false;
//            }
        try{
            self::insert($wap);
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('order.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('order.not_exist'));
            }
            self::where('id', $id)->delete();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setStatus(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $status = !empty($post['status'])?intval($post['status']):0;

            if(empty($id)){
                throw new Exception(t('order.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('order.not_exist'));
            }

            if($status == 5){
                $status = 4;
                try{
                    Db::name('user')
                        ->where('id', $row['userid'])
                        ->inc('balance', $row['money'])
                        ->update();
                    \app\common\model\BalanceLogModel::add($row['userid'], 'refund', floatval($row['money']), '订单退款 +'.$row['money'].' 元', intval($id));
                }catch (\Exception $e){
                    throw new Exception($e->getMessage());
                }
            }
            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $data = $this->buildSearchWhere('id|trade_no');

            $list = self::order('id' ,'desc')->where($data)->paginate([
                'list_rows'=> $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
