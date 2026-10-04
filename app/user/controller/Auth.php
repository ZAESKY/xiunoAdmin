<?php
declare (strict_types = 1);

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\service\RateLimitService;
use app\user\service\AuthService;
use think\facade\Log;
use think\facade\View;

class Auth extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new AuthService();
    }

    public function list()
    {
        $canManageAuth = (int)($this->myPowerInfo['addauth_power'] ?? 0) === 1;
        if (IS_POST) {
            try {
                $result = $this->service->list();
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Throwable $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }

        View::assign('replace_notice', clean_rich_text($this->myAppInfo['replace_notice'] ?? ''));
        View::assign('canManageAuth', $canManageAuth);
        return $this->render('auth/list');
    }

    /**
     * 用户中心离线激活：用主题申请码换取一次性激活凭证。
     */
    public function offlineIssue()
    {
        if (!IS_POST) {
            return json(message('offline_auth.post_only', false, [
                'error_code' => 'METHOD_NOT_ALLOWED',
            ], 405), 405);
        }

        $userId = (int)$this->userId;
        if ($userId <= 0) {
            return json(message('offline_auth.login_required', false, [
                'error_code' => 'AUTH_REQUIRED',
            ], 401), 401);
        }

        $userRate = RateLimitService::hit('offline_issue_user', (string)$userId, 10, 3600);
        $ipRate = RateLimitService::hit('offline_issue_ip', (string)get_client_ip(), 30, 3600);
        if (!$userRate['ok'] || !$ipRate['ok']) {
            $retryAfter = max((int)$userRate['retry_after'], (int)$ipRate['retry_after']);
            return json(message('offline_auth.too_frequent', false, [
                'error_code' => 'RATE_LIMITED',
                'retry_after' => $retryAfter,
            ], 4290));
        }

        try {
            $result = $this->service->offlineIssue([
                'authcode' => request()->post('authcode'),
                'request_code' => request()->post('request_code'),
            ], $userId);
            return json($result);
        } catch (\Throwable $e) {
            // 不记录授权码、申请码、激活凭证或可能携带它们的异常正文。
            Log::error('Offline activation portal request failed', [
                'user_id' => $userId,
                'exception_type' => get_class($e),
            ]);
            return json(message('offline_auth.issue_failed', false, [
                'error_code' => 'SERVICE_ERROR',
            ], 5000));
        }
    }

}
