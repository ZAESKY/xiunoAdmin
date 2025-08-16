<?php

namespace app\user\event;

use think\facade\View;

class UserIndex
{

    public function handle()
    {
        View::fetch('main');
    }
}