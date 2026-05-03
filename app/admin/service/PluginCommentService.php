<?php

namespace app\admin\service;

use app\admin\model\PluginCommentModel;
use app\common\service\BaseService;

class PluginCommentService extends BaseService
{
    public function __construct()
    {
        $this->model = new PluginCommentModel();
    }
}
