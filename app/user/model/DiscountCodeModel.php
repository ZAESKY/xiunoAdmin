<?php

namespace app\user\model;

use app\common\model\BaseModel;
use think\Exception;
use think\facade\Db;

class DiscountCodeModel extends BaseModel
{
    protected $name = 'discount_code';

    public function getByUserId($userId)
    {
        return self::where('user_id', $userId)->find();
    }

    public function getByCode($code)
    {
        return self::where('code', $code)->find();
    }

    public function generateCode($userId)
    {
        // 一个用户只能有一个有效折扣码
        $existing = $this->getByUserId($userId);
        if ($existing) {
            return $existing;
        }

        // 生成唯一 12 位码
        for ($i = 0; $i < 5; $i++) {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
            $row = self::where('code', $code)->find();
            if (!$row) {
                break;
            }
            $code = ''; // 碰撞，重试
        }
        if (empty($code)) {
            throw new Exception('生成折扣码失败，请重试');
        }

        self::insert([
            'user_id' => $userId,
            'code' => $code,
            'status' => 1,
            'created_at' => datetime(),
        ]);

        return self::where('code', $code)->find();
    }

    public function list()
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;

        $data = $this->buildSearchWhere('id|code');
        return self::order('id', 'desc')->where($data)->paginate([
            'list_rows' => $limit,
            'page' => $current_page,
        ]);
    }
}
