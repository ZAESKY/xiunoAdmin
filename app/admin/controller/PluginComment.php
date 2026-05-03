<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\PluginCommentService;

/**
 * 插件评论管理控制器
 * @author SF授权系统
 * @since 2026-05-03
 */
class PluginComment extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new PluginCommentService();
    }
}
