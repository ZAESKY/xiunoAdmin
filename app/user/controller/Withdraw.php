<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\model\BalanceLogModel;
use app\common\model\NotificationModel;
use app\common\model\WithdrawModel;
use think\facade\Db;
use think\facade\View;

class Withdraw extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->denyIfClosed();
    }

    private function denyIfClosed()
    {
        if (!feature_enabled('feature_withdraw_enabled')) {
            if (IS_POST) {
                exit(json_encode(message('提现功能已关闭', false), JSON_UNESCAPED_UNICODE));
            }
            exit($this->render('/public/error', ['msg' => '提现功能已关闭']));
        }
    }

    public function index()
    {
        if (IS_POST) {
            $limit = input('post.limit', 15, 'intval');
            $page = input('post.current_page', 1, 'intval');
            $list = Db::name('withdraw')
                ->where('user_id', $this->userId)
                ->order('id', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);
            return json(message('ok', true, [
                'data' => $list->items(),
                'total' => $list->total(),
            ]));
        }
        return $this->render();
    }

    public function apply()
    {
        if (!IS_POST) return json(message('非法请求', false));

        // Check withdraw enabled
        if (!intval(Db::name('config')->where('name', 'withdraw_enable')->value('value'))) {
            return json(message('提现功能暂未开放', false));
        }

        $userId = $this->userId;
        $user = Db::name('user')->where('id', $userId)->find();
        $balance = floatval($user['balance']);

        // Check min amount
        $minAmount = floatval(Db::name('config')->where('name', 'withdraw_min_amount')->value('value'));
        if ($minAmount <= 0) $minAmount = 10;
        if ($balance < $minAmount) {
            return json(message("余额不足，最低提现金额为 {$minAmount} 元", false));
        }

        // Check interval
        $interval = intval(Db::name('config')->where('name', 'withdraw_interval')->value('value'));
        if ($interval > 0) {
            $lastTime = WithdrawModel::getLastApprovedTime($userId);
            if ($lastTime > 0 && (time() - $lastTime) < $interval * 3600) {
                $hours = $interval;
                return json(message("提现间隔未到，每 {$hours} 小时只能提现一次", false));
            }
        }

        // Check no pending application
        $pending = WithdrawModel::getUserLast($userId);
        if ($pending && in_array($pending['status'], ['pending'])) {
            return json(message('您已有提现申请正在处理中，请勿重复提交', false));
        }

        $amount = floatval(input('post.amount', 0));
        if ($amount <= 0) {
            return json(message('提现金额不能为0', false));
        }
        if ($amount < $minAmount) {
            return json(message("提现金额不能低于 {$minAmount} 元", false));
        }
        if ($amount > $balance) {
            return json(message('提现金额不能超过账户余额', false));
        }

        $phone = trim(input('post.phone', ''));
        $realName = trim(input('post.real_name', ''));
        $remark = trim(input('post.remark', ''));

        if (empty($phone) || strlen($phone) < 11) {
            return json(message('请填写正确的手机号', false));
        }
        if (empty($realName)) {
            return json(message('请填写真实姓名', false));
        }
        $payMethod = trim(input('post.pay_method', ''));
        $qrImage = trim(input('post.qr_image', ''));
        if (empty($payMethod) || !in_array($payMethod, ['alipay', 'wechat', 'bank'])) {
            return json(message('请选择收款方式', false));
        }
        if (empty($qrImage)) {
            return json(message('请上传收款码图片', false));
        }

        Db::startTrans();
        try {
            $amount = round($amount, 2);
            $now = datetime();

            // Deduct balance
            $newBalance = round($balance - $amount, 2);
            Db::name('user')->where('id', $userId)->data(['balance' => $newBalance])->update();

            // Balance log
            BalanceLogModel::add($userId, 'withdraw_apply', -$amount,
                "提现申请 -{$amount} 元，待审核");

            // Withdraw record
            $withdrawId = Db::name('withdraw')->insertGetId([
                'user_id' => $userId,
                'amount' => $amount,
                'phone' => $phone,
                'real_name' => $realName,
                'pay_method' => $payMethod,
                'qr_image' => $qrImage,
                'user_remark' => $remark,
                'status' => 'pending',
                'applied_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Db::commit();

            // Notify admin
            NotificationModel::add([
                'user_id' => 0,
                'title' => '新的提现申请',
                'content' => "用户 {$user['username']} 申请提现 {$amount} 元",
                'type' => 'withdraw_new',
                'link' => '/Order/withdraw.html',
                'created_at' => $now,
            ]);

            return json(message('提现申请已提交，请等待审核', true));
        } catch (\Exception $e) {
            Db::rollback();
            return json(message('提交失败：' . $e->getMessage(), false));
        }
    }

    public function cancel()
    {
        if (!IS_POST) return json(message('非法请求', false));

        $id = input('post.id', 0, 'intval');
        $userId = $this->userId;

        $row = Db::name('withdraw')->where('id', $id)->where('user_id', $userId)->find();
        if (!$row) return json(message('提现记录不存在', false));
        if ($row['status'] !== 'pending') return json(message('当前状态不可撤回', false));

        Db::startTrans();
        try {
            $amount = floatval($row['amount']);
            $now = datetime();

            // Return balance
            $user = Db::name('user')->where('id', $userId)->find();
            $newBalance = round(floatval($user['balance']) + $amount, 2);
            Db::name('user')->where('id', $userId)->data(['balance' => $newBalance])->update();

            // Balance log
            BalanceLogModel::add($userId, 'withdraw_cancel', $amount,
                "提现撤回，返还 {$amount} 元");

            // Update status
            Db::name('withdraw')->where('id', $id)->data([
                'status' => 'withdrawn',
                'withdrawn_at' => $now,
                'updated_at' => $now,
            ])->update();

            Db::commit();

            return json(message('提现已撤回，余额已返还', true));
        } catch (\Exception $e) {
            Db::rollback();
            return json(message('撤回失败：' . $e->getMessage(), false));
        }
    }

    public function check()
    {
        if (!IS_POST) return json(message('非法请求', false));

        $userId = $this->userId;

        // Check enabled (direct DB to avoid cache issues)
        $wdEnable = Db::name('config')->where('name', 'withdraw_enable')->value('value');
        if (!intval($wdEnable)) {
            return json(['code' => -1, 'msg' => '提现功能暂未开放', 'reason' => 'disabled']);
        }

        $user = Db::name('user')->where('id', $userId)->find();
        $balance = floatval($user['balance']);

        // Check min (direct DB to avoid cache issues)
        $minAmount = floatval(Db::name('config')->where('name', 'withdraw_min_amount')->value('value'));
        if ($minAmount <= 0) $minAmount = 10;
        if ($balance < $minAmount) {
            return json(['code' => -1, 'msg' => "当前余额 ¥{$balance}，未达到最低提现金额 ¥{$minAmount}", 'reason' => 'min']);
        }

        // Check pending
        $pending = WithdrawModel::getUserLast($userId);
        if ($pending && $pending['status'] === 'pending') {
            return json(['code' => -1, 'msg' => '您已有提现申请正在处理中，请等待处理完成后再提交', 'reason' => 'pending']);
        }

        // Check interval
        $interval = intval(Db::name('config')->where('name', 'withdraw_interval')->value('value'));
        if ($interval > 0) {
            $lastTime = WithdrawModel::getLastApprovedTime($userId);
            if ($lastTime > 0 && (time() - $lastTime) < $interval * 3600) {
                $nextTime = date('Y-m-d H:i:s', $lastTime + $interval * 3600);
                return json(['code' => -1, 'msg' => "距上次提现未满 {$interval} 小时，下次可提现时间：{$nextTime}", 'reason' => 'interval']);
            }
        }

        return json(message('ok', true, ['balance' => $balance, 'min' => $minAmount]));
    }
}
