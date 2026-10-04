<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\facade\Cache;
use think\facade\Event;
use think\facade\Db;
use think\facade\Log;
use app\common\service\RateLimitService;
use app\common\service\ApplicationInstallerService;

class DownloadMail extends Frontend
{
    public function initialize()
    {
        parent::initialize();
    }

    public function getCode(){
        if(IS_POST){
            if (sf_download_mode() !== 'mail') {
                return message(t('download.mail_disabled'), false);
            }
            $post = $this->request->post();
            $qq = !empty($post['qq'])?trim((string)$post['qq']):null;
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
            if(empty($appid)){
                return message(t('auth.select_app') ,false);
            }
            if(empty($auth_info)){
                return message(t('auth.enter_content') ,false);
            }
            if(empty($qq)){
                return message(t('auth.enter_qq') ,false);
            }
            if (!preg_match('/^[1-9][0-9]{4,11}$/D', (string)$qq)) {
                return message(t('validation.auth_qq_invalid'), false);
            }
            $rate = RateLimitService::hit('download_mail', (string)get_client_ip(), 20, 3600);
            if (!$rate['ok']) {
                return message(t('login.network_send_limited'), false);
            }
            $row = Db::name('auth')
                ->where([
                    'appid' => $appid,
                    'auth_info' => $auth_info
                ])
                ->field('id,qq')
                ->find();
            if(empty($row)){
                return message(t('auth.not_exist') ,false);
            }
            if($row['qq'] != $qq){
                return message(t('auth.qq_mismatch') ,false);
            }
            $cacheKey = $this->verificationCacheKey($appid, (string)$auth_info, (string)$qq);
            if(Cache::get($cacheKey)){
                return message(t('download.code_send_too_frequent') ,false);
            }
            $code = sprintf('%06d', random_int(0, 999999));
            $email = $qq.'@qq.com';
            $param = [
                'to' => $email,
                'title' => conf('title').' - '.t('mail.verification_code_subject'),
                'from_name' => conf('title'),
                'content' => t('mail.download_code_body', ['code' => $code])
            ];

            try {
                $result = Event::trigger('DownloadMailNotice', $param)[0] ?? null;
                if (is_string($result)) {
                    $decoded = json_decode($result, true);
                    $result = is_array($decoded) ? $decoded : null;
                }
                if (!is_array($result) || (int)($result['code'] ?? -1) !== 0) {
                    return message(t('login.email_send_failed'), false);
                }
                Cache::set($cacheKey, $code, 180);
                Cache::delete($cacheKey . ':attempts');
                return message(t('login.code_sent'), true);
            } catch (\Throwable $e) {
                Log::error('Download verification mail failed: ' . $e->getMessage(), ['exception' => $e]);
                return message(t('login.email_send_failed'), false);
            }
        }
    }

    public function verification(){
        if(IS_POST){
            if (sf_download_mode() !== 'mail') {
                return message(t('download.mail_disabled'), false);
            }
            $post = $this->request->post();
            $qq = !empty($post['qq'])?trim((string)$post['qq']):null;
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
            $code = !empty($post['code'])?$post['code']:null;
            if(empty($appid)){
                return message(t('auth.select_app') ,false);
            }
            if(empty($auth_info)){
                return message(t('auth.enter_content') ,false);
            }
            if(empty($qq)){
                return message(t('auth.enter_qq') ,false);
            }
            if(empty($code)){
                return message(t('login.verification_code_required') ,false);
            }
            $row = Db::name('auth')
                ->where([
                    'appid' => $appid,
                    'auth_info' => $auth_info
                ])
                ->field('id,qq')
                ->find();
            if(empty($row)){
                return message(t('auth.not_exist') ,false);
            }
            if($row['qq'] != $qq){
                return message(t('auth.qq_mismatch') ,false);
            }
            $cacheKey = $this->verificationCacheKey($appid, (string)$auth_info, (string)$qq);
            $attemptKey = $cacheKey . ':attempts';
            $attempts = (int)Cache::get($attemptKey, 0);
            if ($attempts >= 5) {
                Cache::delete($cacheKey);
                return message(t('login.code_attempts_exceeded'), false);
            }
            Cache::set($attemptKey, $attempts + 1, 300);
            $cacheCode = (string)Cache::get($cacheKey, '');
            if(empty($cacheCode)){
                return message(t('download.code_missing_or_expired') ,false);
            }
            if(!hash_equals($cacheCode, (string)$code)){
                return message(t('download.code_invalid') ,false);
            }
            Cache::delete($cacheKey);
            Cache::delete($attemptKey);
            $ticket = ApplicationInstallerService::issueDownloadTicket(
                $appid,
                (int)$row['id'],
                (string)get_client_ip()
            );
            if (empty($ticket['ok'])) {
                return message((string)$ticket['msg'], false);
            }
            return message(t('app.download_link_success') ,true, [
                'url' => SITE_URL.'/api.php/Download/download/?sign='.$ticket['ticket'],
            ]);
        }
    }

    private function verificationCacheKey(int $appid, string $authInfo, string $qq): string
    {
        return 'download_verification:' . hash('sha256', $appid . '|' . $authInfo . '|' . $qq);
    }
}
