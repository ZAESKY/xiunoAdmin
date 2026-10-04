<?php
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
        $this->initSystemConstant($request);

        // 初始化消息中间件RabbitMQ常量
        $this->initRabbitMQ();

        // 初始化数据库常量
        $this->initDbInfo();

        // 定时清理临时上传图片（每小时一次）
        if (!cache('?last_temp_cleanup')) {
            cache('last_temp_cleanup', 1, 3600);
            if (function_exists('clean_temp_uploads')) {
                clean_temp_uploads(86400);
            }
        }

        return $next($request);
    }

    /**
     * 初始化系统常量
     *
     * @author 陌上花开
     * @since 2022-01-21
     */
    public function initSystemConstant($request)
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
        $upload_parh = (new \app\common\service\LocalFilesystemService())->root();
        define('ATTACHMENT_PATH', $upload_parh);
        define('IMG_PATH', ATTACHMENT_PATH . DS . 'images');
        define('UPLOAD_TEMP_PATH', IMG_PATH . DS . '/temp');
        define('PUBLIC_UPLOAD_PATH', PUBLIC_PATH . DS . 'upload');
        define('PUBLIC_UPLOAD_TEMP', PUBLIC_UPLOAD_PATH . DS . 'temp');

        // 系统配置
        define('SITE_NAME', env('system_sitename'));
        define('NICK_NAME', env('system_nickname'));
        define('SYSTEM_VERSION', env('system_version'));

        // 系统域名。生产环境优先使用受信任的 app_host，避免 Host 头
        // 被用于污染支付回调、下载地址等对外绝对 URL。
        $siteUrl = $this->resolveSiteUrl($request);
        $siteParts = parse_url($siteUrl);
        $domain = (string)($siteParts['host'] ?? '');
        if (isset($siteParts['port'])) {
            $domain .= ':' . (int)$siteParts['port'];
        }
        define('IMG_URL', env('domain_img_url'));
        define('DOMAIN', $domain);
        define('SITE_URL', $siteUrl);
    }

    private function resolveSiteUrl($request): string
    {
        $configured = rtrim(trim((string)config('app.app_host')), '/');
        if ($configured !== '') {
            $parts = parse_url($configured);
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            $path = (string)($parts['path'] ?? '');
            if (is_array($parts)
                && in_array($scheme, ['http', 'https'], true)
                && !empty($parts['host'])
                && ($path === '' || $path === '/')
                && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            ) {
                $origin = $scheme . '://' . strtolower((string)$parts['host']);
                if (isset($parts['port'])) {
                    $origin .= ':' . (int)$parts['port'];
                }
                return $origin;
            }
            throw new \RuntimeException(t('system.app_host_invalid'));
        }

        $scheme = $request->isSsl() ? 'https' : 'http';
        $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
        if ($serverName === '' || !preg_match('/^(?:[A-Za-z0-9.-]+|\[[A-Fa-f0-9:]+\])$/D', $serverName)) {
            throw new \RuntimeException(t('system.server_host_invalid'));
        }
        $port = (int)($_SERVER['SERVER_PORT'] ?? ($scheme === 'https' ? 443 : 80));
        $origin = $scheme . '://' . strtolower($serverName);
        if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
            $origin .= ':' . $port;
        }
        return $origin;
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
