<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\common\service\RebateRiskService;
use app\common\service\RebateSettlementService;
use app\user\model\DiscountCodeModel;
use app\user\model\RebateModel;
use think\Exception;

class RebateService extends UserBaseService
{
    /** @var DiscountCodeModel */
    protected $discountCodeModel;

    /** @var RebateModel */
    protected $rebateModel;

    public function __construct()
    {
        $this->discountCodeModel = new DiscountCodeModel();
        $this->rebateModel = new RebateModel();
    }

    public function generateCode()
    {
        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }
        $powerInfo = $this->getPowerPriceInfo($userInfo['power']);
        if (!$powerInfo || empty($powerInfo['discount_code_enabled'])) {
            throw new Exception(t('rebate.permission_disabled'));
        }

        $code = $this->discountCodeModel->generateCode($userInfo['id']);
        return $code;
    }

    public function getMyCode()
    {
        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }
        return $this->discountCodeModel->getByUserId($userInfo['id']);
    }

    public function getMyRebateList()
    {
        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }
        RebateSettlementService::settleMaturedForUser(intval($userInfo['id']));
        return $this->rebateModel->getListByReferrer($userInfo['id']);
    }

    public function getMySummary(): array
    {
        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }

        RebateSettlementService::settleMaturedForUser(intval($userInfo['id']));

        $rows = $this->rebateModel
            ->where('referrer_user_id', $userInfo['id'])
            ->field('rebate_amount,status,payer_user_id')
            ->select()
            ->toArray();

        $totalAmount = '0.00';
        $pendingAmount = '0.00';
        foreach ($rows as $row) {
            $amount = qh_money_format($row['rebate_amount'] ?? 0);
            if (($row['status'] ?? '') === 'settled') {
                $totalAmount = qh_money_add($totalAmount, $amount);
            } elseif (($row['status'] ?? '') === 'pending') {
                $pendingAmount = qh_money_add($pendingAmount, $amount);
            }
        }

        return [
            'total_amount' => $totalAmount,
            'pending_amount' => $pendingAmount,
            'withdrawable_balance' => qh_money_format($userInfo['withdrawable_balance'] ?? 0),
            'usage_count' => count($rows),
            'hold_days' => RebateRiskService::holdDays(),
        ];
    }
}
