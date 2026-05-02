<?php

namespace app\admin\service;

use app\admin\model\FeedbackModel;
use app\common\service\BaseService;
use think\Exception;

class FeedbackService extends BaseService
{
    public function __construct()
    {
        $this->model = new FeedbackModel();
    }

    public function list()
    {
        try {
            return $this->model->list();
        } catch (\Throwable $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getInfo($id)
    {
        try {
            $result = $this->model->getInfo($id);
            if (!$result) {
                throw new Exception(t('common.no_data'));
            }
            return $result;
        } catch (\Throwable $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function handle()
    {
        try {
            $result = $this->model->handle();
            return message(t('feedback.handle_success'), true);
        } catch (\Throwable $e) {
            return message($e->getMessage(), false);
        }
    }
}
