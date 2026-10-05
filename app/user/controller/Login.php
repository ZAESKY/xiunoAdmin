<?php
namespace app\user\controller;

use app\common\controller\CommonBase;
use app\user\model\ActionLog;
use app\user\service\LoginService;
use app\common\service\RateLimitService;
use app\common\service\PasswordRecoveryService;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Event;
use think\facade\Log;
use think\facade\Session;
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
        $loginSwitch = conf('login_switch');
        if (!is_array($loginSwitch)) {
            $loginSwitch = array_filter(explode(',', (string)$loginSwitch));
        }
        // 旧扫码仅保留给一次性历史身份迁移，不再作为登录入口展示。
        $loginSwitch = array_values(array_intersect($loginSwitch, ['qq']));
        $loginRedirect = qh_plugin_detail_redirect((string)request()->get('redirect', ''));
        if ($loginRedirect !== '') {
            Session::set('user_login_redirect', $loginRedirect);
        } else {
            $loginRedirect = qh_plugin_detail_redirect((string)Session::get('user_login_redirect', ''));
        }
        View::assign(array(
            'captcha_open' => conf('captcha_open'),
            'captcha_id' => conf('captcha_id'),
            'login_switch' => $loginSwitch,
            'login_redirect_json' => json_encode(
                $loginRedirect,
                JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?: '""',
        ));
        $get = request()->get();
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
            'userType' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        View::assign('code',$code);
        View::assign('state',$state);
        View::assign('oauth_error',$oauthError);
        View::assign('oauth_callback_json', $oauthCallbackJson ?: '{}');
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
        if (IS_POST) {
            return message('login.qq_registration_only', false);
        }
        $loginSwitch = conf('login_switch');
        if (!is_array($loginSwitch)) {
            $loginSwitch = array_filter(array_map('trim', explode(',', (string)$loginSwitch)));
        }
        View::assign('qq_register_enabled', in_array('qq', $loginSwitch, true));
        return $this->render('reg');
    }

    /**
     * 找回密码
     */
    public function forgot()
    {
        if (IS_POST) {
            $post = $this->request->post();
            return PasswordRecoveryService::reset(
                (string)($post['username'] ?? ''),
                (string)($post['contact'] ?? $post['email'] ?? $post['phone'] ?? ''),
                (string)($post['code'] ?? ''),
                (string)($post['newPassword'] ?? '')
            );
        }
        $channel = PasswordRecoveryService::channel();
        View::assign([
            'recovery_channel' => $channel,
            'recovery_is_sms' => $channel === 'sms',
        ]);
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
            return PasswordRecoveryService::send(
                trim((string)input('post.username')),
                (string)(input('post.contact') ?: input('post.email') ?: input('post.phone'))
            );
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
            $results = Event::trigger('ChangeBindingMailNotice', $param);
            $result = $results[0] ?? null;
            if (is_string($result)) {
                $decoded = json_decode($result, true);
                $result = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($result) || (int)($result['code'] ?? -1) !== 0) {
                Log::warning('Mail verification code dispatch failed', [
                    'purpose' => str_starts_with($cacheKey, 'reset_code_') ? 'reset' : 'registration',
                    'provider_response' => is_array($result) ? ($result['msg'] ?? 'invalid response') : 'invalid response',
                ]);
                return message('login.email_send_failed', false);
            }

            Cache::set($cacheKey, $code, 180);
            Cache::delete($cacheKey . ':attempts');
            return message('login.code_sent', true);
        } catch (\Throwable $e) {
            Log::error('Mail verification code dispatch exception: ' . $e->getMessage(), ['exception' => $e]);
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
