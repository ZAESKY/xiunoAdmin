<?php
declare (strict_types = 1);
namespace app\user\middleware;

/**
 * 登录中间件
 * @author 陌上花开
 * @since 2022/01/19
 * Class CheckMaintain
 * @package app\middleware
 */
class CheckLogin
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        if (empty(cookie('userId')) && !preg_match('/login/', $request->pathinfo())) {
            if(request()->isPost()){
                exit(json_encode(message(t("login.not_logged_in") ,false)));
            }else{
                $redirect = '';
                $path = '/' . ltrim((string)$request->pathinfo(), '/');
                if (in_array($path, ['/UserPlugin/detail', '/UserPlugin/detail.html'], true)) {
                    $redirect = qh_plugin_detail_redirect(
                        '/UserPlugin/detail.html?id=' . (int)$request->get('id', 0)
                    );
                }
                if ($redirect !== '') {
                    \think\facade\Session::set('user_login_redirect', $redirect);
                    return redirect((string)url('/login/index', ['redirect' => $redirect]));
                }
                return redirect((string)url('/login/index'));
            }
        }
        return $next($request);
    }
}
