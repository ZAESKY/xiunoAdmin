<?php

namespace app\api\controller;

use app\api\service\UpdateV2Service;
use app\common\controller\CommonBase;

/**
 * 更新接口 v2 控制器
 *
 * 同样继承 CommonBase：更新查询不应被站点维护模式阻断。
 *
 * @since 2026-08-16 P2/P3
 */
class UpdateV2 extends CommonBase
{
    protected $service;

    public function initialize()
    {
        parent::initialize();
        $this->service = new UpdateV2Service();
    }

    public function check()
    {
        return $this->service->check();
    }

    public function stable()
    {
        return $this->service->stable();
    }

    public function ticket()
    {
        return $this->service->ticket();
    }

    public function download()
    {
        return $this->service->download();
    }

    public function report()
    {
        return $this->service->report();
    }

    public function retiredPatch()
    {
        return json([
            'code' => 'PROGRAM_PATCH_RETIRED',
            'message' => '独立程序补丁通道已下线，请通过主题版本更新获取 Xiuno 文件变更',
        ], 410);
    }

}
