<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PluginOrderService;

/**
 * 插件订单管理控制器
 * @author SF授权系统
 * @since 2026-05-03
 */
class PluginOrder extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->denyIfClosed();
        $this->service = new PluginOrderService();
    }

    private function denyIfClosed()
    {
        if (!feature_enabled('feature_admin_plugin_enabled')) {
            if (IS_POST) {
                exit(json_encode(message('插件管理功能已关闭', false), JSON_UNESCAPED_UNICODE));
            }
            exit($this->render('/public/error', ['msg' => '插件管理功能已关闭']));
        }
    }
}
