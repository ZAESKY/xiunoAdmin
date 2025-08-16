<?php
namespace app\user\controller;

use app\user\service\LogService;
use app\common\controller\UserBackend;

class Log extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new LogService();
    }
}