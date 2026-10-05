<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\service\WithdrawableBalanceService;
use app\admin\service\OrderService;

class Order extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new OrderService();
    }

    public function withdraw()
    {
        if (!feature_enabled('feature_withdraw_enabled')) {
            if (IS_POST) {
                return json(message('withdraw.feature_closed', false));
            }
            return $this->render('/public/error', ['msg' => t('withdraw.feature_closed')]);
        }

        if (IS_POST) {
            $post = $this->request->post();
            $action = $post['action'] ?? 'list';

            if ($action === 'approve') {
                $id = intval($post['id'] ?? 0);
                $remark = qh_plain_text($post['remark'] ?? '', 500);
                $image = qh_safe_url($post['image'] ?? '', true);
                if ($id <= 0) return json(message('withdraw.record_not_found', false));
                if (empty($remark)) return json(message('withdraw.process_remark_required', false));
                if (empty($image)) return json(message('withdraw.transfer_proof_required', false));
                try {
                    $row = \think\facade\Db::transaction(function () use ($id, $remark, $image) {
                        $locked = \think\facade\Db::name('withdraw')->where('id', $id)->lock(true)->find();
                        if (!$locked) throw new \RuntimeException(t('withdraw.record_not_found'));
                        if ((string)$locked['status'] !== 'pending') throw new \RuntimeException(t('withdraw.status_not_processable'));
                        $updated = \think\facade\Db::name('withdraw')
                            ->where('id', $id)
                            ->where('status', 'pending')
                            ->data([
                                'status' => 'approved', 'admin_remark' => $remark,
                                'transfer_image' => $image, 'handled_at' => datetime(), 'updated_at' => datetime(),
                            ])->update();
                        if ($updated !== 1) throw new \RuntimeException(t('withdraw.status_not_processable'));
                        return $locked;
                    });
                } catch (\Throwable $e) {
                    return json(message($e->getMessage(), false));
                }
                // Notify user
                \app\common\model\NotificationModel::add([
                    'user_id' => intval($row['user_id']), 'title' => t('withdraw.notification_approved_title'),
                    'content' => t('withdraw.notification_approved_content', ['amount' => $row['amount'], 'remark' => $remark]),
                    'type' => 'withdraw_approved', 'link' => '/Withdraw/index.html', 'created_at' => datetime(),
                    'variables' => ['amount' => $row['amount'], 'remark' => $remark],
                ]);
                return json(message('feedback.handle_success', true));
            }

            if ($action === 'reject') {
                $id = intval($post['id'] ?? 0);
                $remark = qh_plain_text($post['remark'] ?? '', 500);
                if ($id <= 0) return json(message('withdraw.record_not_found', false));
                if (empty($remark)) return json(message('withdraw.reject_reason_required', false));
                try {
                    $result = \think\facade\Db::transaction(function () use ($id, $remark) {
                        $row = \think\facade\Db::name('withdraw')->where('id', $id)->lock(true)->find();
                        if (!$row) throw new \RuntimeException(t('withdraw.record_not_found'));
                        if ((string)$row['status'] !== 'pending') throw new \RuntimeException(t('withdraw.status_not_processable'));

                        $amount = qh_money_format($row['amount']);
                        $userId = intval($row['user_id']);
                        $user = \think\facade\Db::name('user')->where('id', $userId)->lock(true)->find();
                        if (!$user) throw new \RuntimeException(t('withdraw.user_not_found'));

                        $updated = \think\facade\Db::name('withdraw')
                            ->where('id', $id)
                            ->where('status', 'pending')
                            ->data([
                                'status' => 'rejected', 'admin_remark' => $remark,
                                'handled_at' => datetime(), 'updated_at' => datetime(),
                            ])->update();
                        if ($updated !== 1) throw new \RuntimeException(t('withdraw.status_not_processable'));

                        if (!WithdrawableBalanceService::restoreWithdrawal(
                            $userId,
                            $amount,
                            'withdraw_reject',
                            t('withdraw.balance_log_reject', ['amount' => $amount, 'reason' => $remark])
                        )) {
                            throw new \RuntimeException(t('withdraw.restore_failed'));
                        }
                        return ['row' => $row, 'amount' => $amount];
                    });
                    $row = $result['row'];
                    $amount = $result['amount'];
                    \app\common\model\NotificationModel::add([
                        'user_id' => intval($row['user_id']), 'title' => t('withdraw.notification_rejected_title'),
                        'content' => t('withdraw.notification_rejected_content', ['amount' => $amount, 'reason' => $remark]),
                        'type' => 'withdraw_rejected', 'link' => '/Withdraw/index.html', 'created_at' => datetime(),
                        'variables' => ['amount' => $amount, 'remark' => $remark],
                    ]);
                    return json(message('withdraw.reject_success', true));
                } catch (\Throwable $e) {
                    return json(message($e->getMessage(), false));
                }
            }

            // List
            $limit = qh_page_limit(input('post.limit', null), 15);
            $page = qh_page_number(input('post.current_page', null));
            $query = \think\facade\Db::name('withdraw')->alias('w')
                ->join('user u', 'w.user_id = u.id', 'left')
                ->order('w.id', 'desc')
                ->field('w.*, u.username');
            $list = $query->paginate(['list_rows' => $limit, 'page' => $page]);
            $items = array_map(static function (array $row): array {
                $row['username'] = qh_plain_text($row['username'] ?? '', 150);
                $row['phone'] = preg_replace('/[^0-9+ -]/', '', (string)($row['phone'] ?? ''));
                $row['real_name'] = qh_plain_text($row['real_name'] ?? '', 50);
                $row['user_remark'] = qh_plain_text($row['user_remark'] ?? '', 200);
                $row['admin_remark'] = qh_plain_text($row['admin_remark'] ?? '', 500);
                $row['qr_image'] = qh_safe_url($row['qr_image'] ?? '', true);
                $row['transfer_image'] = qh_safe_url($row['transfer_image'] ?? '', true);
                return $row;
            }, $list->items());
            return json(message('ok', true, ['data' => $items, 'total' => $list->total()]));
        }
        return $this->render();
    }
}
