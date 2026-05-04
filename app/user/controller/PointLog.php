<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use think\facade\Db;

class PointLog extends UserBackend
{
    public function index()
    {
        if (IS_POST) {
            $post = request()->post();
            $limit = !empty($post['limit']) ? intval($post['limit']) : 15;
            $page = !empty($post['current_page']) ? intval($post['current_page']) : 1;
            $list = Db::name('point_log')
                ->where('user_id', $this->userId)
                ->order('id', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);
            return json(['code' => 0, 'msg' => '', 'count' => $list->total(), 'data' => $list->items()]);
        }
        return $this->render();
    }
}
