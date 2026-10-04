<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\service\PhoneVerificationService;

class Phone extends UserBackend
{
    public function status()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false));
        }
        $status = PhoneVerificationService::status(intval($this->userId));
        $scene = trim((string)input('post.scene/s', ''));
        $status['required'] = PhoneVerificationService::requiredFor($scene);
        return json(message('ok', true, $status));
    }

    public function sendCode()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false));
        }
        $result = PhoneVerificationService::sendCode(
            intval($this->userId),
            trim((string)input('post.scene/s', '')),
            trim((string)input('post.phone/s', ''))
        );
        return json(message($result['message'], $result['ok'], [
            'expires_in' => intval($result['expires_in'] ?? 0),
        ]));
    }

    public function bind()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false));
        }
        $result = PhoneVerificationService::bindPhone(
            intval($this->userId),
            trim((string)input('post.phone/s', '')),
            trim((string)input('post.code/s', ''))
        );
        return json(message($result['message'], $result['ok'], [
            'phone_masked' => (string)($result['phone_masked'] ?? ''),
        ]));
    }
}
