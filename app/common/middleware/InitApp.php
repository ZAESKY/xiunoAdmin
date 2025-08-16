<?php
/*
* +----------------------------------------------------------------------
* | SF 综合验证授权系统
* +----------------------------------------------------------------------
* | Quotes [ 花开的再灿烂，也有凋谢的一天，致我们过去的青春 ]
* +----------------------------------------------------------------------
* | Author: 陌上花开 <2129876388@qq.com>
* +----------------------------------------------------------------------
* | Date: 2022年1月19日 18:48:32
* +----------------------------------------------------------------------
*/

namespace app\common\middleware;

use Closure;

/**
 * 应用初始化
 *
 * @author 陌上花开
 * @since 2022-01-21
 */
class InitApp
{

    /**
     * 指定句柄
     *
     * @author 陌上花开
     * @since 2022-01-21
     */
    public function handle($request, Closure $next)
    {
        // 初始化系统常量
        $this->initSystemConstant();

        // 初始化消息中间件RabbitMQ常量
        $this->initRabbitMQ();

        // 初始化数据库常量
        $this->initDbInfo();
        return $next($request);
    }

    /**
     * 初始化系统常量
     *
     * @author 陌上花开
     * @since 2022-01-21
     */
    public function initSystemConstant()
    {
        // 基础常量
        define('ROOT_PATH', app()->getRootPath());
        define('DS', DIRECTORY_SEPARATOR);
        define('ADDONS_PATH', ROOT_PATH . 'addons');
        define('APP_PATH', ROOT_PATH . 'app');
        define('ROUTE_PATH', ROOT_PATH . 'route');
        define('RUNTIME_PATH', ROOT_PATH . 'runtime');
        define('EXTEND_PATH', ROOT_PATH . 'extend');
        define('VENDOR_PATH', ROOT_PATH . 'vendor');
        define('PUBLIC_PATH', ROOT_PATH . 'public');
        define('HOME_TEMPLATE_PATH', PUBLIC_PATH . DS . 'template' . DS. 'modules' . DS . 'home');
        define('LOGIN_TEMPLATE_PATH', PUBLIC_PATH . DS . 'template' . DS. 'modules' . DS . 'login');
        define('MAINTAIN_TEMPLATE_PATH', PUBLIC_PATH . DS . 'template' . DS. 'modules' . DS . 'maintain');
        define('NOTICE_TEMPLATE_PATH', PUBLIC_PATH . DS . 'template' . DS. 'modules' . DS . 'notice');

        // 附件常量
        // 文件上传路径
        $upload_parh = \think\facade\Filesystem::getDiskConfig(config('filesystem.default'), 'root');
        define('ATTACHMENT_PATH', $upload_parh);
        define('IMG_PATH', ATTACHMENT_PATH . DS . 'images');
        define('UPLOAD_TEMP_PATH', IMG_PATH . DS . '/temp');

        // 系统配置
        define('SITE_NAME', env('system.sitename'));
        define('NICK_NAME', env('system.nickname'));
        define('SYSTEM_VERSION', env('system.version'));

        // 系统域名
        define('IMG_URL', env('domain.img_url'));
        define('DOMAIN', getenv('HTTP_HOST'));
        define('SITE_URL', (getenv('SERVER_PORT') == '443' ? 'https://' : 'http://').DOMAIN);
    }

    /**
     * 初始化RabbitMQ
     *
     * @author 陌上花开
     * @since 2022-01-21
     */
    public function initRabbitMQ()
    {

    }

    /**
     * 初始化数据库常量
     *
     * @author 陌上花开
     * @since 2022-01-21
     */
    public function initDbInfo()
    {
        // 数据表前缀
        define('DB_PREFIX', config('database.connections.mysql.prefix'));
    }

}