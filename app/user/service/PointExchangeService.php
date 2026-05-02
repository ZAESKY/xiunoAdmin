<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\user\model\PointExchangeModel;
use think\Exception;

class PointExchangeService extends UserBaseService
{
    public function __construct()
    {
        $this->model = new PointExchangeModel();
    }

    public function exchange()
    {
        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }
        $this->model->exchange((int)$userInfo['id'], (int)$userInfo['appid']);
        return message('兑换成功', true);
    }

    public function myRecords()
    {
        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            throw new Exception(t('user.info_error'));
        }
        return $this->model->myRecords((int)$userInfo['id']);
    }
}
