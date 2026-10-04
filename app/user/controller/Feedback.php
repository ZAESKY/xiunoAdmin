<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\FeedbackService;
use app\common\model\NotificationModel;
use app\common\service\PluginStorageService;

class Feedback extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new FeedbackService();
    }

    public function index()
    {
        if (IS_POST) {
            try {
                return json($this->service->submit());
            } catch (\Throwable $e) {
                return json(message($e->getMessage(), false));
            }
        }
        return $this->render();
    }

    public function checkPending()
    {
        return json($this->service->checkPending());
    }

    public function myList()
    {
        try {
            $result = $this->service->getMyList();
            return json(message(t('common.list_success'), true, ['data' => $result]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function uploadImage()
    {
        if (!IS_POST) {
            return json(['code' => 1, 'msg' => t('common.illegal_request')], 405);
        }
        try {
            $file = request()->file('file');
            if (!$file) {
                return json(['code' => 1, 'msg' => t('upload.please_select_image')]);
            }
            $allowedExt = 'jpg,jpeg,png,gif,bmp,webp';
            $maxSize = 5 * 1024 * 1024;
            $ext = strtolower($file->getOriginalExtension());
            if (!in_array($ext, explode(',', $allowedExt))) {
                return json(['code' => 1, 'msg' => t('feedback.image_type_error')]);
            }
            if ($file->getSize() > $maxSize) {
                return json(['code' => 1, 'msg' => t('feedback.image_size_error')]);
            }
            $stored = (new PluginStorageService())->storeUploadedFile(
                $file,
                'feedback',
                explode(',', $allowedExt),
                $maxSize
            );
            $url = $stored['url'];
            return json(['code' => 0, 'msg' => 'ok', 'data' => ['src' => $url, 'title' => $file->getOriginalName()]]);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    // === 通知相关 ===
    public function notificationCount()
    {
        try {
            $count = NotificationModel::getUnreadCount($this->getLoginUserId(), false);
            return json(message('', true, ['count' => $count]));
        } catch (\Throwable $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function notificationList()
    {
        try {
            $list = NotificationModel::getList($this->getLoginUserId(), false);
            return json(message('', true, ['data' => $list]));
        } catch (\Throwable $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function notificationRead()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false), 405);
        }
        try {
            $id = request()->post('id', 0);
            NotificationModel::markRead((int) $id, $this->getLoginUserId(), false);
            return json(message('', true));
        } catch (\Throwable $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function notificationReadAll()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false), 405);
        }
        try {
            NotificationModel::markAllRead($this->getLoginUserId(), false);
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
