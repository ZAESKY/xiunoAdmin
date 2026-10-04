<?php

namespace app\user\model;

use app\common\model\BaseModel;
use app\common\model\NotificationModel;
use think\Exception;
use think\facade\Db;

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
        $title = trim(strip_tags((string)($post['title'] ?? '')));
        $content = clean_rich_text($post['content'] ?? '');
        $type = !empty($post['type']) && in_array($post['type'], ['bug', 'feature', 'other']) ? $post['type'] : 'other';

        if (empty($title)) {
            throw new Exception(t('feedback.title_required'));
        }
        if (!rich_text_has_content($content)) {
            throw new Exception(t('feedback.content_required'));
        }

        $userId = (int) $userInfo['id'];
        $username = (string)($userInfo['username'] ?? '');

        if ($this->hasPending($userId)) {
            throw new Exception(t('feedback.has_pending'));
        }

        $feedbackId = Db::name('feedback')->insertGetId([
            'user_id'    => $userId,
            'title'      => $title,
            'content'    => $content,
            'type'       => $type,
            'status'     => self::STATUS_PENDING,
            'created_at' => datetime(),
        ]);

        // 将反馈中的临时图片移动到正式目录
        if (!empty($content)) {
            $movedContent = move_temp_images_in_content($content);
            if ($movedContent !== $content) {
                Db::name('feedback')->where('id', $feedbackId)->update(['content' => $movedContent]);
            }
        }

        try {
            NotificationModel::add([
                'user_id'    => 0,
                'title'      => t('feedback.notify_title_new'),
                'content'    => t('feedback.notify_content_new', [
                    'username' => $username,
                    'title'    => $title,
                ]),
                'type'       => 'feedback_new',
                'variables'  => ['username' => $username, 'feedback_title' => $title],
                'is_read'    => 0,
                'created_at' => datetime(),
            ]);
        } catch (\Throwable $e) {}

        return true;
    }

    public function getMyList(int $userId)
    {
        try {
            $post = request()->post();
            $limit = sf_page_limit($post['limit'] ?? null, 10);
            $current_page = sf_page_number($post['current_page'] ?? null);

            $data = [];
            $text = $post['text'] ?? '';
            if (!empty($text)) {
                $data[] = ['title', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $text) . '%'];
            }
            $status = $post['status'] ?? '';
            if ($status !== '' && $status !== null) {
                $data[] = ['f.status', '=', intval($status)];
            }
            $data[] = ['user_id', '=', $userId];

            $list = self::alias('f')
                ->join('SF_user u', 'f.user_id = u.id', 'LEFT')
                ->field('f.*, u.username')
                ->order('f.id', 'desc')
                ->where($data)->paginate([
                    'list_rows' => $limit,
                    'page'      => $current_page,
                ]);
            $list->each(static function ($item) {
                $item['title'] = trim(strip_tags((string)($item['title'] ?? '')));
                $item['content'] = clean_rich_text($item['content'] ?? '');
                $item['reply'] = clean_rich_text($item['reply'] ?? '');
                return $item;
            });
            return $list;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
