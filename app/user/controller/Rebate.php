<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
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
                if (empty($code)) return json(message('请输入折扣码', false));
                $codeRow = \think\facade\Db::name('discount_code')->where('code', $code)->find();
                if (!$codeRow || $codeRow['status'] != 1) return json(message('折扣码不存在或已停用', false));
                if ($codeRow['user_id'] == $this->userId) return json(message('不能使用自己的折扣码', false));
                $ownerUser = \think\facade\Db::name('user')->where('id', $codeRow['user_id'])->find();
                if (!$ownerUser) return json(message('折扣码无效', false));
                $ownerPower = \think\facade\Db::name('power_price')->where('id', $ownerUser['power'])->find();
                if (!$ownerPower || $ownerPower['rebate_enabled'] != 1) return json(message('折扣码所属用户未开启返利', false));
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
                $code = $this->service->generateCode();
                return json(message('生成成功', true, $code));
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
            return json(message('非法请求', false));
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
