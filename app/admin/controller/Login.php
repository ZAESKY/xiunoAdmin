<?php
namespace app\admin\controller;

use app\admin\model\ActionLog;
use app\admin\service\LoginService;
use app\common\controller\Backend;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Event;
use think\facade\Log;
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
     * 登录控制器不需要登录态，覆写父类校验
     */
    public function initLogin()
    {
        $this->adminId = 0;
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
        if(($this->request->server('QUERY_STRING', '') ?: '') == conf('SF_LOGIN_KEY')){
            session('SF_LOGIN_KEY',conf('SF_LOGIN_KEY'));
        }
        $this->app->view->layout(false);
        $loginSwitch = conf('login_switch');
        if (!is_array($loginSwitch)) {
            $loginSwitch = array_filter(explode(',', (string)$loginSwitch));
        }
        // 管理员登录统一使用 QQ 互联，旧网页扫码入口不再展示。
        $loginSwitch = array_values(array_intersect($loginSwitch, ['qq']));

        View::assign(array(
            'captcha_open' => conf('captcha_open'),
            'captcha_id' => conf('captcha_id'),
            'login_switch' => $loginSwitch,
        ));
        if(session('SF_LOGIN_KEY') != conf('SF_LOGIN_KEY')){
            return $this->render('safe');
        }else{
            $get = $this->request->get();
            $code = trim((string)($get['code'] ?? ''));
            $state = trim((string)($get['state'] ?? ''));
            $oauthError = trim((string)($get['error'] ?? ''));
            if (strlen($code) > 2048) {
                $code = '';
            }
            if (!preg_match('/^[a-f0-9]{64}$/D', $state)) {
                $state = '';
            }
            if (strlen($oauthError) > 128) {
                $oauthError = '';
            }
            $oauthCallbackJson = json_encode([
                'code' => $code,
                'state' => $state,
                'oauth_error' => $oauthError,
                'userType' => 'admin',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            View::assign('code',$code);
            View::assign('state',$state);
            View::assign('oauth_error',$oauthError);
            View::assign('oauth_callback_json', $oauthCallbackJson ?: '{}');
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
                return json($result);
            }catch (\Throwable $e){
                return json(message(t('login.failed').$e->getMessage() ,false));
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
            return json($result);
        }
    }

    /**
     * 注册用户
     */
    public function appNotice()
    {
        if (!IS_POST) {
            return message('common.illegal_request', false);
        }
        $appid = intval(input('post.appid'));
        if ($appid <= 0) {
            return message('login.select_registration_app', false);
        }
        $appInfo = Db::name('app')->where([
            'id' => $appid,
            'status' => 2,
            'register_switch' => 1,
        ])->field('register_notice')->find();
        if (!$appInfo) {
            return message('login.registration_closed', false);
        }
        return message('success', true, [
            'register_notice' => clean_rich_text($appInfo['register_notice'] ?? ''),
        ]);
    }

    public function reg()
    {
        $this->app->view->layout(false);
        if (session('SF_LOGIN_KEY') != conf('SF_LOGIN_KEY')) {
            return $this->render('safe');
        }
        if (IS_POST) {
            try {
                $post = request()->post();
                $appid = !empty($post['appid']) ? intval($post['appid']) : 0;
                $username = !empty($post['username']) ? trim($post['username']) : '';
                $qq = !empty($post['qq']) ? trim((string)$post['qq']) : null;
                $email = !empty($post['email']) ? strtolower(trim((string)$post['email'])) : '';
                $password = !empty($post['password']) ? $post['password'] : '';
                $confirmPassword = !empty($post['confirmPassword']) ? $post['confirmPassword'] : '';
                $code = !empty($post['code']) ? trim($post['code']) : '';

                if (empty($appid)) return message('login.select_registration_app', false);
                if (!preg_match('/^[\p{L}\p{N}_.-]{6,64}$/u', $username)) return message('login.registration_username_format', false);
                if (empty($password) || strlen($password) < 6) return message('login.password_min_length', false);
                if ($password !== $confirmPassword) return message('login.password_mismatch', false);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return message('login.valid_email_required', false);
                if (empty($code)) return message('login.verification_code_required', false);
                if ($qq !== null && !preg_match('/^[1-9][0-9]{4,11}$/', $qq)) return message('login.valid_qq_required', false);

                $codeCacheKey = $this->mailCodeCacheKey('reg', $email);
                $attemptCacheKey = $codeCacheKey . ':attempts';
                $attempts = (int)Cache::get($attemptCacheKey, 0);
                if ($attempts >= 5) {
                    Cache::delete($codeCacheKey);
                    return message('login.code_attempts_exceeded', false);
                }
                Cache::set($attemptCacheKey, $attempts + 1, 300);
                $cachedCode = (string)Cache::get($codeCacheKey, '');
                if ($cachedCode === '' || !hash_equals($cachedCode, (string)$code)) {
                    return message('login.code_invalid_or_expired', false);
                }

                $appInfo = Db::name('app')->where(['id' => $appid])->find();
                if (empty($appInfo)) return message('login.app_not_found', false);
                if ($appInfo['status'] != 2) return message('login.app_unavailable', false);
                if ($appInfo['register_switch'] != 1) return message('login.self_registration_closed', false);

                if (Db::name('user')->where(['username' => $username])->find()) {
                    return message('login.username_exists', false);
                }
                if ($qq !== null && Db::name('user')->where('qq', $qq)->find()) {
                    return message('login.qq_already_bound', false);
                }

                $powerPriceModel = new \app\admin\model\PowerPriceModel();
                $power = $powerPriceModel->getDefaultPower(intval($appInfo['power_template']));

                Db::name('user')->insert([
                    'username' => $username,
                    'password' => sf_password_make($password),
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

                Cache::delete($codeCacheKey);
                Cache::delete($attemptCacheKey);
                return message('login.registration_success', true);
            } catch (\Throwable $e) {
                Log::error('Admin entry user registration failed: ' . $e->getMessage(), ['exception' => $e]);
                return message('login.registration_failed', false);
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
        $this->app->view->layout(false);
        if (session('SF_LOGIN_KEY') != conf('SF_LOGIN_KEY')) {
            return $this->render('safe');
        }
        if (IS_POST) {
            $post = $this->request->post();
            $username = !empty($post['username']) ? $post['username'] : null;
            $email = !empty($post['email']) ? strtolower(trim((string)$post['email'])) : null;
            $code = !empty($post['code']) ? $post['code'] : null;
            $newPassword = !empty($post['newPassword']) ? $post['newPassword'] : null;

            if (empty($username) || empty($email) || empty($newPassword)) {
                return message('login.complete_info_required', false);
            }

            $user = Db::name('user')->where(['username' => $username, 'email' => $email])->find();
            if (empty($user)) {
                return message('login.username_email_mismatch', false);
            }

            $codeCacheKey = 'reset_code_' . intval($user['id']);
            $attemptCacheKey = $codeCacheKey . ':attempts';
            $attempts = (int)Cache::get($attemptCacheKey, 0);
            if ($attempts >= 5) {
                Cache::delete($codeCacheKey);
                return message('login.code_attempts_exceeded', false);
            }
            Cache::set($attemptCacheKey, $attempts + 1, 300);
            $cachedCode = (string)Cache::get($codeCacheKey, '');
            if ($cachedCode === '' || !hash_equals($cachedCode, (string)$code)) {
                return message('login.code_invalid_or_expired', false);
            }

            Db::name('user')->where('id', $user['id'])->update(['password' => sf_password_make($newPassword)]);
            Cache::delete($codeCacheKey);
            Cache::delete($attemptCacheKey);

            return message('login.password_reset_success', true);
        }
        return $this->render('forgot');
    }

    /**
     * 发送注册验证码
     */
    public function sendRegCode()
    {
        if (IS_POST) {
            $email = strtolower(trim((string)input('post.email')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return message('login.valid_email_required', false);
            if ($limited = $this->mailRateLimit('reg', $email)) return $limited;

            $code = sprintf('%06d', random_int(0, 999999));

            $param = [
                'to' => $email,
                'title' => conf('title') . ' - ' . t('mail.register_code_subject'),
                'from_name' => conf('title'),
                'content' => t('mail.register_code_body', ['code' => $code])
            ];

            return $this->dispatchMailCode($param, $this->mailCodeCacheKey('reg', $email), $code);
        }
    }

    /**
     * 发送找回密码验证码
     */
    public function sendResetCode()
    {
        if (IS_POST) {
            $username = trim((string)input('post.username'));
            $email = strtolower(trim((string)input('post.email')));
            if (empty($username) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return message('login.username_email_required', false);
            if ($limited = $this->mailRateLimit('reset', $email)) return $limited;

            $user = Db::name('user')->where(['username' => $username, 'email' => $email])->find();
            if (empty($user)) {
                return message('login.code_sent_if_matched', true);
            }

            $code = sprintf('%06d', random_int(0, 999999));

            $param = [
                'to' => $email,
                'title' => conf('title') . ' - ' . t('mail.password_reset_code_subject'),
                'from_name' => conf('title'),
                'content' => t('mail.password_reset_code_body', ['code' => $code])
            ];

            return $this->dispatchMailCode($param, 'reset_code_' . intval($user['id']), $code);
        }
    }

    private function mailCodeCacheKey(string $purpose, string $email): string
    {
        return $purpose . '_code_' . hash('sha256', strtolower(trim($email)));
    }

    private function mailRateLimit(string $purpose, string $email): ?array
    {
        $recipientHash = hash('sha256', strtolower(trim($email)));
        $cooldownKey = 'mail_code_cooldown:' . $purpose . ':' . $recipientHash;
        if (Cache::get($cooldownKey)) {
            return message('login.send_too_frequent', false);
        }

        $ipHash = hash('sha256', (string)get_client_ip());
        $hourKey = 'mail_code_hour:' . $purpose . ':' . $ipHash . ':' . date('YmdH');
        $hourCount = (int)Cache::get($hourKey, 0);
        if ($hourCount >= 20) {
            return message('login.network_send_limited', false);
        }

        Cache::set($cooldownKey, 1, 60);
        Cache::set($hourKey, $hourCount + 1, 3600);
        return null;
    }

    private function dispatchMailCode(array $param, string $cacheKey, string $code): array
    {
        try {
            $result = Event::trigger('ChangeBindingMailNotice', $param)[0] ?? null;
            if (is_string($result)) {
                $decoded = json_decode($result, true);
                $result = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($result) || intval($result['code'] ?? -1) !== 0) {
                Log::warning('Admin entry mail verification dispatch failed', [
                    'purpose' => str_starts_with($cacheKey, 'reset_code_') ? 'reset' : 'registration',
                ]);
                return message('login.email_send_failed', false);
            }

            Cache::set($cacheKey, $code, 180);
            Cache::delete($cacheKey . ':attempts');
            return message('login.code_sent', true);
        } catch (\Throwable $e) {
            Log::error('Admin entry mail verification dispatch exception: ' . $e->getMessage(), ['exception' => $e]);
            return message('login.email_send_failed', false);
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
