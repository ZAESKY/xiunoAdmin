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
        $limit = sf_page_limit($post['limit'] ?? null, 10);
        $current_page = sf_page_number($post['current_page'] ?? null);

        $where = [['referrer_user_id', '=', $userId]];
        $data = array_merge($where, $this->buildSearchWhere('id|discount_code|pay_trade_no'));

        return self::field(
            'id,pay_trade_no,discount_code,paid_amount,rebate_base_amount,' .
            'rebate_rate,rebate_amount,status,settle_at,settled_at,created_at'
        )->order('id', 'desc')->where($data)->paginate([
            'list_rows' => $limit,
            'page' => $current_page,
        ]);
    }

    public function getInfo($id)
    {
        return self::where('id', $id)->find();
    }
}
