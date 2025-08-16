<?php

namespace app\user\event;
use app\user\model\ActionLog as Action;

class ActionLog
{
    public function handle($content)
    {
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