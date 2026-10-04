<?php
namespace addons\mail\library\SendCloud;

class SendCloud {
    private $apiUser;
    private $apiKey;

    function __construct($apiUser, $apiKey){
        $this->apiUser = $apiUser;
        $this->apiKey = $apiKey;
    }
    public function send($to, $sub, $msg, $from, $from_name){
        if(empty($this->apiUser)||empty($this->apiKey))return false;
        $url='https://api.sendcloud.net/apiv2/mail/send';
        $data=array(
            'apiUser' => $this->apiUser,
            'apiKey' => $this->apiKey,
            'from' => $from,
            'fromName' => $from_name,
            'to' => $to,
            'subject' => $sub,
            'html' => $msg);
        $ch=curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $json=curl_exec($ch);
        if ($json === false) {
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $arr=json_decode($json,true);
        if($httpCode === 200 && is_array($arr) && intval($arr['statusCode'] ?? 0) === 200){
            return true;
        }else{
            $messages = is_array($arr) ? ($arr['message'] ?? []) : [];
            return is_array($messages) ? implode("\n", array_map('strval', $messages)) : (string)$messages;
        }
    }
}
