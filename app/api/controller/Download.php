<?php

namespace app\api\controller;

use app\api\service\DownloadService;
use app\common\controller\Frontend;

class Download extends Frontend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new DownloadService();
    }

    public function download(){
        return $this->service->download();
    }
}