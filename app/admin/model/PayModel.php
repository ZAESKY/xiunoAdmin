<?php

namespace app\admin\model;

use app\common\model\BaseModel;

class PayModel extends BaseModel
{
    protected $name = 'pay';

    public function getInfo($trade_no)
    {
        try{
            $result = self::where('trade_no', $trade_no)->find();
            if ($result) {
                return $result;
            } else {
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    public function edit(array $wap = [])
    {
        $buy_type = !empty($wap['buy_type']) ? $wap['buy_type'] : null;
        $num = !empty($wap['num']) ? $wap['num'] : 1;
        $name = !empty($wap['name']) ? $wap['name'] : null;
        $money = !empty($wap['money']) ? round($wap['money'], 2) : 0;
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
        $trade_no = date("YmdHis") . mt_rand(111, 999);
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
        ];
        try {
            self::insert($data);
            return message(t('order.create_success'), true, ['trade_no' => $trade_no]);
        } catch (\Exception $e) {
            return message(t('order.create_error'), false);
        }

    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('order.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('common.no_data'));
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
            $status = !empty($post['status'])?1:0;

            if(empty($id)){
                throw new Exception(t('order.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('common.no_data'));
            }

            self::where('id', $id)
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
            $limit = !empty($post['limit']) ? $post['limit'] : 10;
            $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
            $appid = !empty($post['appid']) ? $post['appid'] : null;
            $data = $this->buildSearchWhere('id|username');
            if (!empty($appid)) {
                $data[] = ['appid', '=', $appid];
            }

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
