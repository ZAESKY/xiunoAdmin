<?php
declare (strict_types = 1);

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\UserService;

class User extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new UserService();
    }
}