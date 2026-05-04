<?php
namespace app\user\controller;

use app\common\controller\CommonBase;
use app\user\model\ActionLog;
use app\user\service\LoginService;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Event;
use think\facade\View;
use think\exception\ValidateException;

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
            'login_switch' => conf('login_switch'),
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
     * 注册用户
     */
    public function reg()
    {
        if (IS_POST) {
            try {
                $post = request()->post();
                $appid = !empty($post['appid']) ? intval($post['appid']) : 0;
                $username = !empty($post['username']) ? trim($post['username']) : '';
                $qq = !empty($post['qq']) ? intval($post['qq']) : 0;
                $email = !empty($post['email']) ? trim($post['email']) : '';
                $password = !empty($post['password']) ? $post['password'] : '';
                $confirmPassword = !empty($post['confirmPassword']) ? $post['confirmPassword'] : '';
                $code = !empty($post['code']) ? trim($post['code']) : '';

                if (empty($appid)) return message('请选择注册的应用', false);
                if (empty($username) || strlen($username) < 6) return message('用户名至少6位', false);
                if (empty($password) || strlen($password) < 6) return message('密码至少6位', false);
                if ($password !== $confirmPassword) return message('两次密码不一致', false);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return message('请输入正确的邮箱', false);
                if (empty($code)) return message('请输入验证码', false);

                $cachedCode = Cache::get('reg_code_' . $email);
                if (empty($cachedCode) || (string)$cachedCode !== (string)$code) {
                    return message('验证码错误或已过期', false);
                }
                Cache::delete('reg_code_' . $email);

                $appInfo = Db::name('app')->where(['id' => $appid])->find();
                if (empty($appInfo)) return message('不存在此应用！', false);
                if ($appInfo['status'] != 2) return message('该应用已停止运营或维护中！', false);
                if ($appInfo['register_switch'] != 1) return message('该应用未开放自助注册！', false);

                if (Db::name('user')->where(['username' => $username])->find()) {
                    return message('平台已存在该用户名！', false);
                }

                $powerPriceModel = new \app\admin\model\PowerPriceModel();
                $power = $powerPriceModel->getDefaultPower(intval($appInfo['power_template']));

                Db::name('user')->insert([
                    'username' => $username,
                    'password' => get_password($password),
                    'phone' => '',
                    'qq' => $qq,
                    'email' => $email,
                    'appid' => $appid,
                    'status' => 1,
                    'balance' => 0,
                    'integral' => 0,
                    'power' => $power,
                    'addtime' => datetime(),
                    'userid' => 0,
                ]);

                return message('注册成功！', true);
            } catch (\Exception $e) {
                return message('注册失败：' . $e->getMessage(), false);
            }
        }

        $appList = Db::name('app')->where(['status' => 2, 'register_switch' => 1])->field('id,name')->select()->toArray();
        View::assign('appList', $appList);
        return $this->render('reg');
    }

    /**
     * 找回密码
     */
    public function forgot()
    {
        if (IS_POST) {
            $post = $this->request->post();
            $username = !empty($post['username']) ? $post['username'] : null;
            $email = !empty($post['email']) ? $post['email'] : null;
            $code = !empty($post['code']) ? $post['code'] : null;
            $newPassword = !empty($post['newPassword']) ? $post['newPassword'] : null;

            if (empty($username) || empty($email) || empty($newPassword)) {
                return message('请填写完整信息', false);
            }

            $user = Db::name('user')->where(['username' => $username, 'email' => $email])->find();
            if (empty($user)) {
                return message('用户名与邮箱不匹配', false);
            }

            $cachedCode = Cache::get('reset_code_' . $user['id']);
            if (empty($cachedCode) || (string)$cachedCode !== (string)$code) {
                return message('验证码错误或已过期', false);
            }
            Cache::delete('reset_code_' . $user['id']);

            Db::name('user')->where('id', $user['id'])->update(['password' => get_password($newPassword)]);

            return message('密码重置成功！', true);
        }
        return $this->render('forgot');
    }

    /**
     * 发送注册验证码
     */
    public function sendRegCode()
    {
        if (IS_POST) {
            $email = input('post.email');
            if (empty($email)) return message('请输入邮箱', false);

            $code = sprintf('%06d', mt_rand(0, 999999));
            Cache::set('reg_code_' . $email, $code, 180);

            $param = [
                'to' => $email,
                'title' => conf('title') . ' - 注册验证码',
                'from_name' => conf('title'),
                'content' => '验证码: ' . $code . '（6位数字）。此验证码用于注册账号，请妥善保管。如非本人操作请忽略。'
            ];

            try {
                $result = Event::trigger('ChangeBindingMailNotice', $param);
                return $result[0] ?? message('验证码已发送', true);
            } catch (\Exception $e) {
                return message('邮件发送失败: ' . $e->getMessage(), false);
            }
        }
    }

    /**
     * 发送找回密码验证码
     */
    public function sendResetCode()
    {
        if (IS_POST) {
            $username = input('post.username');
            $email = input('post.email');
            if (empty($username) || empty($email)) return message('请输入用户名和邮箱', false);

            $user = Db::name('user')->where(['username' => $username, 'email' => $email])->find();
            if (empty($user)) {
                return message('用户名与邮箱不匹配', false);
            }

            $code = sprintf('%06d', mt_rand(0, 999999));
            Cache::set('reset_code_' . $user['id'], $code, 180);

            $param = [
                'to' => $email,
                'title' => conf('title') . ' - 找回密码验证码',
                'from_name' => conf('title'),
                'content' => '验证码: ' . $code . '（6位数字）。此验证码用于重置密码，请妥善保管。如非本人操作请忽略。'
            ];

            try {
                $result = Event::trigger('ChangeBindingMailNotice', $param);
                return $result[0] ?? message('验证码已发送', true);
            } catch (\Exception $e) {
                return message('邮件发送失败: ' . $e->getMessage(), false);
            }
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