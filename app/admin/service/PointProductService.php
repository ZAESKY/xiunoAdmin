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
        $limit = qh_page_limit($post['limit'] ?? null, 10);
        $currentPage = qh_page_number($post['current_page'] ?? null);
        $data = [];
        $text = trim((string)($post['text'] ?? ''));
        if ($text !== '') {
            $data[] = ['r.id|r.product_name|u.username', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%'];
        }

        return Db::name('point_exchange_record')
            ->alias('r')
            ->leftJoin('QH_user u', 'r.user_id = u.id')
            ->field('r.*, u.username')
            ->where($data)
            ->order('r.id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $currentPage,
            ]);
    }
}
