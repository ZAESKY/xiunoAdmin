<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PluginService;
use app\admin\model\PluginModel;
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
}
