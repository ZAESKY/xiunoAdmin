<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\FeedbackService;
use app\common\model\NotificationModel;

class Feedback extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new FeedbackService();
    }

    /**
     * Feedback submission page (GET) / submit action (POST)
     */
    public function index()
    {
        if (IS_POST) {
            try {
                return json($this->service->submit());
            } catch (\Throwable $e) {
                $log = date('Y-m-d H:i:s') . ' Feedback Submit Error: ' . $e->getMessage()
                    . ' | File: ' . $e->getFile() . ':' . $e->getLine()
                    . ' | Trace: ' . $e->getTraceAsString() . PHP_EOL;
                file_put_contents(app()->getRuntimePath() . 'feedback_error.log', $log, FILE_APPEND);
                return json(message($e->getMessage(), false));
            }
        }
        try {
            return $this->render();
        } catch (\Exception $e) {
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Check if user has a pending feedback
     */
    public function checkPending()
    {
        return json($this->service->checkPending());
    }

    /**
     * Get current user's feedback list
     */
    public function myList()
    {
        try {
            $result = $this->service->getMyList();
            return json(message(t('common.list_success'), true, ['data' => $result]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * Get notification count for the bell badge
     */
    public function notificationCount()
    {
        try {
            $count = NotificationModel::getUnreadCount($this->getLoginUserId(), false);
            event('ActionLog', [
                'Title' => t('feedback.notifications'),
                'Result' => 'success',
            ]);
            return json(message('', true, ['count' => $count]));
        } catch (\Throwable $e) {
            $log = date('Y-m-d H:i:s') . ' NotifCount Error: ' . $e->getMessage()
                . ' | File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
            file_put_contents(app()->getRuntimePath() . 'feedback_error.log', $log, FILE_APPEND);
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * Get notification list for the bell popup
     */
    public function notificationList()
    {
        try {
            $list = NotificationModel::getList($this->getLoginUserId(), false);
            event('ActionLog', [
                'Title' => t('feedback.notifications'),
                'Result' => 'success',
            ]);
            return json(message('', true, ['data' => $list]));
        } catch (\Throwable $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * Mark a notification as read
     */
    public function notificationRead()
    {
        try {
            $id = request()->post('id', 0);
            NotificationModel::markRead((int) $id, $this->getLoginUserId(), false);
            event('ActionLog', [
                'Title' => t('feedback.notifications'),
                'Result' => 'success',
            ]);
            return json(message('', true));
        } catch (\Throwable $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * Mark all notifications as read
     */
    public function notificationReadAll()
    {
        try {
            NotificationModel::markAllRead($this->getLoginUserId(), false);
            event('ActionLog', [
                'Title' => t('feedback.notifications'),
                'Result' => 'success',
            ]);
            return json(message('', true));
        } catch (\Throwable $e) {
            return json(message($e->getMessage(), false));
        }
    }

    private function getLoginUserId(): int
    {
        if (!empty($this->userInfo['id'])) {
            return (int) $this->userInfo['id'];
        }
        if (!empty($this->userId)) {
            return (int) $this->userId;
        }
        return (int) cookie('userId');
    }
}
