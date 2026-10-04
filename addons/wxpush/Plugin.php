<?php
namespace addons\wxpush;	// 注意命名空间规范

use think\Addons;
use addons\wxpush\library\WxPusher\WxPusher;
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
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall(){
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
     * @title 微信推送消息到用户钩子
     * @param $to 接收者
     * @param $title 标题
     * @param $content 邮件内容
     * @return mixed
     */
    public function wxPushNotifyUser(array $param){
        //return $this->serverSauce($param['text'], $param['desp'], $param['key']);
        //return $this->wxPusher('AT_D3xjkDGB2KWP6FJNs1zZqFMCeJYB1EeJ', $param['title'], $param['content'], $param['contentType'], 1, $param['to']);
        try{
            switch (conf('wxpush_type')){
                case 1:// server酱
                    return $this->serverSauce($param['title'], $param['content'], $param['key']);
                case 2:// wxpusher
                    $appToken = conf('wxpush_wxpusher_apptoken');
                    return $this->wxPusher($appToken, $param['title'], $param['content'], $param['contentType'], 1, $param['to']);
                default:
                    return json_encode(message(t('wxpush.disabled'), false));
            }
        }catch (\Exception $e){
            return json_encode(message(t('wxpush.send_failed').' [errorCode:SendMailError]', false));
        }
    }

    /**
     * @title SMTP发送邮箱
     * @param $text 标题
     * @param $desp 内容
     * @param $key ServerKey
     * @return mixed
     */
    private function serverSauce($text, $desp = '', $key = '') {
        //conf('wxpush_server_key');
        if(empty($key)) return json_encode(message(t('wxpush.server_key_missing'), false));
        $postData = [
            'text' => $text,
            'desp' => $desp
        ];
        $header = [
            'Content-type: application/x-www-form-urlencoded',
        ];
        $result = get_curl('https://sc.ftqq.com/'.rawurlencode((string)$key).'.send', $postData, 0, 0, $header, 0, 0, 0, 1);
        $result = json_decode((string)($result['body'] ?? ''), true);

        if(is_array($result) && ($result['code'] ?? -1) == 0){
            return json_encode(message(t('wxpush.send_success'), true));
        }else{
            return json_encode(message(t('wxpush.send_failed').' [errorCode:WxPushServerSauceError]', false));
        }
    }

    /**
     * @param null $content
     * @param int $contentType
     * @param int $summary
     * @param bool $isUids
     * @param array int $id
     * @param string $url
     * @return string
     *
     * 标准信息发送方式
     * 需调用CURL
     *
     * $content: 您要发送的内容 \n换行
     * $summary: 消息摘要 \n换行  可为空
     * $contentType:
     *     |- 1表示文字
     *     |- 2表示html
     *     |- 3表示markdown
     *
     * $isUids:
     *     |- true    发送到用户
     *     |- false   发送到主题
     *
     * $url 需要添加协议头 http://或https://
     *
     * $array_id:  单条可使用int类型，多条使用数组方式
     *
     * 执行结果：
     *  是否开启$getMessageId:
     *      |—true    执行完毕后返回messageId和错误信息 多维数组形式
     *      |—false   执行完毕后仅返回错误信息，若无错误返回TRUE
     *
     * 使用方法实例：
     *      $wx->send('内容','摘要','类型','是否为用户id',id或数组id,'需传送的url',是否返回messageID))
     */
    private function wxPusher($appToken, $summary = null, $content = null,$contentType = 1,$isUids = true,$array_id = [],$url = '',$getMessageId = false){
        if(empty($appToken)){
            return json_encode(message(t('wxpush.send_failed').' [errorCode:WxPushWxPusherAppTokenEmpty]', false));
        }
        $wx = new WxPusher($appToken);
        $result = $wx->send($content, $summary, $contentType, $isUids, $array_id, $url, $getMessageId);
        if ($result === true || ($getMessageId && is_array($result) && empty($result['error']))) {
            return json_encode(message(t('wxpush.send_success'), true), JSON_UNESCAPED_UNICODE);
        }
        return json_encode(message(t('wxpush.send_failed').' [errorCode:WxPushWxPusherError]', false), JSON_UNESCAPED_UNICODE);
    }
}
