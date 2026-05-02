<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\user\model\DiscountCodeModel;
use app\user\model\RebateModel;
use think\Exception;

class RebateService extends UserBaseService
{
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
            throw new Exception('您当前权限未开启折扣码功能');
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
        return $this->rebateModel->getListByReferrer($userInfo['id']);
    }
}
