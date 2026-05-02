<?php

namespace app\user\service;

use app\user\model\FeedbackModel;
use app\common\service\UserBaseService;
use think\Exception;

class FeedbackService extends UserBaseService
{
    public function __construct()
    {
        $this->model = new FeedbackModel();
    }

    public function submit()
    {
        try {
            $userInfo = $this->getCurrentUserInfo();
            $result = $this->model->submit($userInfo);
            return message(t('feedback.submit_success'), true);
        } catch (\Throwable $e) {
            return message($e->getMessage(), false);
        }
    }

    public function checkPending()
    {
        try {
            $userInfo = $this->getCurrentUserInfo();
            $hasPending = $this->model->hasPending((int) $userInfo['id']);
            return message('', true, ['has_pending' => $hasPending]);
        } catch (\Throwable $e) {
            return message($e->getMessage(), false);
        }
    }

    public function getMyList()
    {
        try {
            $userInfo = $this->getCurrentUserInfo();
            return $this->model->getMyList((int) $userInfo['id']);
        } catch (\Throwable $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function getCurrentUserInfo(): array
    {
        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }

        if (is_object($userInfo) && method_exists($userInfo, 'toArray')) {
            $userInfo = $userInfo->toArray();
        } elseif (is_object($userInfo)) {
            $userInfo = get_object_vars($userInfo);
        }

        if (empty($userInfo['id'])) {
            throw new Exception(t('user.info_error'));
        }

        return $userInfo;
    }
}
