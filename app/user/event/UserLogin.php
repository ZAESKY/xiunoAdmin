<?php

namespace app\user\event;

use app\user\model\ActionLog as Action;
use app\user\model\User;

class UserLogin
{
    public function handle($content)
    {
        $user = new User();
        $user->setOne(['lasttime' => datetime()]);
        if(!empty($content['Title'])){
            Action::setTitle($content['Title']);
            unset($content['Title']);
        }
        if($content['Result'] != 'success'){
            Action::setResult(0);
        }
        Action::setContent(json_encode($content));
    }
}