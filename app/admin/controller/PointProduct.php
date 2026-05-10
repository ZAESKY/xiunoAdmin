<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PointProductService;

class PointProduct extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->denyIfClosed();
        $this->service = new PointProductService();
    }

    private function denyIfClosed()
    {
        if (!feature_enabled('feature_point_exchange_enabled')) {
            if (IS_POST) {
                exit(json_encode(message('积分兑换功能已关闭', false), JSON_UNESCAPED_UNICODE));
            }
            exit($this->render('/public/error', ['msg' => '积分兑换功能已关闭']));
        }
    }

    public function records()
    {
        try {
            if (IS_POST) {
                return message(t('common.list_success'), true, ['data' => $this->service->records()]);
            }
            return $this->render();
        } catch (\Throwable $e) {
            return message($e->getMessage(), false);
        }
    }
}
