<?php

namespace app\user\model;

use app\common\model\BaseModel;
use app\common\model\NotificationModel;
use think\Exception;

class FeedbackModel extends BaseModel
{
    protected $name = 'feedback';

    const STATUS_PENDING  = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_REJECTED = 2;

    public function list()
    {
        $userInfo = (new \app\user\model\User())->getInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }
        return $this->getMyList((int) $userInfo['id']);
    }

    public function hasPending(int $userId): bool
    {
        $count = self::where('user_id', $userId)
            ->where('status', self::STATUS_PENDING)
            ->count();
        return $count > 0;
    }

    public function submit(array $userInfo)
    {
        $post = request()->post();
        $title = trim((string)($post['title'] ?? ''));
        $content = trim((string)($post['content'] ?? ''));

        if (empty($title)) {
            throw new Exception(t('feedback.title_required'));
        }
        if (empty($content)) {
            throw new Exception(t('feedback.content_required'));
        }

        $userId = (int) $userInfo['id'];
        $username = (string)($userInfo['username'] ?? '');

        if ($this->hasPending($userId)) {
            throw new Exception(t('feedback.has_pending'));
        }

        self::insert([
            'user_id'    => $userId,
            'title'      => $title,
            'content'    => $content,
            'status'     => self::STATUS_PENDING,
            'created_at' => datetime(),
        ]);

        try {
            NotificationModel::add([
                'user_id'    => 0,
                'title'      => t('feedback.notify_title_new'),
                'content'    => t('feedback.notify_content_new', [
                    'username' => $username,
                    'title'    => $title,
                ]),
                'type'       => 'feedback_new',
                'is_read'    => 0,
                'created_at' => datetime(),
            ]);
        } catch (\Throwable $e) {
            trace('Feedback notification failed: ' . $e->getMessage(), 'error');
        }

        return true;
    }

    public function getMyList(int $userId)
    {
        try {
            $post = request()->post();
            $limit = !empty($post['limit']) ? $post['limit'] : 10;
            $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;

            $data = [];
            $text = $post['text'] ?? '';
            if (!empty($text)) {
                $data[] = ['title', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $text) . '%'];
            }
            $status = $post['status'] ?? '';
            if ($status !== '' && $status !== null) {
                $data[] = ['status', '=', intval($status)];
            }
            $data[] = ['user_id', '=', $userId];

            return self::order('id', 'desc')->where($data)->paginate([
                'list_rows' => $limit,
                'page'      => $current_page,
            ]);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
