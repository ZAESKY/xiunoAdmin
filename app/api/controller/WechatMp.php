<?php

namespace app\api\controller;

use app\common\controller\ApiBackend;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Session;

class WechatMp extends ApiBackend
{
    private const EXPIRE_SECONDS = 300;
    private $appModel;

    public function initialize()
    {
        parent::initialize();
        $this->appModel = new \app\admin\model\AppModel();
    }

    public function callback()
    {
        if (IS_GET) {
            return $this->checkSignature();
        }

        $xml = file_get_contents('php://input');
        if (empty($xml)) {
            return 'success';
        }

        $message = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$message) {
            return 'success';
        }

        $event = strtolower((string)$message->Event);
        if (!in_array($event, ['subscribe', 'scan'], true)) {
            return 'success';
        }

        $openid = (string)$message->FromUserName;
        $eventKey = (string)$message->EventKey;
        if ($event === 'subscribe' && strpos($eventKey, 'qrscene_') === 0) {
            $eventKey = substr($eventKey, 8);
        }

        if (!empty($openid) && strpos($eventKey, 'wxlogin_') === 0) {
            $token = substr($eventKey, 8);
            $row = Db::name('wechat_mp_login')
                ->where('token', $token)
                ->where('expires_at', '>', datetime())
                ->find();
            if ($row && $row['status'] === 'pending') {
                Db::name('wechat_mp_login')
                    ->where('id', $row['id'])
                    ->data([
                        'openid' => $openid,
                        'status' => 'scanned',
                        'updated_at' => datetime(),
                    ])
                    ->update();
            }
        }

        return 'success';
    }

    public function qrcode()
    {
        if (!IS_POST) {
            return json(message('非法请求', false));
        }

        $type = input('post.type/s', '');
        if (!in_array($type, ['userLogin', 'adminLogin', 'bindUser', 'bindAdmin'], true)) {
            return json(message('登录类型错误', false));
        }

        if (in_array($type, ['userLogin', 'adminLogin'], true) && !$this->loginEnabled()) {
            return json(message('微信公众号登录未开启', false));
        }

        if ($type === 'bindUser') {
            try {
                parent::userLogin();
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }

        if ($type === 'bindAdmin' && empty(session('adminId'))) {
            return json(message(t('common.need_login'), false));
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return json(message('微信公众号配置不完整或 access_token 获取失败', false));
        }

        $token = md5(uniqid('', true) . mt_rand(1000, 9999));
        $scene = 'wxlogin_' . $token;
        $payload = [
            'expire_seconds' => self::EXPIRE_SECONDS,
            'action_name' => 'QR_STR_SCENE',
            'action_info' => [
                'scene' => [
                    'scene_str' => $scene,
                ],
            ],
        ];

        $api = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . urlencode($accessToken);
        $response = curl_request($api, json_encode($payload, JSON_UNESCAPED_UNICODE), 'post', true);
        $result = json_decode((string)$response, true);
        if (empty($result['ticket'])) {
            $msg = !empty($result['errmsg']) ? $result['errmsg'] : '二维码创建失败';
            return json(message('微信公众号' . $msg, false));
        }

        Db::name('wechat_mp_login')->insert([
            'token' => $token,
            'scene' => $scene,
            'type' => $type,
            'status' => 'pending',
            'openid' => '',
            'user_id' => $type === 'bindUser' ? intval(cookie('userId')) : 0,
            'admin_id' => $type === 'bindAdmin' ? intval(session('adminId')) : 0,
            'expires_at' => datetime(time() + self::EXPIRE_SECONDS),
            'created_at' => datetime(),
            'updated_at' => datetime(),
        ]);

        return json(message('ok', true, [
            'token' => $token,
            'qr_url' => 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($result['ticket']),
            'expires_in' => self::EXPIRE_SECONDS,
        ]));
    }

    public function status()
    {
        if (!IS_POST) {
            return json(message('非法请求', false));
        }

        $token = input('post.token/s', '');
        if (empty($token)) {
            return json(message('缺少登录凭证', false, ['status' => 'expired']));
        }

        $row = Db::name('wechat_mp_login')->where('token', $token)->find();
        if (!$row || strtotime($row['expires_at']) < time()) {
            return json(message('二维码已过期，请刷新重试', false, ['status' => 'expired']));
        }

        if ($row['status'] === 'pending') {
            return json(message('等待扫码关注公众号', true, ['status' => 'pending']));
        }

        if (empty($row['openid'])) {
            return json(message('微信扫码状态异常，请刷新重试', false, ['status' => 'expired']));
        }

        switch ($row['type']) {
            case 'userLogin':
                return json($this->finishUserLogin($row));
            case 'adminLogin':
                return json($this->finishAdminLogin($row));
            case 'bindUser':
                return json($this->finishUserBind($row));
            case 'bindAdmin':
                return json($this->finishAdminBind($row));
            default:
                return json(message('登录类型错误', false));
        }
    }

    public function chooseUser()
    {
        if (!IS_POST) {
            return json(message('非法请求', false));
        }

        $token = input('post.token/s', '');
        $userId = intval(input('post.user_id/d', 0));
        $row = Db::name('wechat_mp_login')->where('token', $token)->find();
        if (!$row || $row['type'] !== 'userLogin' || empty($row['openid'])) {
            return json(message('登录凭证已失效', false));
        }

        $user = Db::name('user')->where([
            'id' => $userId,
            'wechat_openid' => $row['openid'],
            'status' => 1,
        ])->find();
        if (empty($user)) {
            return json(message(t('user.not_exist'), false));
        }

        $this->setUserLogin($user);
        $this->markDone($row['id']);
        return json(message(t('login.success'), true, ['url' => '/user.php/Index/index.html']));
    }

    private function finishUserLogin(array $row): array
    {
        $users = Db::name('user')->where(['wechat_openid' => $row['openid'], 'status' => 1])->select();
        $count = $users->count();
        if ($count <= 0) {
            return message('该微信未绑定用户账号，请先登录后在个人中心绑定微信公众号', false, ['status' => 'need_bind']);
        }
        if ($count === 1) {
            $user = $users->first();
            $this->setUserLogin($user);
            $this->markDone($row['id']);
            return message(t('login.success'), true, ['status' => 'success', 'url' => '/user.php/Index/index.html']);
        }

        $data = [];
        foreach ($users as $user) {
            $appName = t('common.load_failed');
            if (!empty($user['appid'])) {
                $appInfo = $this->appModel->getInfo(intval($user['appid']));
                if ($appInfo) {
                    $appName = $appInfo['name'];
                }
            }
            $data[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'appname' => $appName,
            ];
        }
        return message(t('login.select_account'), true, ['status' => 'select', 'accounts' => $data]);
    }

    private function finishAdminLogin(array $row): array
    {
        $admin = Db::name('admin')->where(['wechat_openid' => $row['openid'], 'status' => 1])->find();
        if (empty($admin)) {
            return message('该微信未绑定管理员账号，请先登录后台后绑定微信公众号', false, ['status' => 'need_bind']);
        }

        session('adminId', $admin['id'], 86400);
        session('adminSign', data_auth_sign($admin['username'] . $admin['password'] . sf_password_hash()), 86400);
        Session::save();
        $this->markDone($row['id']);
        return message(t('login.success'), true, ['status' => 'success', 'url' => '/admin.php/Index/index.html']);
    }

    private function finishUserBind(array $row): array
    {
        try {
            parent::userLogin();
        } catch (\Exception $e) {
            return message($e->getMessage(), false);
        }

        Db::name('user')->where('id', intval(cookie('userId')))->data(['wechat_openid' => $row['openid']])->update();
        $this->markDone($row['id']);
        return message('微信公众号绑定成功', true, ['status' => 'success']);
    }

    private function finishAdminBind(array $row): array
    {
        $adminId = intval(session('adminId'));
        if (empty($adminId)) {
            return message(t('common.need_login'), false);
        }

        Db::name('admin')->where('id', $adminId)->data(['wechat_openid' => $row['openid']])->update();
        $this->markDone($row['id']);
        return message('微信公众号绑定成功', true, ['status' => 'success']);
    }

    private function setUserLogin(array $user): void
    {
        cookie('userId', $user['id']);
        cookie('userSign', data_auth_sign($user['appid'] . $user['username'] . $user['password'] . sf_password_hash()));
    }

    private function markDone(int $id): void
    {
        Db::name('wechat_mp_login')->where('id', $id)->data([
            'status' => 'confirmed',
            'updated_at' => datetime(),
        ])->update();
    }

    private function checkSignature()
    {
        $token = (string)conf('wechat_mp_token');
        $signature = input('get.signature/s', '');
        $timestamp = input('get.timestamp/s', '');
        $nonce = input('get.nonce/s', '');
        $echostr = input('get.echostr/s', '');

        $tmp = [$token, $timestamp, $nonce];
        sort($tmp, SORT_STRING);
        if (!empty($token) && sha1(implode($tmp)) === $signature) {
            return response($echostr);
        }
        return response('fail');
    }

    private function getAccessToken(): string
    {
        $appid = (string)conf('wechat_mp_appid');
        $secret = (string)conf('wechat_mp_appsecret');
        if (empty($appid) || empty($secret)) {
            return '';
        }

        $cacheKey = 'wechat_mp_access_token_' . md5($appid);
        $token = Cache::get($cacheKey);
        if (!empty($token)) {
            return $token;
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/token';
        $response = curl_get($url, [
            'grant_type' => 'client_credential',
            'appid' => $appid,
            'secret' => $secret,
        ]);
        $data = json_decode((string)$response, true);
        if (empty($data['access_token'])) {
            return '';
        }

        Cache::set($cacheKey, $data['access_token'], max(60, intval($data['expires_in'] ?? 7200) - 300));
        return $data['access_token'];
    }

    private function loginEnabled(): bool
    {
        $loginSwitch = conf('login_switch');
        if (!is_array($loginSwitch)) {
            $loginSwitch = array_filter(explode(',', (string)$loginSwitch));
        }
        return in_array('wechat_mp', $loginSwitch, true);
    }
}
