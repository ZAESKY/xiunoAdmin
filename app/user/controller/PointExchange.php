<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\PointExchangeService;

class PointExchange extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new PointExchangeService();
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
