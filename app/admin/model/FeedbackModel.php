<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use app\common\model\NotificationModel;
use think\Exception;

class FeedbackModel extends BaseModel
{
    protected $name = 'feedback';

    const STATUS_PENDING  = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_REJECTED = 2;

    public function getInfo($id)
    {
        try {
            $result = self::alias('f')
                ->join('SF_user u', 'f.user_id = u.id', 'LEFT')
                ->field('f.*, u.username')
                ->where('f.id', $id)
                ->find();
            if ($result) {
                $result['title'] = trim(strip_tags((string)($result['title'] ?? '')));
                $result['content'] = clean_rich_text($result['content'] ?? '');
                $result['reply'] = clean_rich_text($result['reply'] ?? '');
            }
            return $result ?: false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function list()
    {
        try {
            $post = request()->post();
            $limit = sf_page_limit($post['limit'] ?? null, 10);
            $current_page = sf_page_number($post['current_page'] ?? null);

            $data = $this->buildSearchWhere('f.id|f.title', 'text', '');
            // 手动处理 status 筛选避免 join 后歧义
            $status = $post['status'] ?? '';
            if ($status !== '' && $status !== null) {
                $data[] = ['f.status', '=', intval($status)];
            }
            $type = $post['type'] ?? '';
            if ($type !== '' && in_array($type, ['bug', 'feature', 'other'])) {
                $data[] = ['f.type', '=', $type];
            }

            $list = self::alias('f')
                ->join('SF_user u', 'f.user_id = u.id', 'LEFT')
                ->field('f.*, u.username')
                ->order('f.id', 'desc')
                ->where($data)
                ->paginate([
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

    public function handle()
    {
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : null;
        $reply = clean_rich_text($post['reply'] ?? '');
        $status = isset($post['status']) ? intval($post['status']) : null;

        if (empty($id)) {
            throw new Exception(t('validation.missing_id'));
        }
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED], true)) {
            throw new Exception(t('feedback.invalid_status'));
        }

        $row = $this->getInfo($id);
        if (!$row) {
            throw new Exception(t('common.no_data'));
        }

        if (!rich_text_has_content($reply)) {
            throw new Exception(t('feedback.reply_required'));
        }

        $oldReply = clean_rich_text($row['reply'] ?? '');
        $replyChanged = trim($oldReply) !== trim($reply);
        $statusChanged = intval($row['status']) !== $status;
        if (!$replyChanged && !$statusChanged) {
            throw new Exception(t('feedback.no_changes'));
        }

        self::where('id', $id)->data([
            'reply'      => $reply,
            'status'     => $status,
            'updated_at' => datetime(),
        ])->update();

        // 将回复中的临时图片移动到正式目录
        if (!empty($reply)) {
            $movedReply = move_temp_images_in_content($reply);
            if ($movedReply !== $reply) {
                self::where('id', $id)->update(['reply' => $movedReply]);
                $reply = $movedReply;
            }
        }

        try {
            $statusMap = [
                self::STATUS_PENDING  => t('feedback.status_pending'),
                self::STATUS_ACCEPTED => t('feedback.status_accepted'),
                self::STATUS_REJECTED => t('feedback.status_rejected'),
            ];
            $statusLabel = $statusMap[$status] ?? '';
            $replyText = trim((string)preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($reply, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
            if ($replyText === '' && preg_match('/<img\b/i', $reply)) {
                $replyText = t('feedback.image_reply');
            }
            $replyText = mb_substr($replyText, 0, 2000);
            NotificationModel::add([
                'user_id'    => $row['user_id'],
                'title'      => t('feedback.notify_title_handled'),
                'content'    => t('feedback.notify_content_handled', [
                    'title'  => $row['title'],
                    'status' => $statusLabel,
                    'reply'  => $replyText,
                ]),
                'type'       => 'feedback_handled',
                'variables'  => [
                    'feedback_title' => $row['title'],
                    'review_status'  => $statusLabel,
                    'feedback_reply' => $replyText,
                ],
                'is_read'    => 0,
                'created_at' => datetime(),
            ]);
        } catch (\Throwable $e) {}

        return true;
    }
}
