<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\FeedbackService;
use app\common\model\NotificationModel;
use app\common\service\PluginStorageService;

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
        return $this->render();
    }

    public function handle()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false), 405);
        }
        return json($this->service->handle());
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
            $count = NotificationModel::getUnreadCount(0, true);
            return json(message('', true, ['count' => $count]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function notificationList()
    {
        try {
            $list = NotificationModel::getList(0, true);
            return json(message('', true, ['data' => $list]));
        } catch (\Exception $e) {
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
            NotificationModel::markRead((int) $id, 0, true);
            return json(message('', true));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function notificationReadAll()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false), 405);
        }
        try {
            NotificationModel::markAllRead(0, true);
            return json(message('', true));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }
}
