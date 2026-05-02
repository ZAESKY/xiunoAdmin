<?php

namespace app\user\model;

use app\common\model\BaseModel;
use think\facade\Db;

class RebateModel extends BaseModel
{
    protected $name = 'rebate_record';

    public function getListByReferrer($userId)
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;

        $where = [['referrer_user_id', '=', $userId]];
        $data = array_merge($where, $this->buildSearchWhere('id|discount_code|pay_trade_no'));

        return self::order('id', 'desc')->where($data)->paginate([
            'list_rows' => $limit,
            'page' => $current_page,
        ]);
    }

    public function getInfo($id)
    {
        return self::where('id', $id)->find();
    }
}
