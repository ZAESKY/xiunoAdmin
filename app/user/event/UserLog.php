<?php
namespace app\user\event;

use app\user\model\ActionLog;

/**
 * 行为事件
 *
 * @author 陌上花开
 * @since 2022-01-21
 */
class UserLog
{

    /**
     * 登录时间执行句柄
     * @author 陌上花开
     * @since 2022-01-21
     */
    public function handle()
    {
        if (request()->isPost()) {
            ActionLog::record();
        }
    }
}