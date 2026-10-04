<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use app\common\model\NotificationModel;
use think\Exception;

/**
 * 插件评论-模型
 * @author SF授权系统
 * @since 2026-05-03
 */
class PluginCommentModel extends BaseModel
{
    protected $name = 'plugin_comment';

    public function getInfo($id)
    {
        try {
            $result = self::where('id', $id)->find();
            return $result ?: false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function drop($id)
    {
        try {
            if (empty($id)) {
                throw new Exception(t('plugin_action.comment_id_required'));
            }
            $row = $this->getInfo($id);
            if (!$row) {
                throw new Exception(t('plugin_action.comment_not_found'));
            }

            self::where('id', $id)->delete();

            // 更新插件评论数量
            $pluginModel = new PluginModel();
            $pluginModel->updateCommentCount($row['plugin_id']);

            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function setStatus()
    {
        try {
            $post = request()->post();
            $id = !empty($post['id']) ? intval($post['id']) : null;
            $status = isset($post['status']) ? intval($post['status']) : 0;

            if (empty($id)) {
                throw new Exception(t('plugin_action.comment_id_required'));
            }
            $row = $this->getInfo($id);
            if (!$row) {
                throw new Exception(t('plugin_action.comment_not_found'));
            }

            self::where('id', $id)->data(['status' => $status, 'updated_at' => datetime()])->update();

            // 更新插件评论数量和评分
            $pluginModel = new PluginModel();
            $pluginModel->updateCommentCount($row['plugin_id']);
            $pluginModel->updateRatingStats($row['plugin_id']);

            // 通知评论者审核结果
            try {
                if (!empty($row['user_id'])) {
                    $plugin = \think\facade\Db::name('plugin')->where('id', $row['plugin_id'])->find();
                    $pluginName = $plugin ? $plugin['name'] : t('plugin_admin.unknown_plugin');
                    $statusMap = [
                        0 => t('plugin_admin.status_pending'),
                        1 => t('plugin_admin.status_approved'),
                        2 => t('plugin_admin.status_rejected'),
                    ];
                    $statusLabel = $statusMap[$status] ?? t('plugin_admin.status_unknown');
                    NotificationModel::add([
                        'user_id'    => intval($row['user_id']),
                        'title'      => t('plugin_admin.comment_audit_title'),
                        'content'    => t('plugin_admin.comment_audit_content', [
                            'plugin' => $pluginName,
                            'status' => $statusLabel,
                        ]),
                        'type'       => 'comment_audit',
                        'link'       => '/UserPlugin/detail.html?id=' . $row['plugin_id'],
                        'variables'  => ['plugin_name' => $pluginName, 'review_status' => $statusLabel],
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function list()
    {
        try {
            $post = request()->post();
            $limit = sf_page_limit($post['limit'] ?? null, 10);
            $current_page = sf_page_number($post['current_page'] ?? null);
            $plugin_id = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;

            $where = [];

            $text = $post['text'] ?? '';
            if (!empty($text)) {
                $text = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string)$text);
                $where[] = ['c.id|c.content', 'like', '%' . $text . '%'];
            }

            $status = $post['status'] ?? '';
            if ($status !== '' && $status !== null) {
                $where[] = ['c.status', '=', intval($status)];
            }

            if ($plugin_id > 0) {
                $where[] = ['c.plugin_id', '=', $plugin_id];
            }

            $list = self::alias('c')
                ->leftJoin('user u', 'c.user_id = u.id')
                ->leftJoin('plugin p', 'c.plugin_id = p.id')
                ->field('c.*, u.username, p.name as plugin_name')
                ->order('c.id', 'desc')
                ->where($where)
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page,
                ]);
            return $list;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
