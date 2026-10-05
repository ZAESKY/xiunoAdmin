<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\common\model\BalanceLogModel;
use app\common\model\NotificationModel;
use app\common\model\WithdrawModel;
use app\common\service\RebateSettlementService;
use app\common\service\PhoneVerificationService;
use app\common\service\WithdrawableBalanceService;
use think\facade\Db;
use think\facade\Log;
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
                exit(json_encode(message('withdraw.feature_closed', false), JSON_UNESCAPED_UNICODE));
            }
            exit($this->render('/public/error', ['msg' => t('withdraw.feature_closed')]));
        }
    }

    public function index()
    {
        if (IS_POST) {
            $limit = qh_page_limit(input('post.limit', null), 15);
            $page = qh_page_number(input('post.current_page', null));
            $list = Db::name('withdraw')
                ->where('user_id', $this->userId)
                ->order('id', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);
            return json(message('ok', true, [
                'data' => array_map([$this, 'sanitizeWithdrawRow'], $list->items()),
                'total' => $list->total(),
            ]));
        }
        return $this->render();
    }

    public function apply()
    {
        if (!IS_POST) return json(message('common.illegal_request', false));

        // Check withdraw enabled
        if (!intval(Db::name('config')->where('name', 'withdraw_enable')->value('value'))) {
            return json(message('withdraw.temporarily_unavailable', false));
        }

        $userId = $this->userId;
        try {
            RebateSettlementService::settleMaturedForUser(intval($userId));
        } catch (\Throwable $e) {
            Log::error('Matured rebate settlement before withdrawal failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => intval($userId),
            ]);
            return json(message('withdraw.settlement_busy', false));
        }
        $user = Db::name('user')->where('id', $userId)->find();
        if (!$user) {
            return json(message('withdraw.user_not_found', false));
        }
        $balance = qh_money_format($user['withdrawable_balance'] ?? 0);
        $balanceCents = qh_money_to_cents($balance);

        // Check min amount
        $minAmount = qh_money_format(Db::name('config')->where('name', 'withdraw_min_amount')->value('value') ?: '10.00');
        if (qh_money_to_cents($minAmount) <= 0) $minAmount = '10.00';
        $minAmountCents = qh_money_to_cents($minAmount);
        if ($balanceCents < $minAmountCents) {
            return json(message(t('withdraw.balance_below_minimum', ['amount' => $minAmount]), false));
        }

        // Check interval
        $interval = intval(Db::name('config')->where('name', 'withdraw_interval')->value('value'));
        if ($interval > 0) {
            $lastTime = WithdrawModel::getLastApprovedTime($userId);
            if ($lastTime > 0 && (time() - $lastTime) < $interval * 3600) {
                $hours = $interval;
                return json(message(t('withdraw.interval_not_reached', ['hours' => $hours]), false));
            }
        }

        // Check no pending application
        $pending = WithdrawModel::getUserLast($userId);
        if ($pending && in_array($pending['status'], ['pending'])) {
            return json(message('withdraw.pending_duplicate', false));
        }

        try {
            $amount = qh_money_format(input('post.amount', '0'));
        } catch (\InvalidArgumentException $e) {
            return json(message('withdraw.amount_format_error', false));
        }
        $amountCents = qh_money_to_cents($amount);
        if ($amountCents <= 0) {
            return json(message('withdraw.amount_zero', false));
        }
        if ($amountCents < $minAmountCents) {
            return json(message(t('withdraw.below_minimum_plain', ['amount' => $minAmount]), false));
        }
        if ($amountCents > $balanceCents) {
            return json(message('withdraw.exceeds_available', false));
        }

        $smsRequired = PhoneVerificationService::requiredFor('withdraw');
        $phoneStatus = PhoneVerificationService::status(intval($userId));
        $phone = $smsRequired
            ? (string)($phoneStatus['phone'] ?? '')
            : trim((string)input('post.phone', ''));
        $realName = qh_plain_text(input('post.real_name', ''), 50);
        $remark = qh_plain_text(input('post.remark', ''), 200);

        if ($smsRequired && empty($phoneStatus['verified'])) {
            return json(message('withdraw.bind_verified_phone', false));
        }
        if (!preg_match('/^1[3-9][0-9]{9}$/D', $phone)) {
            return json(message('withdraw.invalid_phone_plain', false));
        }
        if (empty($realName)) {
            return json(message('withdraw.real_name_required', false));
        }
        $payMethod = trim((string)input('post.pay_method', ''));
        $qrImage = qh_safe_url(input('post.qr_image', ''), true);
        if (empty($payMethod) || !in_array($payMethod, ['alipay', 'wechat', 'bank'], true)) {
            return json(message('withdraw.payment_method_required', false));
        }
        if ($qrImage === '' || !preg_match('#^/upload/[A-Za-z0-9/_-]+\.(?:jpe?g|png|gif|webp)$#iD', $qrImage)) {
            return json(message('withdraw.qr_image_required', false));
        }

        Db::startTrans();
        try {
            $now = datetime();

            // Serialize all balance-changing withdrawal operations per user.
            $lockedUser = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$lockedUser) {
                throw new \RuntimeException('user not found');
            }
            $lockedBalance = qh_money_format($lockedUser['balance']);
            $lockedWithdrawableBalance = qh_money_format($lockedUser['withdrawable_balance'] ?? 0);
            if (
                $amountCents > qh_money_to_cents($lockedWithdrawableBalance)
                || $amountCents > qh_money_to_cents($lockedBalance)
            ) {
                Db::rollback();
                return json(message('withdraw.exceeds_available', false));
            }
            $pending = Db::name('withdraw')
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->lock(true)
                ->find();
            if ($pending) {
                Db::rollback();
                return json(message('withdraw.pending_duplicate', false));
            }
            if ($interval > 0) {
                $lastTime = WithdrawModel::getLastApprovedTime($userId);
                if ($lastTime > 0 && (time() - $lastTime) < $interval * 3600) {
                    Db::rollback();
                    return json(message(t('withdraw.interval_not_reached', ['hours' => $interval]), false));
                }
            }

            if ($smsRequired) {
                $smsResult = PhoneVerificationService::verifySensitiveCode(
                    intval($userId),
                    'withdraw',
                    trim((string)input('post.sms_code', ''))
                );
                if (!$smsResult['ok']) {
                    Db::rollback();
                    return json(message($smsResult['message'], false));
                }
            }

            // 提现只允许扣减收益余额，同时从总余额中扣除同额资金。
            $newBalance = qh_money_subtract($lockedBalance, $amount);
            $newWithdrawableBalance = qh_money_subtract($lockedWithdrawableBalance, $amount);
            $updated = Db::name('user')->where('id', $userId)->data([
                'balance' => $newBalance,
                'withdrawable_balance' => $newWithdrawableBalance,
            ])->update();
            if ($updated !== 1) {
                throw new \RuntimeException('withdrawal balance update failed');
            }

            // Balance log
            BalanceLogModel::add($userId, 'withdraw_apply', qh_money_from_cents(-qh_money_to_cents($amount)),
                t('withdraw.balance_log_apply', ['amount' => $amount]));

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
                'title' => t('withdraw.notification_new_title'),
                'content' => t('withdraw.notification_new_content', ['username' => $lockedUser['username'], 'amount' => $amount]),
                'type' => 'withdraw_new',
                'link' => '/Order/withdraw.html',
                'variables' => ['username' => $lockedUser['username'], 'amount' => $amount],
                'created_at' => $now,
            ]);

            return json(message('withdraw.submit_success', true));
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('Withdraw application failed: ' . $e->getMessage(), ['exception' => $e, 'user_id' => intval($userId)]);
            return json(message('withdraw.submit_failed', false));
        }
    }

    public function cancel()
    {
        if (!IS_POST) return json(message('common.illegal_request', false));

        $id = input('post.id', 0, 'intval');
        Db::startTrans();
        try {
            $row = Db::name('withdraw')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->lock(true)
                ->find();
            if (!$row) {
                Db::rollback();
                return json(message('withdraw.record_not_found', false));
            }
            if ($row['status'] !== 'pending') {
                Db::rollback();
                return json(message('withdraw.cannot_cancel', false));
            }
            $amount = qh_money_format($row['amount']);
            $now = datetime();

            $user = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$user) {
                throw new \RuntimeException('user not found');
            }

            // Claim the pending record before returning its balance.
            $updated = Db::name('withdraw')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->data([
                    'status' => 'withdrawn',
                    'withdrawn_at' => $now,
                    'updated_at' => $now,
                ])->update();
            if ($updated !== 1) {
                Db::rollback();
                return json(message('withdraw.cannot_cancel', false));
            }

            // 撤回时同时恢复总余额和可提现收益余额。
            if (!WithdrawableBalanceService::restoreWithdrawal(
                intval($userId),
                $amount,
                'withdraw_cancel',
                t('withdraw.balance_log_cancel', ['amount' => $amount])
            )) {
                throw new \RuntimeException('withdrawal balance restore failed');
            }

            Db::commit();

            return json(message('withdraw.cancel_success', true));
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('Withdraw cancellation failed: ' . $e->getMessage(), ['exception' => $e, 'user_id' => intval($userId), 'withdraw_id' => intval($id)]);
            return json(message('withdraw.cancel_failed', false));
        }
    }

    public function check()
    {
        if (!IS_POST) return json(message('common.illegal_request', false));

        $userId = $this->userId;

        try {
            RebateSettlementService::settleMaturedForUser(intval($userId));
        } catch (\Throwable $e) {
            Log::error('Matured rebate settlement before withdrawal check failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => intval($userId),
            ]);
            return json(message('withdraw.settlement_busy', false));
        }

        $user = Db::name('user')->where('id', $userId)->find();
        if (!$user) {
            return json(message('withdraw.user_not_found', false));
        }
        $accountBalance = qh_money_format($user['balance']);
        $balance = qh_money_format($user['withdrawable_balance'] ?? 0);

        // Always return the configured threshold so the page can display it even
        // when the current account does not yet meet the withdrawal conditions.
        $minAmount = qh_money_format(Db::name('config')->where('name', 'withdraw_min_amount')->value('value') ?: '10.00');
        if (qh_money_to_cents($minAmount) <= 0) $minAmount = '10.00';
        $conditionData = [
            'balance' => $balance,
            'withdrawable_balance' => $balance,
            'account_balance' => $accountBalance,
            'min' => $minAmount,
        ];
        $phoneStatus = PhoneVerificationService::status(intval($userId));
        $conditionData['sms_required'] = PhoneVerificationService::requiredFor('withdraw');
        $conditionData['phone_verified'] = !empty($phoneStatus['verified']);
        $conditionData['phone_masked'] = (string)($phoneStatus['phone_masked'] ?? '');

        // Check enabled (direct DB to avoid cache issues)
        $wdEnable = Db::name('config')->where('name', 'withdraw_enable')->value('value');
        if (!intval($wdEnable)) {
            return json(['code' => -1, 'msg' => t('withdraw.temporarily_unavailable'), 'reason' => 'disabled', 'data' => $conditionData]);
        }
        if ($conditionData['sms_required'] && !$conditionData['phone_verified']) {
            return json(['code' => -1, 'msg' => t('withdraw.bind_verified_phone'), 'reason' => 'phone_unverified', 'data' => $conditionData]);
        }
        if (qh_money_to_cents($balance) < qh_money_to_cents($minAmount)) {
            return json(['code' => -1, 'msg' => t('withdraw.current_below_minimum', ['balance' => $balance, 'min' => $minAmount]), 'reason' => 'min', 'data' => $conditionData]);
        }

        // Check pending
        $pending = WithdrawModel::getUserLast($userId);
        if ($pending && $pending['status'] === 'pending') {
            return json(['code' => -1, 'msg' => t('withdraw.pending_wait'), 'reason' => 'pending', 'data' => $conditionData]);
        }

        // Check interval
        $interval = intval(Db::name('config')->where('name', 'withdraw_interval')->value('value'));
        if ($interval > 0) {
            $lastTime = WithdrawModel::getLastApprovedTime($userId);
            if ($lastTime > 0 && (time() - $lastTime) < $interval * 3600) {
                $nextTime = date('Y-m-d H:i:s', $lastTime + $interval * 3600);
                return json(['code' => -1, 'msg' => t('withdraw.interval_next_time', ['hours' => $interval, 'time' => $nextTime]), 'reason' => 'interval', 'data' => $conditionData]);
            }
        }

        return json(message('ok', true, $conditionData));
    }

    private function sanitizeWithdrawRow(array $row): array
    {
        $row['phone'] = preg_replace('/[^0-9+ -]/', '', (string)($row['phone'] ?? ''));
        $row['real_name'] = qh_plain_text($row['real_name'] ?? '', 50);
        $row['user_remark'] = qh_plain_text($row['user_remark'] ?? '', 200);
        $row['admin_remark'] = qh_plain_text($row['admin_remark'] ?? '', 500);
        $row['qr_image'] = qh_safe_url($row['qr_image'] ?? '', true);
        $row['transfer_image'] = qh_safe_url($row['transfer_image'] ?? '', true);
        return $row;
    }
}
