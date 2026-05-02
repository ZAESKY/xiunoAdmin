<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\FeedbackService;
use app\common\model\NotificationModel;

class Feedback extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new FeedbackService();
    }

    public function list()
    {
        try {
            if (IS_POST) {
                $result = $this->service->list();
                return json(message(t('common.list_success'), true, ['data' => $result]));
            }
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
        try {
            return $this->render();
        } catch (\Exception $e) {
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }

    public function handle()
    {
        return json($this->service->handle());
    }

    /**
     * Get notification count for the admin bell badge
     */
    public function notificationCount()
    {
        try {
            $count = NotificationModel::getUnreadCount(0, true);
            return json(message('', true, ['count' => $count]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * Get notification list for the admin bell popup
     */
    public function notificationList()
    {
        try {
            $list = NotificationModel::getList(0, true);
            return json(message('', true, ['data' => $list]));
        } catch (\Exception $e) {
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
            NotificationModel::markRead((int)$id, 0, true);
            return json(message('', true));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * Mark all notifications as read
     */
    public function notificationReadAll()
    {
        try {
            NotificationModel::markAllRead(0, true);
            return json(message('', true));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }
}
