<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PluginCommentService;

/**
 * 插件评论管理控制器
 * @author QH授权系统
 * @since 2026-05-03
 */
class PluginComment extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->denyIfClosed();
        $this->service = new PluginCommentService();
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
}
