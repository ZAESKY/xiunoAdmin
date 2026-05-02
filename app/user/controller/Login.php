<?php
namespace app\user\controller;

use app\common\controller\CommonBase;
use app\user\model\ActionLog;
use app\user\service\LoginService;
use think\facade\View;

/**
 * 后台登陆控制器
 *
 * @author 陌上花开
 * @since 2022-01-22
 */
class Login extends CommonBase
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
        //$this->app->view->layout(false);
        View::assign(array(
            'captcha_open' => conf('captcha_open'),
            'captcha_id' => conf('captcha_id'),
        ));
        $get = request()->get();
        $code = isset($get['code'])?$get['code']:'';
        $state = isset($get['state'])?$get['state']:'';
        View::assign('code',$code);
        View::assign('state',$state);
        return $this->render('login/index');

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
        // 清空cookie
        cookie('userId', null);
	cookie('userSign', null);
        // 记录退出日志
        ActionLog::setTitle("系统退出");
        $actionLog = new ActionLog();
        $actionLog->record();
        // 跳转登录页
        return redirect(url('/login/index'));
    }

}