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
                exit(json_encode(message("未登录！" ,false)));
            }else{
                return redirect((string)url('/login/index'));
            }
        }
        return $next($request);
    }
}