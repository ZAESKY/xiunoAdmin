<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PluginService;
use app\admin\model\PluginModel;
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
        $this->service = new PluginService();
    }

    /**
     * 编辑页面
     */
    public function edit()
    {
        if (IS_POST) {
            return $this->service->edit();
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
        View::assign('commissionRate', conf('plugin_commission_rate') ?? 10);
        return $this->render();
    }

    /**
     * 上传插件文件
     */
    public function uploadFile()
    {
        if (IS_POST) {
            return $this->service->uploadFile();
        }
    }

    /**
     * 保存插件（包含文件）
     */
    public function saveWithFile()
    {
        if (IS_POST) {
            return $this->service->saveWithFile();
        }
    }

    public function searchRelatedPlugins()
    {
        if (IS_POST) {
            try {
                $keyword = input('post.keyword', '', 'trim');
                $excludeId = input('post.exclude_id', 0, 'intval');
                $pluginModel = new PluginModel();
                return message('ok', true, ['list' => $pluginModel->searchRelatedOptions($keyword, $excludeId)]);
            } catch (\Exception $e) {
                return message($e->getMessage(), false, ['list' => []]);
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
                $limit = !empty($post['limit']) ? intval($post['limit']) : 10;
                $currentPage = !empty($post['current_page']) ? intval($post['current_page']) : 1;
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
                return message(t('common.list_success'), true, ['data' => $result]);
            } catch (\Exception $e) {
                return message($e->getMessage(), false, ['data' => []]);
            }
        }
    }
}
