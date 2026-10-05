<?php
declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;

/**
 * Disable the installer as soon as the installation lock exists.
 *
 * This guard lives at application-middleware level so every install route is
 * protected, including controllers that do not extend the Install base class.
 */
class InstallLock
{
    public function handle(Request $request, Closure $next)
    {
        $lockFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'QH_Auth.Lock';
        if (!is_file($lockFile)) {
            return $next($request);
        }

        return json(message('系统已完成安装，安装入口已关闭', false), 403);
    }
}
