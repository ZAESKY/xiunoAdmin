<?php
namespace addons\encryption\library;

class Sg11
{
    public function encryption($comment, $version, $original, $complete) {
        if(!is_file($original)){
            $data = [
                'code' => -1,
                'info' => '$original并不是一个有效文件！'
            ];
            return $data;
        }
        $version = implode(',' ,$version);
        $result = json_decode($this->get_curl('http://api.phpth.cn/api.php?act=encode',[
            'post'=> [
                'edition'   => 'Sg11',
                'version'   => $version,
                'comment'   => $comment,
                'file'      => new \CURLFile($original)
            ]
        ]),true);
        
        if($result['code'] == 1){
            file_put_contents($complete, $this->get_curl('http://api.phpth.cn/api.php?act=downfile&file='.$result['down']));
            $data = [
                'code' => 1,
                'info' => $result['info'],
            ];
            return $data;
        }else{
            $data = [
                'code' => -1,
                'info' => $result['info'],
            ];
            return $data;
        }
    }
    
    private function get_curl($url, $paras = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (@$paras['Header']) {
            $Header = $paras['Header'];
        } else {
            $Header[] = "Accept:*/*";
            $Header[] = "Accept-Encoding:gzip,deflate,sdch";
            $Header[] = "Accept-Language:zh-CN,zh;q=0.8";
            $Header[] = "Connection:close";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $Header);
        if (@$paras['ctime']) { // 连接超时
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $paras['ctime']);
        } else {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        }
        if (@$paras['rtime']) { // 读取超时
            curl_setopt($ch, CURLOPT_TIMEOUT, $paras['rtime']);
        }
        if (@$paras['post']) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paras['post']);
        }
        if (@$paras['header']) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if (@$paras['cookie']) {
            curl_setopt($ch, CURLOPT_COOKIE, $paras['cookie']);
        }
        if (@$paras['refer']) {
            if ($paras['refer'] == 1) {
                curl_setopt($ch, CURLOPT_REFERER, 'http://m.qzone.com/infocenter?g_f=');
            } else {
                curl_setopt($ch, CURLOPT_REFERER, $paras['refer']);
            }
        }
        if (@$paras['ua']) {
            curl_setopt($ch, CURLOPT_USERAGENT, $paras['ua']);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36");
        }
        if (@$paras['nobody']) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (@$paras['GetCookie']) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $result = curl_exec($ch);
            preg_match_all("/Set-Cookie: (.*?);/m", $result, $matches);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($result, 0, $headerSize); //状态码
            $body = substr($result, $headerSize); //响应内容
            $ret = [
                "Cookie" => $matches, "body" => $body, "code" => $header
            ];
            curl_close($ch);
            return $ret;
        }
        $ret = curl_exec($ch);
        if (@$paras['loadurl']) {
            $Headers = curl_getinfo($ch);
            $ret = $Headers['redirect_url'];
        }
        curl_close($ch);
        return $ret;
    }
}