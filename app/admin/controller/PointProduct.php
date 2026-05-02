<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PointProductService;

class PointProduct extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new PointProductService();
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
