<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\PointExchangeService;

class PointExchange extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->denyIfClosed();
        $this->service = new PointExchangeService();
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

    public function exchange()
    {
        try {
            if (IS_POST) {
                return $this->service->exchange();
            }
        } catch (\Throwable $e) {
            return message($e->getMessage(), false);
        }
    }

    public function myRecords()
    {
        try {
            if (IS_POST) {
                return message(t('common.list_success'), true, ['data' => $this->service->myRecords()]);
            }
        } catch (\Throwable $e) {
            return message($e->getMessage(), false);
        }
    }
}
