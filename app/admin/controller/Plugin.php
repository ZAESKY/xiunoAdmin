<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PluginService;
use app\admin\model\PluginModel;
use app\common\service\PluginCommissionService;
use app\common\service\PluginRewardService;
use think\facade\Db;
use think\facade\View;

/**
 * 插件管理控制器
 * @author SF授权系统
 * @since 2026-05-03
 */
class Plugin extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->denyIfClosed();
        $this->service = new PluginService();
        View::assign('pluginRewardConfig', PluginRewardService::config());
    }

    private function denyIfClosed()
    {
        if (!feature_enabled('feature_admin_plugin_enabled')) {
            if (IS_POST) {
                exit(json_encode(message('plugin_action.management_closed', false), JSON_UNESCAPED_UNICODE));
            }
            exit($this->render('/public/error', ['msg' => t('plugin_action.management_closed')]));
        }
    }

    /**
     * 编辑页面
     */
    public function edit()
    {
        if (IS_POST) {
            return json($this->service->edit());
        }
        $id = input('get.id', 0, 'intval');
        $plugin = [];
        if ($id > 0) {
            $pluginModel = new PluginModel();
            $plugin = $pluginModel->getInfo($id);
            if (!$plugin) {
                $plugin = [];
            }
        }
        View::assign('plugin', $plugin);
        View::assign('commissionConfig', PluginCommissionService::config());
        return $this->render();
    }

    /**
     * 上传插件文件
     */
    public function uploadFile()
    {
        if (IS_POST) {
            $file = request()->file('file');
            if (!$file) {
                return json(message('plugin_action.select_plugin_file', false));
            }
            return json($this->service->uploadFile($file));
        }
    }

    /**
     * 上传插件图标、封面
     */
    public function uploadResource()
    {
        if (IS_POST) {
            $file = request()->file('file');
            if (!$file) {
                return json(message('plugin_action.select_resource_file', false));
            }
            return json($this->service->uploadResource($file));
        }
    }

    /**
     * 保存插件（包含文件）
     */
    public function saveWithFile()
    {
        if (IS_POST) {
            $file = request()->file('file');
            if (!$file) {
                return json(message('plugin_ui.upload_package_first', false));
            }
            return json($this->service->saveWithFile($file));
        }
    }

    public function searchRelatedPlugins()
    {
        if (IS_POST) {
            try {
                $keyword = input('post.keyword', '', 'trim');
                $excludeId = input('post.exclude_id', 0, 'intval');
                $pluginModel = new PluginModel();
                return json(message('ok', true, ['list' => $pluginModel->searchRelatedOptions($keyword, $excludeId)]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['list' => []]));
            }
        }
    }

    /**
     * 插件下载记录
     */
    public function downloadRecords()
    {
        if (IS_POST) {
            try {
                $post = $this->request->post();
                $pluginId = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;
                $limit = sf_page_limit($post['limit'] ?? null, 10);
                $currentPage = sf_page_number($post['current_page'] ?? null);
                $query = Db::name('plugin_download')
                    ->alias('d')
                    ->leftJoin('plugin p', 'd.plugin_id = p.id')
                    ->leftJoin('user u', 'd.user_id = u.id')
                    ->field('d.*, p.name as plugin_name, u.username');
                if ($pluginId > 0) {
                    $query->where('d.plugin_id', $pluginId);
                }
                $text = !empty($post['text']) ? trim($post['text']) : '';
                if ($text !== '') {
                    $text = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $text);
                    $query->where('p.name|u.username|d.ip', 'like', '%' . $text . '%');
                }
                $result = $query->order('d.id', 'desc')->paginate([
                    'list_rows' => $limit,
                    'page' => $currentPage,
                ]);
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }
    }

    public function rewardRecords()
    {
        if (IS_POST) {
            try {
                $post = $this->request->post();
                $pluginId = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;
                $limit = sf_page_limit($post['limit'] ?? null, 10);
                $currentPage = sf_page_number($post['current_page'] ?? null);
                $query = Db::name('plugin_reward')
                    ->alias('r')
                    ->leftJoin('user u', 'r.user_id = u.id')
                    ->leftJoin('admin a', 'r.approved_by = a.id')
                    ->field('r.*, u.username, a.username as admin_username');
                if ($pluginId > 0) {
                    $query->where('r.plugin_id', $pluginId);
                }
                $result = $query->order('r.id', 'desc')->paginate([
                    'list_rows' => $limit,
                    'page' => $currentPage,
                ]);
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }
    }

    public function setStatus()
    {
        if (IS_POST) {
            try {
                $result = $this->service->setStatus();
                $reward = is_array($result) ? ($result['reward'] ?? null) : null;
                $msg = t('user.status_change_success');
                if (is_array($reward)) {
                    $rewardText = PluginRewardService::rewardText($reward);
                    if ($rewardText !== '') {
                        $msg .= t('plugin_admin.reward_granted', ['reward' => $rewardText]);
                    } elseif (!empty($reward['reason'])) {
                        $msg .= t('plugin_admin.reward_not_granted', ['reason' => $reward['reason']]);
                    }
                }
                return json(message($msg, true, ['reward' => $reward]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }
    }
}
