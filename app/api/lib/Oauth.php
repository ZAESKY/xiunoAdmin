<?php
/**
 * SF聚合登录SDK
 * 1.0
 * 陌上花开QQ2129876388
 * QQ群：363306078
 * 有任何问题可以加QQ群进行反馈，欢迎进群咨询
 **/
namespace app\api\lib;

class Oauth{
    private $apiurl;
    private $appid;
    private $appkey;
    private $callback;

    function __construct($callback){
        $siteurl = ($_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].'/';
        $this->apiurl = 'http://api.sf-team.cn/connect.php';
        $this->appid = 1000;
        $this->appkey = 'b2a445fd479dd5918c8b353fa8dcd65f';
        $this->callback = $siteurl.$callback;
    }

    //获取登录跳转url
    public function login($type){

        //-------生成唯一随机串防CSRF攻击
        $state = md5(uniqid(rand(), TRUE));
        session('Oauth_state', $state);

        //-------构造请求参数列表
        $keysArr = array(
            "act" => "login",
            "appid" => $this->appid,
            "appkey" => $this->appkey,
            "type" => $type,
            "redirect_uri" => $this->callback,
            "state" => $state
        );
        $login_url = $this->apiurl.'?'.http_build_query($keysArr);
        $response = self::get_curl($login_url);
        $arr = json_decode($response,true);
        return $arr;
    }

    //登录成功返回网站
    public function callback($code){
        //-------请求参数列表
        $keysArr = array(
            "act" => "callback",
            "appid" => $this->appid,
            "appkey" => $this->appkey,
            "code" => $code
        );

        //------构造请求access_token的url
        $token_url = $this->apiurl.'?'.http_build_query($keysArr);
        $response = self::get_curl($token_url);

        $arr = json_decode($response,true);
        return $arr;
    }

    //查询用户信息
    public function query($type, $social_uid){
        //-------请求参数列表
        $keysArr = array(
            "act" => "query",
            "appid" => $this->appid,
            "appkey" => $this->appkey,
            "type" => $type,
            "social_uid" => $social_uid
        );

        //------构造请求access_token的url
        $token_url = $this->apiurl.'?'.http_build_query($keysArr);
        $response = self::get_curl($token_url);

        $arr = json_decode($response,true);
        return $arr;
    }

    private function get_curl($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }
}
