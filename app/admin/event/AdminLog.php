<?php
namespace app\admin\event;

use app\admin\model\ActionLog;

/**
 * 登录事件
 *
 * @author 陌上花开
 * @since 2022-01-21
 */
class AdminLog
{

    /**
     * 登录时间执行句柄
     *
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