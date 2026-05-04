<?php
/**
 * QQ互联 OAuth2.0 直接对接
 */
namespace app\api\lib;

class Oauth{
    private $appid;
    private $appkey;
    private $callback;

    function __construct($callback){
        $siteurl = ($_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].'/';
        $this->appid = '101849630';
        $this->appkey = '8e9f043c7955f1f63909c0ef5a90db66';
        $this->callback = $siteurl.$callback;
    }

    // 获取QQ互联授权登录跳转url
    public function login($type){
        $state = md5(uniqid(rand(), TRUE));
        session('Oauth_state', $state);

        $keysArr = array(
            "response_type" => "code",
            "client_id" => $this->appid,
            "redirect_uri" => $this->callback,
            "state" => $state
        );
        $login_url = 'https://graph.qq.com/oauth2.0/authorize?'.http_build_query($keysArr);
        return ['code' => 0, 'url' => $login_url];
    }

    // 登录回调：用code换取access_token和openid
    public function callback($code){
        // Step 1: 用code换取access_token
        $keysArr = array(
            "grant_type" => "authorization_code",
            "client_id" => $this->appid,
            "client_secret" => $this->appkey,
            "code" => $code,
            "redirect_uri" => $this->callback
        );
        $token_url = 'https://graph.qq.com/oauth2.0/token?'.http_build_query($keysArr);
        $response = self::get_curl($token_url);

        // QQ互联token接口返回的是 query string 格式: access_token=xxx&expires_in=xxx
        parse_str($response, $tokenData);

        if(isset($tokenData['error'])){
            return ['code' => -1, 'msg' => $tokenData['error_description'] ?? '获取token失败'];
        }
        if(empty($tokenData['access_token'])){
            return ['code' => -1, 'msg' => '获取access_token失败: '.$response];
        }

        $access_token = $tokenData['access_token'];

        // Step 2: 用access_token换取openid
        $openid_url = 'https://graph.qq.com/oauth2.0/me?access_token='.$access_token;
        $response = self::get_curl($openid_url);

        // QQ互联me接口返回: callback( {...} ); 去掉外层包裹
        if(strpos($response, 'callback(') !== false){
            $lpos = strpos($response, '(');
            $rpos = strrpos($response, ')');
            $response = substr($response, $lpos + 1, $rpos - $lpos - 1);
        }

        $arr = json_decode($response, true);
        if(isset($arr['error'])){
            return ['code' => -1, 'msg' => $arr['error_description'] ?? '获取openid失败'];
        }
        if(empty($arr['openid'])){
            return ['code' => -1, 'msg' => '获取openid为空: '.$response];
        }

        // 用openid作为唯一标识（存入原access_token字段，保持兼容）
        return ['code' => 0, 'access_token' => $arr['openid'], 'social_uid' => $arr['openid']];
    }

    // 查询用户信息（保留接口兼容性）
    public function query($type, $social_uid){
        return ['code' => 0];
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
