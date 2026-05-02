<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\model\BalanceLogModel;
use think\facade\View;

class BalanceLog extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
    }

    public function index()
    {
        if (IS_POST) {
            return $this->myList();
        }
        return $this->render();
    }

    private function myList()
    {
        try {
            $post = request()->post();
            $limit = !empty($post['limit']) ? $post['limit'] : 15;
            $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;

            $list = BalanceLogModel::where('user_id', $this->userId)
                ->order('id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page,
                ]);

            return message('ok', true, ['data' => $list]);
        } catch (\Exception $e) {
            return message($e->getMessage(), false);
        }
    }
}
