<?php

namespace app\user\model;

use app\common\model\BaseModel;

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

    public function list(){
        try{
            try{
                $userInfo = parent::getUserInfo();
                if(!$userInfo){
                    throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
                }
            }catch (\Exception $e){
                throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
            }
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $data = $this->buildSearchWhere('id|trade_no');

            $data[] = ['userid', '=', $userInfo['id']];
            $list = self::order('id' ,'desc')->where($data)->paginate([
                'list_rows'=> $limit,
                'page' => $current_page,
            ]);
            $content = [
                'Title' => '订单列表',
                '操作' => '获取订单列表',
                '获取条数' => $list->total().' 条',
                'Result' => 'success'
            ];
            event('ActionLog', $content);
            return $list;
        }catch (\Exception $e){
            $content = [
                'Title' => '订单列表',
                '操作' => '获取订单列表',
                '获取条数' => '0 条',
                'Result' => '[errorCode:GetCDKEYListError]'
            ];
            event('ActionLog', $content);
            throw new Exception(t('user.list_failed').'[errorCode:GetOrderListError]');
        }
    }
}
