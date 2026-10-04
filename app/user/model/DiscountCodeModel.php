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
            $code = strtoupper(bin2hex(random_bytes(6)));
            $row = self::where('code', $code)->find();
            if (!$row) {
                break;
            }
            $code = ''; // 碰撞，重试
        }
        if (empty($code)) {
            throw new Exception(t('discount_code.generate_failed'));
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
        $limit = sf_page_limit($post['limit'] ?? null, 10);
        $current_page = sf_page_number($post['current_page'] ?? null);

        $data = $this->buildSearchWhere('id|code');
        return self::order('id', 'desc')->where($data)->paginate([
            'list_rows' => $limit,
            'page' => $current_page,
        ]);
    }
}
