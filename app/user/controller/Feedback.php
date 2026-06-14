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
        try {
            $file = request()->file('file');
            if (!$file) {
                return json(['code' => 1, 'msg' => '请选择图片']);
            }
            $allowedExt = 'jpg,jpeg,png,gif,bmp,webp';
            $maxSize = 5 * 1024 * 1024;
            $ext = strtolower($file->getOriginalExtension());
            if (!in_array($ext, explode(',', $allowedExt))) {
                return json(['code' => 1, 'msg' => '仅支持 jpg/png/gif/bmp/webp 图片']);
            }
            if ($file->getSize() > $maxSize) {
                return json(['code' => 1, 'msg' => '图片不能超过5MB']);
            }
            $uploadDir = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . date('Ymd');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = md5(uniqid(mt_rand(), true)) . '.' . $ext;
            $info = $file->move($uploadDir, $fileName);
            if (!$info) {
                return json(['code' => 1, 'msg' => $file->getError()]);
            }
            $url = '/upload/temp/' . date('Ymd') . '/' . $fileName;
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
