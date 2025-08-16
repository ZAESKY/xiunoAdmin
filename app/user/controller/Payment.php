<?php
namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\PaymentService;

class Payment extends UserBackend
{
    public function initialize(){
        parent::initialize();
        $this->service = new PaymentService();
    }
}