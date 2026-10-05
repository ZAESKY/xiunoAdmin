<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;

class PayModel extends BaseModel
{
    protected $name = 'pay';

    public function getInfo($trade_no)
    {
        try{
            $result = self::where('trade_no', $trade_no)->find();
            if (!$result) {
                return false;
            }
            return $result;
        }catch (\Exception $e){
            return false;
        }
    }

    public function edit(array $wap = [])
    {
        $buy_type = !empty($wap['buy_type']) ? $wap['buy_type'] : null;
        $num = !empty($wap['num']) ? $wap['num'] : 1;
        $name = !empty($wap['name']) ? $wap['name'] : null;
        $money = !empty($wap['money']) ? qh_money_format($wap['money']) : '0.00';
        $input = !empty($wap['input']) ? $wap['input'] : '';
        $userId = !empty($wap['userid']) ? intval($wap['userid']) : null;

        if(empty($buy_type)){
            return message(t('order.create_buy_type_empty'), false);
        }
        if(empty($name)){
            return message(t('order.create_name_empty'), false);
        }
        if(empty($userId)){
            return message(t('order.create_user_id_empty').$userId, false);
        }
        // Keep the legacy numeric format while making new order identifiers
        // impractical to enumerate (old format only had 889 possibilities/sec).
        do {
            $trade_no = date('YmdHis') . sprintf('%09d', random_int(0, 999999999));
        } while (self::where('trade_no', $trade_no)->find());
        $data = [
            'trade_no' => $trade_no,
            'buy_type' => $buy_type,
            'num' => $num,
            'name' => $name,
            'money' => $money,
            'input' => $input,
            'ip' => get_client_ip(),
            'addtime' => datetime(),
            'status' => 0,
            'userid' => $userId,
            'discount_code' => $wap['discount_code'] ?? null,
        ];
        try {
            self::insert($data);
            return message(t('order.create_success'), true, ['trade_no' => $trade_no]);
        } catch (\Exception $e) {
            return message(t('order.create_error'), false);
        }

    }

    public function drop($trade_no){
        try{
            if(empty($trade_no)){
                throw new Exception(t('order.missing_id'));
            }
            $row = $this->getInfo($trade_no);
            if(!$row){
                throw new Exception(t('common.no_data'));
            }
            self::where('trade_no', $trade_no)->delete();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setStatus(){
        try{
            $post = request()->post();
            $trade_no = !empty($post['trade_no'])?$post['trade_no']:null;
            $status = !empty($post['status'])?1:0;

            if(empty($trade_no)){
                throw new Exception(t('order.missing_id'));
            }
            $row = $this->getInfo($trade_no);
            if(!$row){
                throw new Exception(t('common.no_data'));
            }

            self::where('trade_no', $trade_no)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list()
    {
        try{
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $data = $this->buildSearchWhere('trade_no|name');

            $list = self::order('id', 'asc')->where($data)->paginate([
                'list_rows' => $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
