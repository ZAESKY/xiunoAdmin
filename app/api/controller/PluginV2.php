<?php

namespace app\api\controller;

use app\api\service\PluginV2Service;
use app\common\controller\CommonBase;

/**
 * 授权站点使用的只读插件市场接口。
 *
 * 购买、评论和发布仍只存在于授权中心用户端；主题端仅查看和下载免费包。
 */
class PluginV2 extends CommonBase
{
    protected $service;

    public function initialize()
    {
        parent::initialize();
        $this->service = new PluginV2Service();
    }

    public function list()
    {
        return $this->service->listing();
    }

    public function detail()
    {
        return $this->service->detail();
    }

    public function freeTicket()
    {
        return $this->service->freeTicket();
    }

    public function download()
    {
        return $this->service->download();
    }
}
