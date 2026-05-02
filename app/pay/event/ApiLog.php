<?php
namespace app\pay\event;

use app\pay\model\ActionLog;

/**
 * 日志事件
 *
 * @author 陌上花开
 * @since 2022-01-21
 */
class PayLog
{

    /**
     * 日志执行句柄
     *
     * @author 陌上花开
     * @since 2022-01-21
     */
    public function handle()
    {
        if (request()->isGet() || request()->isPost()) {
            ActionLog::record();
        }
    }
}