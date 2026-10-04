<?php
namespace addons\mail;	// 注意命名空间规范

use think\Addons;
use think\facade\Log;
use PHPMailer\PHPMailer\PHPMailer;
use addons\mail\library\AliYun\AliYun;
use addons\mail\library\SendCloud\SendCloud;
/**
 * 插件测试
 * @author byron sampson
 */
class Plugin extends Addons	// 需继承think\Addons类
{
    /**
     * 插件安装方法
     * @return bool
     */
    public function install(){
        addNoticeGroupList(['mailNotifyUser' => 'SMTP发信邮箱通知']);
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall(){
        deleteNoticeGroupList(['mailNotifyUser' => 'SMTP发信邮箱通知']);
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable(){
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable(){
        return true;
    }

    /**
     * @title 发送邮件钩子
     * @param $to 接收者
     * @param $title 标题
     * @param $content 邮件内容
     * @return mixed
     */
    public function mailNotifyUser(array $param){
        try{
            $to = trim((string)($param['to'] ?? ''));
            $fromName = trim((string)($param['from_name'] ?? ''));
            $title = trim((string)($param['title'] ?? ''));
            $content = (string)($param['content'] ?? '');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return json_encode(message(t('mail.recipient_invalid'), false), JSON_UNESCAPED_UNICODE);
            }
            if ($title === '' || $content === '') {
                return json_encode(message(t('mail.subject_or_content_required'), false), JSON_UNESCAPED_UNICODE);
            }

            $config = self::normalizeConfig($this->getConfig(true));
            switch ((int)($config['type'] ?? 0)){
                case 1:
                    return $this->sendMail($to, $fromName, $title, $content, (array)($config['smtp'] ?? []));
                case 2:
                    return $this->sendAliYun($to, $fromName, $title, $content, (array)($config['aliyun'] ?? []));
                case 3:
                    return $this->sendSendCloud($to, $fromName, $title, $content, (array)($config['sendcloud'] ?? []));
                default:
                    return json_encode(message(t('mail.sending_disabled'), false), JSON_UNESCAPED_UNICODE);
            }
        }catch (\Throwable $e){
            Log::error('Mail plugin failed: ' . $e->getMessage(), ['exception' => $e]);
            return json_encode(message(t('mail.send_failed').' [errorCode:SendMailError]', false), JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * think-addons 2.x returns indexed metadata rows for getConfig(true), while
     * the mail plugin consumes a name => value map. Support both layouts.
     */
    private static function normalizeConfig(array $raw): array
    {
        $config = [];
        foreach ($raw as $key => $item) {
            if (is_array($item) && isset($item['name'])) {
                $name = trim((string)$item['name']);
                if ($name !== '') {
                    $config[$name] = $item['value'] ?? null;
                }
                continue;
            }
            if (is_string($key)) {
                $config[$key] = $item;
            }
        }
        return $config;
    }

    /**
     * @title SMTP发送邮箱
     * @param $to 接收者
     * @param $from_name 发信人昵称
     * @param $title 标题
     * @param $content 邮件内容
     * @return mixed
     */
    private function sendMail($to, $from_name, $title, $content, array $config) {
        $mail = new PHPMailer;
        $host = trim((string)($config['server'] ?? ''));
        $port = (int)($config['port'] ?? 0);
        $username = trim((string)($config['name'] ?? ''));
        $password = (string)($config['authcode'] ?? '');
        if(empty($host)) return json_encode(message(t('mail.smtp_host_missing'), false));
        if($port < 1 || $port > 65535) return json_encode(message(t('mail.smtp_port_invalid'), false));
        if(!filter_var($username, FILTER_VALIDATE_EMAIL)) return json_encode(message(t('mail.smtp_username_invalid'), false));
        if(empty($password)) return json_encode(message(t('mail.smtp_password_missing'), false));
        // 是否启用smtp的debug进行调试 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式，
        // 可选择的值有 1 、 2 、 3
        // $mail->SMTPDebug = 2;
        //使用smtp鉴权方式发送邮件
        $mail->isSMTP();
        // Business notifications run after the primary database operation.
        // Bound the provider wait so an unavailable SMTP server cannot leave
        // an audit/purchase/withdrawal request hanging for several minutes.
        $mail->Timeout = 15;
        //smtp需要鉴权 这个必须是true
        $mail->SMTPAuth = true;
        // qq 邮箱的 smtp服务器地址，这里当然也可以写其他的 smtp服务器地址
        $mail->Host = $host;
        //smtp登录的账号 这里填入字符串格式的qq号即可
        $mail->Username = $username;
        // 这个就是之前得到的授权码，一共16位
        $mail->Password = $password;
        // 465 uses implicit TLS; 587 conventionally uses STARTTLS.
        $mail->SMTPSecure = $port === 587 ? 'tls' : 'ssl';
        // //设置ssl连接smtp服务器的远程服务器端口号，可选465或587
        $mail->Port = $port;
        //设置smtp的helo消息头 这个可有可无 内容任意
        // $mail->Helo = 'Hello smtp.qq.com Server';
        //设置发件人的主机域 可有可无 默认为localhost 内容任意，建议使用你的域名
        // $mail->Hostname = 'http://www.lsgogroup.com';
        //设置发送的邮件的编码 也可选 GB2312
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($username, $from_name);
        // $to 为收件人的邮箱地址，如果想一次性发送向多个邮箱地址，则只需要将下面这个方法多次调用即可
        $mail->addAddress($to);
        //邮件正文是否为html编码 注意此处是一个方法 不再是属性 true或false
        $mail->isHTML(true);
        // 该邮件的主题
        $mail->Subject = $title;
        // 该邮件的正文内容
        $mail->Body = $content;
        //为该邮件添加附件 该方法也有两个参数 第一个参数为附件存放的目录（相对目录、或绝对目录均可） 第二参数为在邮件附件中该附件的名称
        //$mail->addAttachment('E:/370205030101010301_20200903142139_4.jpg','mm.jpg');
        //同样该方法可以多次调用 上传多个附件
        // $mail->addAttachment('./Jlib-1.1.0.js','Jlib.js');
        // 使用 send() 方法发送邮件
        if(!$mail->send()) {
          Log::warning('SMTP mail provider rejected a message: ' . $this->safeProviderError(
              (string)$mail->ErrorInfo,
              [$username, $password]
          ));
          return json_encode(message(t('mail.send_failed').' [errorCode:SendMailError]', false), JSON_UNESCAPED_UNICODE);
          //return message('发送失败！'.$mail->ErrorInfo, false);
        } else {
            return json_encode(message(t('mail.send_success'), true), JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * @title 阿里云AliYun
     * @param $to 接收者
     * @param $from_name 发信人昵称
     * @param $title 标题
     * @param $content 邮件内容
     * @return mixed
     */
    private function sendAliYun($to, $from_name, $title, $content, array $config){
        $accessKeyId = trim((string)($config['accessKey'] ?? ''));
        $accessKeySecret = (string)($config['accessSecret'] ?? '');
        $name = trim((string)($config['name'] ?? ''));
        if(empty($accessKeyId)) return json_encode(message(t('mail.access_key_id_missing'), false));
        if(empty($accessKeySecret)) return json_encode(message(t('mail.access_key_secret_missing'), false));
        if(empty($name)) return json_encode(message(t('mail.sender_missing'), false));
        $aliYun = new AliYun($accessKeyId, $accessKeySecret);
        $result = $aliYun->send($to, $title, $content, $name, $from_name);
        if($result === true){
            return json_encode(message(t('mail.send_success'), true), JSON_UNESCAPED_UNICODE);
        }else{
            Log::warning('AliYun mail provider rejected a message: ' . (is_scalar($result) ? (string)$result : 'unknown response'));
            return json_encode(message(t('mail.send_failed').' [errorCode:SendMailError]', false), JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * @title 搜狐SendCloud
     * @param $to 接收者
     * @param $from_name 发信人昵称
     * @param $title 标题
     * @param $content 邮件内容
     * @return mixed
     */
    private function sendSendCloud($to, $from_name, $title, $content, array $config){
        $apiUser = trim((string)($config['apiUser'] ?? ''));
        $apiKey = (string)($config['apiKey'] ?? '');
        $name = trim((string)($config['name'] ?? ''));
        if(empty($apiUser)) return json_encode(message(t('mail.api_user_missing'), false));
        if(empty($apiKey)) return json_encode(message(t('mail.api_key_missing'), false));
        if(empty($name)) return json_encode(message(t('mail.sender_missing'), false));
        $sendCloud = new SendCloud($apiUser, $apiKey);
        $result = $sendCloud->send($to, $title, $content, $name, $from_name);
        if($result === true){
            return json_encode(message(t('mail.send_success'), true), JSON_UNESCAPED_UNICODE);
        }else{
            Log::warning('SendCloud mail provider rejected a message: ' . (is_scalar($result) ? (string)$result : 'unknown response'));
            return json_encode(message(t('mail.send_failed').' [errorCode:SendMailError]', false), JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Keep useful provider diagnostics without writing credentials to logs.
     */
    private function safeProviderError(string $error, array $secrets = []): string
    {
        $error = trim((string)preg_replace('/[\r\n\t]+/', ' ', $error));
        foreach ($secrets as $secret) {
            $secret = (string)$secret;
            if ($secret !== '') {
                $error = str_replace($secret, '[redacted]', $error);
            }
        }
        return mb_substr($error !== '' ? $error : 'unknown provider error', 0, 500);
    }

}
