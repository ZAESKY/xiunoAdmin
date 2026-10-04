<?php
namespace app\user\service;

use app\api\service\LicenseV2Service;
use app\user\model\AuthModel;
use app\common\service\UserBaseService;
use think\Exception;

class AuthService extends UserBaseService
{
    /** @var object */
    private $offlineIssuer;

    public function __construct($offlineIssuer = null) {
        $this->model = new AuthModel();
        $this->offlineIssuer = $offlineIssuer ?: new LicenseV2Service();
    }

    public function list() {
        try{
            $result = $this->model->list();
            foreach($result as $res){
                try {
                    $appInfo = parent::getAppInfo($res['appid']);
                } catch (\Throwable $e) {
                    $appInfo = false;
                }
                if (!$appInfo) {
                    $res['appName'] = t('common.load_failed');
                    $res['remainderReplacePrice'] = t('common.load_failed');
                    continue;
                }
                $res['appName'] = $appInfo['name'];
                $remainderReplaceNumber = (int)$appInfo['free_replace_number'] - (int)$res['replace_number'];
                $res['remainderReplacePrice'] = '￥ '.($remainderReplaceNumber > 0 ? 0 : $appInfo['replace_money']);
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 为当前登录用户签发离线激活凭证，并固定对外错误格式。
     */
    public function offlineIssue(array $input, int $userId): array
    {
        if ($userId <= 0) {
            return $this->offlineError('AUTH_REQUIRED', t('offline_auth.login_required'));
        }

        $authcode = $input['authcode'] ?? '';
        $requestCode = $input['request_code'] ?? '';
        if (!is_string($authcode) || !is_string($requestCode)) {
            return $this->offlineError('INVALID_INPUT', t('offline_auth.invalid_input'));
        }

        $authcode = trim($authcode);
        $requestCode = trim($requestCode);
        if ($authcode === '') {
            return $this->offlineError('AUTHCODE_REQUIRED', t('offline_auth.authcode_required'));
        }
        if ($requestCode === '') {
            return $this->offlineError('REQUEST_CODE_REQUIRED', t('offline_auth.request_code_required'));
        }
        if (strlen($authcode) > 128 || strlen($requestCode) > 16384) {
            return $this->offlineError('INPUT_TOO_LONG', t('offline_auth.input_length_invalid'));
        }

        $result = $this->offlineIssuer->offlineIssue([
            'authcode' => $authcode,
            'request_code' => $requestCode,
        ], $userId);
        if (!is_array($result) || empty($result['ok'])) {
            return $this->offlineFailure(is_array($result) ? $result : []);
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $activationCode = $data['activation_code'] ?? '';
        if (!is_string($activationCode) || $activationCode === '') {
            return $this->offlineError('SIGNING_UNAVAILABLE', t('offline_auth.signing_unavailable'), 5000);
        }

        return message('offline_auth.credential_generated', true, [
            'activation_code' => $activationCode,
            'expires_in' => max(0, (int)($data['expires_in'] ?? 0)),
            'site_id' => is_string($data['site_id'] ?? null) ? $data['site_id'] : '',
        ]);
    }

    private function offlineFailure(array $result): array
    {
        $message = (string)($result['msg'] ?? '');
        $map = [
            '申请码无效或已过期' => ['INVALID_REQUEST_CODE', t('offline_auth.request_code_invalid')],
            '授权码格式错误' => ['INVALID_AUTHCODE_FORMAT', t('offline_auth.authcode_format_invalid')],
            '授权码无效' => ['INVALID_AUTHCODE', t('offline_auth.authcode_invalid')],
            '该授权不属于当前账号' => ['LICENSE_NOT_OWNED', t('offline_auth.license_not_owned')],
            '该授权已绑定其它域名' => ['SITE_ALREADY_BOUND', t('offline_auth.site_already_bound')],
            '绑定名额已用尽' => ['SITE_ALREADY_BOUND', t('offline_auth.site_already_bound')],
            '该产品尚未在授权站配置' => ['PRODUCT_NOT_CONFIGURED', t('offline_auth.product_not_configured')],
            '该旧授权已经升级，请使用升级后的授权码' => ['LICENSE_ALREADY_MIGRATED', t('offline_auth.license_already_migrated')],
            '授权迁移失败，请稍后重试' => ['MIGRATION_FAILED', t('offline_auth.migration_failed')],
        ];
        foreach ($map as $prefix => $mapped) {
            if (str_starts_with($message, $prefix)) {
                return $this->offlineError($mapped[0], $mapped[1], (int)($result['code'] ?? 4000));
            }
        }
        if (str_starts_with($message, '该授权当前状态不可激活')) {
            return $this->offlineError('LICENSE_UNAVAILABLE', t('offline_auth.license_unavailable'), 4003);
        }
        return $this->offlineError('SIGNING_UNAVAILABLE', t('offline_auth.signing_unavailable'), 5000);
    }

    private function offlineError(string $errorCode, string $messageText, int $code = 4000): array
    {
        return message($messageText, false, ['error_code' => $errorCode], $code);
    }
}
