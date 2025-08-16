<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\facade\Cache;
use think\facade\Event;
use think\facade\Db;

class DownloadMail extends Frontend
{
    public function initialize()
    {
        parent::initialize();
    }

    public function getCode(){
        if(IS_POST){
            $cleartime = 180;// 过期时间 单位:秒
            $post = $this->request->post();
            $qq = !empty($post['qq'])?intval($post['qq']):null;
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
            if(empty($appid)){
                return message('请选择所属应用！' ,false);
            }
            if(empty($auth_info)){
                return message('请填写授权内容！' ,false);
            }
            if(empty($qq)){
                return message('请填写授权者QQ！' ,false);
            }
            $row = Db::name('auth')
                ->where([
                    'appid' => $appid,
                    'auth_info' => $auth_info
                ])
                ->field('qq')
                ->find();
            if(empty($row)){
                return message('不存在此授权！' ,false);
            }
            if($row['qq'] != $qq){
                return message('该授权QQ与所填QQ不匹配！' ,false);
            }
            if(Cache::get('downloadVerification'.$qq)){
                return message('请勿频繁发送验证码！' ,false);
            }
            $code = get_random_code(6);
            $email = $qq.'@qq.com';
            $param = [
                'to' => $email,
                'title' => conf('title').' - 验证码通知',
                'from_name' => conf('title'),
                'content' => '验证码:'.$code.'。此验证码只用于源码下载的邮箱，请妥善保管，不要透露给任何人。如非本人操作请忽略。'
            ];

            Cache::set('downloadVerification'.$qq, $code, $cleartime);
            return Event::trigger('DownloadMailNotice', $param)[0];
        }
    }

    public function verification(){
        if(IS_POST){
            $post = $this->request->post();
            $qq = !empty($post['qq'])?intval($post['qq']):null;
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
            $code = !empty($post['code'])?$post['code']:null;
            if(empty($appid)){
                return message('请选择所属应用！' ,false);
            }
            if(empty($auth_info)){
                return message('请填写授权内容！' ,false);
            }
            if(empty($qq)){
                return message('请填写授权者QQ！' ,false);
            }
            if(empty($code)){
                return message('请填写验证码！' ,false);
            }
            $row = Db::name('auth')
                ->where([
                    'appid' => $appid,
                    'auth_info' => $auth_info
                ])
                ->field('qq,authcode')
                ->find();
            if(empty($row)){
                return message('不存在此授权！' ,false);
            }
            if($row['qq'] != $qq){
                return message('该授权QQ与所填QQ不匹配！' ,false);
            }
            $cacheCode = Cache::get('downloadVerification'.$qq);
            if(empty($cacheCode)){
                return message('不存在此验证码或已过期' ,false);
            }
            if($cacheCode != $code){
                return message('验证码错误' ,false);
            }
            Cache::delete('downloadVerification'.$qq);
            $res = Db::name('version')
                        ->where([
                            ['appid', '=', $appid],
                            ['status', '=', 1],
                            ['type', '=', 0]
                        ])
                        ->find();
            if(empty($res)){
                return message('此应用无安装包' ,false);
            }
            $value = serialize([
                'versionInfo' => $res,
                'authInfo' => $row
            ]);
            $key = md5(uniqid());
            Cache::set($key, $value, 43200);
            return message('获取下载链接成功' ,true, ['url' => 'http://'.DOMAIN.'/api.php/Download/download/?sign='.$key]);
        }
    }
}