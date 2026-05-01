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