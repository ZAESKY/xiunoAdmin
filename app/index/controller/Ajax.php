<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\index\service\AjaxService;
use app\index\validate\Register;
use app\common\service\RateLimitService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Event;
use think\facade\Log;
class Ajax extends Frontend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new AjaxService();
    }

    public function appInfo(){
        if(IS_POST){
            return $this->service->appInfo();
        }
    }

    public function queryAuth(){
        if(IS_POST){
            return $this->service->queryAuth();
        }
    }

    public function pluginMarket(){
        if(IS_POST){
            $post = $this->request->post();
            $limit = qh_page_limit($post['limit'] ?? null, 12, 50);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $keyword = !empty($post['text']) ? trim($post['text']) : '';

            $where = [['status', '=', 1]];
            if (!empty($keyword)) {
                $where[] = ['name|description|author', 'like', '%' . $keyword . '%'];
            }

            $total = Db::name('plugin')->where($where)->count();
            $list = Db::name('plugin')
                ->where($where)
                ->field('id,name,slug,category,version,author,icon,description,rating_count,rating_avg,is_hot,is_recommend,published_at,publish_type,publish_time')
                ->order('is_recommend', 'desc')
                ->order('download_count', 'desc')
                ->page($current_page, $limit)
                ->select()
                ->toArray();

            return message('success', true, [
                'data' => $list,
                'total' => $total,
                'current_page' => $current_page,
                'limit' => $limit,
            ]);
        }
    }

    public function register(){
        if(IS_POST){
            $post = $this->request->post();
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $username = !empty($post['username'])?trim((string)$post['username']):null;
            $qq = !empty($post['qq'])?trim((string)$post['qq']):null;
            $email = !empty($post['email'])?strtolower(trim((string)$post['email'])):null;
            $password = !empty($post['password'])?$post['password']:null;
            $code = !empty($post['code']) ? trim((string)$post['code']) : '';
            $rate = RateLimitService::hit('public_register', (string)get_client_ip(), 10, 3600);
            if (!$rate['ok']) {
                return message(t('login.network_register_limited'), false);
            }
            try {
                validate(Register::class)->check($post);
            } catch (ValidateException $e) {
                // 验证失败 输出错误信息
                return message($e->getError() ,false);
            }
            if (!preg_match('/^[1-9][0-9]{4,11}$/D', (string)$qq)) {
                return message(t('login.valid_qq_required'), false);
            }
            if ($code === '') {
                return message(t('registration.email_code_placeholder'), false);
            }

            $codeCacheKey = $this->registrationCodeCacheKey((string)$email);
            $attemptCacheKey = $codeCacheKey . ':attempts';
            $attempts = (int)Cache::get($attemptCacheKey, 0);
            if ($attempts >= 5) {
                Cache::delete($codeCacheKey);
                return message(t('login.code_attempts_exceeded'), false);
            }
            Cache::set($attemptCacheKey, $attempts + 1, 300);
            $cachedCode = (string)Cache::get($codeCacheKey, '');
            if ($cachedCode === '' || !hash_equals($cachedCode, $code)) {
                return message(t('login.code_invalid_or_expired'), false);
            }
            $appInfo = Db::name('app')
                ->where([
                    'id' => $appid,
                ])
                ->find();
            if(empty($appInfo)){
                return message(t('app.not_exist'), false);
            }
            if($appInfo['status'] != 2){
                return message(t('app.stopped_or_maintain'), false);
            }
            if($appInfo['register_switch'] != 1){
                return message(t('app.register_closed'), false);
            }
            $row = Db::name('user')
                ->where([
                    'username' => $username
                ])
                ->field('id')
                ->find();
            if($row){
                return message(t('app.username_exists'), false);
            }
            if (Db::name('user')->where('qq', $qq)->find()) {
                return message(t('login.qq_already_bound'), false);
            }
            if (Db::name('user')->where('email', $email)->find()) {
                return message(t('login.email_already_bound'), false);
            }
            try{
                $powerPriceModel = new \app\admin\model\PowerPriceModel();
                $power = $powerPriceModel->getDefaultPower(intval($appInfo['power_template']));
            }catch (\Exception $e){
                return message($e->getMessage(), false);
            }
            try{
                $data = [
                    'username' => $username,
                    'password' => qh_password_make($password),
                    'phone' => '',
                    'qq' => $qq,
                    'email' => $email,
                    'appid' => $appid,
                    'status' => 1,
                    'balance' => 0,
                    'integral' => 0,
                    'power' => $power,
                    'addtime' => datetime(),
                    'userid' => 0
                ];
                Db::name('user')
                    ->insert($data);
                Cache::delete($codeCacheKey);
                Cache::delete($attemptCacheKey);
                return message(t('login.registration_success'), true);
            }catch (\Exception $e){
                Log::error('Public registration failed: ' . $e->getMessage(), ['exception' => $e]);
                return message(t('login.registration_failed'), false);
            }
        }
    }

    public function sendRegCode()
    {
        if (!IS_POST) {
            return message(t('common.illegal_request'), false);
        }

        $email = strtolower(trim((string)input('post.email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return message(t('login.valid_email_required'), false);
        }

        $recipientHash = hash('sha256', $email);
        if (Cache::get('mail_code_cooldown:reg:' . $recipientHash)) {
            return message(t('login.send_too_frequent'), false);
        }
        $rate = RateLimitService::hit('public_reg_mail', (string)get_client_ip(), 20, 3600);
        if (!$rate['ok']) {
            return message(t('login.network_send_limited'), false);
        }

        $code = sprintf('%06d', random_int(0, 999999));
        try {
            $results = Event::trigger('ChangeBindingMailNotice', [
                'to' => $email,
                'title' => conf('title') . ' - ' . t('mail.register_code_subject'),
                'from_name' => conf('title'),
                'content' => t('mail.register_code_body', ['code' => $code]),
            ]);
            $result = $results[0] ?? null;
            if (is_string($result)) {
                $decoded = json_decode($result, true);
                $result = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($result) || (int)($result['code'] ?? -1) !== 0) {
                Log::warning('Public registration mail dispatch failed', [
                    'provider_response' => is_array($result) ? ($result['msg'] ?? 'invalid response') : 'invalid response',
                ]);
                return message(t('login.email_send_failed'), false);
            }

            Cache::set($this->registrationCodeCacheKey($email), $code, 180);
            Cache::delete($this->registrationCodeCacheKey($email) . ':attempts');
            Cache::set('mail_code_cooldown:reg:' . $recipientHash, 1, 60);
            return message(t('login.code_sent'), true);
        } catch (\Throwable $e) {
            Log::error('Public registration mail exception: ' . $e->getMessage(), ['exception' => $e]);
            return message(t('login.email_send_failed'), false);
        }
    }

    private function registrationCodeCacheKey(string $email): string
    {
        return 'reg_code_' . hash('sha256', strtolower(trim($email)));
    }
}
