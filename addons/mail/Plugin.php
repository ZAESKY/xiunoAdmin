<?php
namespace addons\mail;	// 注意命名空间规范

use think\Addons;
use addons\mail\library\PHPMailer\PHPMailer;
use addons\mail\library\AliYun\Aliyun;
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
            $config = $this->getConfig();
            //exit(print_r($config));
            switch ($config['type']){
                case 1:
                    return $this->sendMail($param['to'], $param['from_name'], $param['title'], $param['content']);
                case 2:
                    return $this->sendAliYun($param['to'], $param['from_name'], $param['title'], $param['content']);
                case 3:
                    return $this->sendSendCloud($param['to'], $param['from_name'], $param['title'], $param['content']);
                default:
                    return json_encode(message('站点未开启邮箱发送！', false));
            }
        }catch (\Exception $e){
            return json_encode(message('发送失败！[errorCode:SendMailError]'.$e->getMessage(), false));
        }
    }

    /**
     * @title SMTP发送邮箱
     * @param $to 接收者
     * @param $from_name 发信人昵称
     * @param $title 标题
     * @param $content 邮件内容
     * @return mixed
     */
    private function sendMail($to, $from_name, $title, $content) {
        $mail = new PHPMailer;
        $config = $this->getConfig();
        $host = $config['smtp']['server'];
        $port = $config['smtp']['port'];
        $username = $config['smtp']['name'];
        $password = $config['smtp']['authcode'];
        if(empty($host)) return json_encode(message('站点未配置smtp服务器地址！', false));
        if(empty($port)) return json_encode(message('站点未配置smtp服务器的远程服务器端口号！', false));
        if(empty($username)) return json_encode(message('站点未配置smtp登录的账号！', false));
        if(empty($password)) return json_encode(message('站点未配置smtp登录的授权码！', false));
        // 是否启用smtp的debug进行调试 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式，
        // 可选择的值有 1 、 2 、 3
        // $mail->SMTPDebug = 2;
        //使用smtp鉴权方式发送邮件
        $mail->isSMTP();
        //smtp需要鉴权 这个必须是true
        $mail->SMTPAuth = true;
        // qq 邮箱的 smtp服务器地址，这里当然也可以写其他的 smtp服务器地址
        $mail->Host = $host;
        //smtp登录的账号 这里填入字符串格式的qq号即可
        $mail->Username = $username;
        // 这个就是之前得到的授权码，一共16位
        $mail->Password = $password;
        //设置使用ssl加密方式登录鉴权
        $mail->SMTPSecure = 'ssl';
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
          return json_encode(message('发送失败！[errorCode:SendMailError]', false));
          //return message('发送失败！'.$mail->ErrorInfo, false);
        } else {
            return json_encode(message('发送成功！', true));
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
    private function sendAliYun($to, $from_name, $title, $content){
        $accessKeyId = conf('mail_aliyun_accesskeyid');
        $accessKeySecret = conf('mail_aliyun_accesskeysecret');
        $name = conf('mail_aliyun_name');
        if(empty($accessKeyId)) return json_encode(message('站点未配置AccessKeyId！', false));
        if(empty($accessKeySecret)) return json_encode(message('站点未配置AccessKeySecret！', false));
        if(empty($name)) return json_encode(message('站点未配置发信邮箱！', false));
        $aliYun = new Aliyun($accessKeyId, $accessKeySecret);
        $result = $aliYun->send($to, $title, $content, $name, $from_name);
        if($result){
            return json_encode(message('发送成功！', true));
        }else{
            return json_encode(message('发送失败！[errorCode:SendMailError]', false));
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
    private function sendSendCloud($to, $from_name, $title, $content){
        $apiUser = conf('mail_sendcloud_apiuser');
        $apiKey = conf('mail_sendcloud_apikey');
        $name = conf('mail_sendcloud_name');
        if(empty($apiUser)) return json_encode(message('站点未配置API_USER！', false));
        if(empty($apiKey)) return json_encode(message('站点未配置API_KEY！', false));
        if(empty($name)) return json_encode(message('站点未配置发信邮箱！', false));
        $sendCloud = new SendCloud($apiUser, $apiKey);
        $result = $sendCloud->send($to, $title, $content, $name, $from_name);
        if($result){
            return json_encode(message('发送成功！', true));
        }else{
            return json_encode(message('发送失败！[errorCode:SendMailError]', false));
        }
    }

}