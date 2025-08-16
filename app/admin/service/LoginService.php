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

namespace app\admin\service;

use app\admin\model\ActionLog;
use app\admin\model\Admin;
use app\common\service\BaseService;
use app\api\lib\GeetestLib;

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
        if(session('SF_LOGIN_KEY') != conf('SF_LOGIN_KEY')) {
            return message('请返回登录口令界面输入口令后在进行登录！', false);
        }
        $param = request()->param();
        if(!$param){
            return message('请勿留空！', false);
        }
        if (conf('captcha_open') == 1) {
            $captcha_id = conf('captcha_id');
            $captcha_key = conf('captcha_key');
            $api_server = 'http://gcaptcha4.geetest.com';
            $lot_number = $param['lot_number']??null;
            $captcha_output = $param['captcha_output']??null;
            $pass_token = $param['pass_token']??null;
            $gen_time = $param['gen_time']??null;
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
            $obj = json_decode($res,true);
            if($obj['result'] == 'error'){
                return message($obj['reason'], false);
            }
        }
        ActionLog::setTitle("登录后台");
        // 登录用户名
        $username = $param['username'];
        if (!$username) {
            return message('登录用户名不能为空', false, 'username');
        }
        // 登录密码
        $password = $param['password'];
        if (!$password) {
            return message('登录密码不能为空', false, 'password');
        }
        // 用户验证
        $info = $this->model->getOne($username);
        if (!$info) {
            return message('您的登录用户名不存在', false, 'username');
        }
        // 密码校验
        if ($password != $info['password']) {
            ActionLog::setContent("账号密码错误|用户名:".$username."|IP:".get_client_ip());
            return message("您的登录密码不正确", false, "password");
        }

        // 使用状态校验
        if ($info['status'] != 1) {
            return message("您的帐号已被禁用", false);
        }

        // 本地SESSION存储登录信息
        session('adminId', $info['id'], 86400);
        session('adminSign', data_auth_sign($info['username'].$info['password'].sf_password_hash()), 86400);

        ActionLog::setContent("登录成功|用户名:".$username."|IP:".get_client_ip());
        return message('尊敬的'.$username.', 欢迎回来~', true);
    }
    /**
     * 系统登录
     * @return array
     * @author 陌上花开
     * @since 2020/7/11
     */
    public function checkLoginKey()
    {
        ActionLog::setTitle("登录口令");
        // 参数
        $param = request()->param();
        if(!$param){
            return message('请勿留空！', false);
        }
        // 使用状态校验
        if (empty($param['SF_LOGIN_KEY'])) {
            return message("请填写登录口令！", false);
        }
        
        if ($param['SF_LOGIN_KEY'] != conf('SF_LOGIN_KEY')) {
            ActionLog::setContent("口令输入错误|IP:".get_client_ip());
            return message("登录口令不正确！", false);
        }
        // 本地SESSION存储登录口令信息
        session('SF_LOGIN_KEY', conf('SF_LOGIN_KEY'));
        ActionLog::setContent("口令输入正确|IP:".get_client_ip());
        return message('验证成功！', true);
    }

    private function post_request($url, $postdata) {
        $data = http_build_query($postdata);
        $options    = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'content' => $data,
                'timeout' => 5
            )
        );
        $context = stream_context_create($options);
        $result    = file_get_contents($url, false, $context);
        if($http_response_header[0] != 'HTTP/1.1 200 OK'){
            $result = array(
                'result' => 'error',
                'reason' => '验证失败'
            );
            return json_encode($result);
        }else{
            return $result;
        }
    }
}