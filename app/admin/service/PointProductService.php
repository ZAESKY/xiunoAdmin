<?php

namespace app\admin\service;

use app\admin\model\PointProductModel;
use app\common\service\BaseService;
use think\facade\Db;

class PointProductService extends BaseService
{
    public function __construct()
    {
        $this->model = new PointProductModel();
    }

    public function records()
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $currentPage = !empty($post['current_page']) ? $post['current_page'] : 1;
        $data = [];
        $text = trim((string)($post['text'] ?? ''));
        if ($text !== '') {
            $data[] = ['r.id|r.product_name|u.username', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%'];
        }

        return Db::name('point_exchange_record')
            ->alias('r')
            ->leftJoin('SF_user u', 'r.user_id = u.id')
            ->field('r.*, u.username')
            ->where($data)
            ->order('r.id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $currentPage,
            ]);
    }
}
