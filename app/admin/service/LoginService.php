<?php
namespace app\admin\service;

use app\admin\model\ActionLog;
use app\admin\model\Admin;
use app\common\service\BaseService;
use think\facade\Cache;
use think\facade\Session;

/**
 * 系统登录服务
 *
 * @author 陌上花开
 * @since 2020-04-21
 */
class LoginService extends BaseService
{

    /**
     * 构造函数
     * LoginService constructor.
     */
    public function __construct()
    {
        $this->model = new Admin();
    }

    /**
     * 系统登录
     * @return array
     * @author 陌上花开
     * @since 2020/7/11
     */
    public function login()
    {
        // 参数
        if (!hash_equals((string)conf('QH_LOGIN_KEY'), (string)session('QH_LOGIN_KEY'))) {
            return message(t('login.token_empty'), false);
        }
        $param = request()->param();
        if(!$param){
            return message(t('validation.not_empty'), false);
        }
        if (conf('captcha_open') == 1) {
            $captcha_id = conf('captcha_id');
            $captcha_key = conf('captcha_key');
            $api_server = 'https://gcaptcha4.geetest.com';
            $lot_number = $param['lot_number']??null;
            $captcha_output = $param['captcha_output']??null;
            $pass_token = $param['pass_token']??null;
            $gen_time = $param['gen_time']??null;
            if (!$lot_number || !$captcha_output || !$pass_token || !$gen_time || !$captcha_key) {
                return message(t('login.captcha_error'), false);
            }
            $sign_token = hash_hmac('sha256', $lot_number, $captcha_key);
            $query = array(
                'lot_number' => $lot_number,
                'captcha_output' => $captcha_output,
                'pass_token' => $pass_token,
                'gen_time' => $gen_time,
                'sign_token' => $sign_token
            );
            $url = sprintf($api_server . '/validate' . '?captcha_id=%s', $captcha_id);
            $res = $this->post_request($url,$query);
            if ($res === false) {
                return message(t('login.captcha_error'), false);
            }
            $obj = json_decode($res,true);
            if (!is_array($obj)) {
                return message(t('login.captcha_error'), false);
            }
            if (isset($obj['result']) && in_array($obj['result'], ['error', 'fail'], true)) {
                return message($obj['reason'] ?? t('login.captcha_error'), false);
            }
        }
        ActionLog::setTitle(t('login.login_backend'));
        // 登录用户名
        $username = $param['username'] ?? '';
        if (!$username) {
            return message(t('login.username_empty'), false, 'username');
        }
        $rateKeys = $this->loginRateKeys((string)$username);
        if ($this->loginRateLimited($rateKeys)) {
            return message('login.too_many_attempts', false);
        }
        // 登录密码
        $password = $param['password'] ?? '';
        if (!$password) {
            return message(t('login.password_empty'), false, 'password');
        }
        // 用户验证
        $info = $this->model->getOne($username);
        if (!$info) {
            $this->recordLoginFailure($rateKeys);
            return message(t('login.username_not_exist'), false, 'username');
        }
        // 密码校验：兼容旧生产的明文/双 MD5，并在成功登录后升级为现代哈希。
        $needsRehash = false;
        if (!qh_password_verify($password, $info['password'], $needsRehash)) {
            $this->recordLoginFailure($rateKeys);
            ActionLog::setContent("账号密码错误|用户名:".$username."|IP:".get_client_ip());
            return message(t('login.password_incorrect'), false, "password");
        }

        // 使用状态校验
        if ($info['status'] != 1) {
            $this->recordLoginFailure($rateKeys);
            return message(t('login.account_disabled'), false);
        }

        if ($needsRehash) {
            $newHash = qh_password_make($password);
            $this->model->where('id', $info['id'])->update(['password' => $newHash]);
            $info['password'] = $newHash;
        }

        // 本地SESSION存储登录信息
        Session::regenerate(true);
        session('adminId', $info['id'], 86400);
        session('adminSign', data_auth_sign($info['username'].$info['password'].qh_password_hash()), 86400);
        Cache::delete($rateKeys['account']);

        ActionLog::setContent("登录成功|用户名:".$username."|IP:".get_client_ip());
        return message(t('login.success'), true);
    }
    /**
     * 系统登录
     * @return array
     * @author 陌上花开
     * @since 2020/7/11
     */
    public function checkLoginKey()
    {
        ActionLog::setTitle(t('login.verify_token_title'));
        // 参数
        $param = request()->param();
        if(!$param){
            return message(t('validation.not_empty'), false);
        }
        // 使用状态校验
        if (empty($param['QH_LOGIN_KEY'])) {
            return message(t('login.token_empty'), false);
        }

        $rateKey = 'login:admin:key:' . hash('sha256', get_client_ip());
        if ((int)Cache::get($rateKey, 0) >= 10) {
            return message('login.token_too_many_attempts', false);
        }

        if (!hash_equals((string)conf('QH_LOGIN_KEY'), (string)$param['QH_LOGIN_KEY'])) {
            Cache::set($rateKey, (int)Cache::get($rateKey, 0) + 1, 600);
            ActionLog::setContent("口令输入错误|IP:".get_client_ip());
            return message(t('login.token_incorrect'), false);
        }
        // 本地SESSION存储登录口令信息
        Session::regenerate(true);
        session('QH_LOGIN_KEY', conf('QH_LOGIN_KEY'));
        Cache::delete($rateKey);
        ActionLog::setContent("口令输入正确|IP:".get_client_ip());
        return message(t('login.verify_success'), true);
    }

    private function post_request($url, $postdata) {
        $data = http_build_query($postdata);
        $options    = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'content' => $data,
                'timeout' => 5,
                'ignore_errors' => true
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        $status = isset($http_response_header[0]) ? $http_response_header[0] : '';
        if ($result === false || strpos($status, '200') === false) {
            return false;
        }
        return $result;
    }

    private function loginRateKeys(string $username): array
    {
        $ip = get_client_ip();
        return [
            'ip' => 'login:admin:ip:' . hash('sha256', $ip),
            'account' => 'login:admin:account:' . hash('sha256', strtolower(trim($username)) . '|' . $ip),
        ];
    }

    private function loginRateLimited(array $keys): bool
    {
        return (int)Cache::get($keys['ip'], 0) >= 50
            || (int)Cache::get($keys['account'], 0) >= 10;
    }

    private function recordLoginFailure(array $keys): void
    {
        Cache::set($keys['ip'], (int)Cache::get($keys['ip'], 0) + 1, 600);
        Cache::set($keys['account'], (int)Cache::get($keys['account'], 0) + 1, 600);
    }
}
