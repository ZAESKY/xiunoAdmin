<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\service\PhoneVerificationService;
use app\common\service\RebateRiskService;
use app\user\service\RebateService;
use think\facade\View;

class Rebate extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new RebateService();
    }

    public function checkCode()
    {
        try {
            if (IS_POST) {
                $code = trim(request()->post('code', ''));
                if (empty($code)) return json(message('rebate.code_required', false));
                $codeRow = \think\facade\Db::name('discount_code')->where('code', $code)->find();
                if (!$codeRow || $codeRow['status'] != 1) return json(message('rebate.code_invalid_or_disabled', false));
                $riskReason = RebateRiskService::relatedAccountReason(
                    intval($this->userId),
                    intval($codeRow['user_id'])
                );
                if ($riskReason !== '') return json(message('rebate.code_unavailable_for_account', false));
                $ownerUser = \think\facade\Db::name('user')->where('id', $codeRow['user_id'])->find();
                if (!$ownerUser) return json(message('rebate.code_invalid', false));
                $ownerPower = \think\facade\Db::name('power_price')->where('id', $ownerUser['power'])->find();
                if (!$ownerPower || $ownerPower['rebate_enabled'] != 1) return json(message('rebate.owner_disabled', false));
                return json(message('ok', true, [
                    'rate' => floatval($ownerPower['rebate_rate']),
                    'owner' => $ownerUser['username'],
                ]));
            }
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function index()
    {
        try {
            if (IS_POST) {
                return json(message(t('common.system_busy'), false));
            }
            return $this->render();
        } catch (\Exception $e) {
            return $this->render('public/error', ['msg' => $e->getMessage()]);
        }
    }

    public function generate()
    {
        try {
            if (IS_POST) {
                if (empty($this->myPowerInfo['discount_code_enabled'])) {
                    return json(message('rebate.permission_disabled', false));
                }
                if (PhoneVerificationService::requiredFor('rebate')) {
                    $verified = PhoneVerificationService::verifySensitiveCode(
                        intval($this->userId),
                        'rebate',
                        trim((string)input('post.sms_code/s', ''))
                    );
                    if (!$verified['ok']) {
                        return json(message($verified['message'], false));
                    }
                }
                $code = $this->service->generateCode();
                return json(message('rebate.generate_success', true, $code));
            }
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function myCode()
    {
        try {
            if (IS_POST) {
                $code = $this->service->getMyCode();
                return json(message('ok', true, $code));
            }
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function mySummary()
    {
        try {
            if (IS_POST) {
                return json(message('ok', true, $this->service->getMySummary()));
            }
            return json(message('common.illegal_request', false));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function myRebateList()
    {
        try {
            if (IS_POST) {
                $list = $this->service->getMyRebateList();
                return json(message('ok', true, $list));
            }
            return $this->render('rebate/index');
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }
}
