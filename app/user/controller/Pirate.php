<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\PirateService;
use think\facade\View;

class Pirate extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new PirateService();
    }
}