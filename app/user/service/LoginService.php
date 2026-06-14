<?php
namespace app\user\service;

use app\user\model\User;
use app\common\service\UserBaseService;
use app\api\lib\GeetestLib;

/**
 * 系统登录服务
 *
 * @author 陌上花开
 * @since 2020-04-21
 */
class LoginService extends UserBaseService
{

    /**
     * 构造函数
     * LoginService constructor.
     */
    public function __construct(){
        $this->model = new User();
    }

    /**
     * 系统登录
     * @return array
     * @author 陌上花开
     * @since 2020/7/11
     */
    public function login()
    {
        $param = request()->param();
        if(!$param){
            return message(t('validation.not_empty'), false);
        }
        if (conf('captcha_open') == 1) {
            $captcha_id = conf('captcha_id');
            $captcha_key = conf('captcha_key');
            $api_server = 'http://gcaptcha4.geetest.com';
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
            if ($res !== false) {
                $obj = json_decode($res,true);
                if (!is_array($obj)) {
                    return message(t('login.captcha_error'), false);
                }
                if (isset($obj['result']) && in_array($obj['result'], ['error', 'fail'])) {
                    return message($obj['reason'] ?? t('login.captcha_error'), false);
                }
            }
        }
        // 登录用户名
        $username = $param['username'] ?? '';
        if (!$username) {
            return message(t('login.username_empty'), false, 'username');
        }
        // 登录密码
        $password = $param['password'] ?? '';
        if (!$password) {
            return message(t('login.password_empty'), false, 'password');
        }
        // 用户验证
        $info = $this->model->getOne($username);
        if (!$info) {
            return message(t('login.username_not_exist'), false, 'username');
        }
        // 密码校验
        if (get_password($password) != $info['password']) {
            $content = [
                'Title' => '登录后台',
                '结果' => '登陆失败[账号密码错误]',
                'Result' => 'success'
            ];
            event('UserLogin', $content);
            return message(t('login.password_incorrect'), false, 'password');
        }

        // 使用状态校验
        if ($info['status'] != 1) {
            $content = [
                'Title' => '登录后台',
                '结果' => '登陆失败[账号已被禁用]',
                'Result' => 'success'
            ];
            event('UserLogin', $content);
            return message(t('login.account_disabled'), false);
        }
        if(!empty($info['ip'])) {
            if (!in_array(get_client_ip(), unserialize($info['ip']))) {
                $content = [
                    'Title' => '登录后台',
                    '结果' => '登陆失败[IP不在白名单]',
                    'Result' => 'success'
                ];
                event('UserLogin', $content);
                return message(t('user.ip_not_whitelist_login'), false);
            }
        }
        // 本地cookie存储登录信息
        cookie('userId', $info['id']);
        cookie('userSign',data_auth_sign($info['appid'].$info['username'].$info['password'].sf_password_hash()));

        $content = [
            'Title' => '登录后台',
            '结果' => '登录成功',
            'Result' => 'success'
        ];
        event('UserLogin', $content);
        return message(t('login.success').' '.$username.', '.t('common.home').t('common.back').'~', true);
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
}
