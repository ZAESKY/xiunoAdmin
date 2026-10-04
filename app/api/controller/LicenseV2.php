<?php

namespace app\api\controller;

use app\api\service\LicenseV2Service;
use app\common\controller\CommonBase;

/**
 * 授权接口 v2 控制器
 *
 * 刻意继承 CommonBase 而非 Frontend：
 * 授权校验必须在站点维护模式下依然可用，否则维护窗口会把所有客户
 * 推入宽限期甚至降级状态。
 *
 * @since 2026-08-16 P2
 */
class LicenseV2 extends CommonBase
{
    protected $service;

    public function initialize()
    {
        parent::initialize();
        $this->service = new LicenseV2Service();
    }

    public function activate()
    {
        return $this->service->activate();
    }

    public function redeem()
    {
        return $this->service->redeem();
    }

    public function restore()
    {
        return $this->service->restore();
    }

    public function status()
    {
        return $this->service->status();
    }

    public function rebind()
    {
        return $this->service->rebind();
    }

    public function channel()
    {
        return $this->service->channel();
    }

    public function trial()
    {
        return $this->service->trial();
    }

}
