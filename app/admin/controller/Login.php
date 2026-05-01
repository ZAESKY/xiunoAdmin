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
namespace app\admin\controller;

use app\admin\model\ActionLog;
use app\admin\service\LoginService;
use app\common\controller\Backend;
use think\facade\View;
/**
 * 后台登陆控制器
 *
 * @author 陌上花开
 * @since 2022-01-22
 */

class Login extends Backend
{
    /**
     * 初始化方法
     * @author 陌上花开
     * @since 2022/1/22
     */
    public function initialize()
    {
        parent::initialize();
        $this->service = new LoginService();
    }

    /**
     * 登录首页
     * @return mixed
     * @author 陌上花开
     * @date 2022/1/18
     */
    public function index()
    {
        // 取消模板布局
        if($_SERVER['QUERY_STRING'] == conf('SF_LOGIN_KEY')){
            session('SF_LOGIN_KEY',conf('SF_LOGIN_KEY'));
        }
        $this->app->view->layout(false);
        View::assign(array(
            'captcha_open' => conf('captcha_open'),
            'captcha_id' => conf('captcha_id'),
        ));
        if(session('SF_LOGIN_KEY') != conf('SF_LOGIN_KEY')){
            return $this->render('safe');
        }else{
            $get = $this->request->get();
            $code = isset($get['code'])?$get['code']:'';
            $state = isset($get['state'])?$get['state']:'';
            View::assign('code',$code);
            View::assign('state',$state);
            View::config(['view_path' => config('self_template.login.view_base')]);
            return $this->render('index/index');
        }
    }

    /**
     * 系统登录
     * @return mixed
     * @author 陌上花开
     * @date 2022/1/18
     */
    public function login()
    {
        if (IS_POST) {
            try{
                $result = $this->service->login();
                return $result;
            }catch (\Exception $e){
                return message(t('login.failed').$e->getMessage() ,false);
            }
        }
    }
    /**
     * 检测口令
     * @return mixed
     * @author 陌上花开
     * @date 2022/1/18
     */
    public function checkLoginKey()
    {
        if (IS_POST) {
            $result = $this->service->checkLoginKey();
            return $result;
        }
    }

    /**
     * 退出系统
     * @author 陌上花开
     * @since 2020/6/29
     */
    public function logout()
    {
        // 清空SESSION
        session('adminId', null);
	session('adminSign', null);
        // 记录退出日志
        ActionLog::setTitle("系统退出");
        $actionLog = new ActionLog();
        $actionLog->record();
        // 跳转登录页
        return redirect(url('/login/index'));
    }

}