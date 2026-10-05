<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\service\AccountingLogService;
use think\facade\Db;

class PointLog extends UserBackend
{
    public function index()
    {
        if (IS_POST) {
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 15);
            $page = qh_page_number($post['current_page'] ?? null);
            $list = Db::name('point_log')
                ->where('user_id', $this->userId)
                ->order('id', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);
            return json([
                'code' => 0,
                'msg' => '',
                'count' => $list->total(),
                'data' => AccountingLogService::decorateItems(
                    $list->items(),
                    AccountingLogService::LEDGER_POINT
                ),
            ]);
        }
        return $this->render();
    }
}
