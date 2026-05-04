<?php

namespace app\admin\controller;

use app\common\controller\Backend;
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
        if (IS_POST) {
            $post = $this->request->post();
            $action = $post['action'] ?? 'list';

            if ($action === 'approve') {
                $id = intval($post['id'] ?? 0);
                $remark = trim($post['remark'] ?? '');
                $image = trim($post['image'] ?? '');
                if (empty($remark)) return json(message('请填写处理备注', false));
                if (empty($image)) return json(message('请上传转账凭证', false));
                $row = \think\facade\Db::name('withdraw')->where('id', $id)->find();
                if (!$row) return json(message('记录不存在', false));
                if ($row['status'] !== 'pending') return json(message('当前状态不可处理', false));
                \think\facade\Db::name('withdraw')->where('id', $id)->data([
                    'status' => 'approved', 'admin_remark' => $remark,
                    'transfer_image' => $image, 'handled_at' => datetime(), 'updated_at' => datetime(),
                ])->update();
                // Notify user
                \app\common\model\NotificationModel::add([
                    'user_id' => intval($row['user_id']), 'title' => '提现已处理',
                    'content' => "您的提现申请 {$row['amount']} 元已处理，备注：{$remark}",
                    'type' => 'withdraw_approved', 'link' => '/Withdraw/index.html', 'created_at' => datetime(),
                ]);
                return json(message('处理成功', true));
            }

            if ($action === 'reject') {
                $id = intval($post['id'] ?? 0);
                $remark = trim($post['remark'] ?? '');
                if (empty($remark)) return json(message('请填写驳回原因', false));
                $row = \think\facade\Db::name('withdraw')->where('id', $id)->find();
                if (!$row) return json(message('记录不存在', false));
                if ($row['status'] !== 'pending') return json(message('当前状态不可处理', false));
                \think\facade\Db::startTrans();
                try {
                    $amount = floatval($row['amount']);
                    $user = \think\facade\Db::name('user')->where('id', intval($row['user_id']))->find();
                    $newBalance = round(floatval($user['balance']) + $amount, 2);
                    \think\facade\Db::name('user')->where('id', intval($row['user_id']))->data(['balance' => $newBalance])->update();
                    \app\common\model\BalanceLogModel::add(intval($row['user_id']), 'withdraw_reject', $amount, "提现驳回，返还 {$amount} 元，原因：{$remark}");
                    \think\facade\Db::name('withdraw')->where('id', $id)->data([
                        'status' => 'rejected', 'admin_remark' => $remark,
                        'handled_at' => datetime(), 'updated_at' => datetime(),
                    ])->update();
                    \think\facade\Db::commit();
                    \app\common\model\NotificationModel::add([
                        'user_id' => intval($row['user_id']), 'title' => '提现被驳回',
                        'content' => "您的提现申请 {$amount} 元已被驳回，原因：{$remark}，金额已返还余额",
                        'type' => 'withdraw_rejected', 'link' => '/Withdraw/index.html', 'created_at' => datetime(),
                    ]);
                    return json(message('驳回成功，金额已返还用户', true));
                } catch (\Exception $e) {
                    \think\facade\Db::rollback();
                    return json(message($e->getMessage(), false));
                }
            }

            // List
            $limit = input('post.limit', 15, 'intval');
            $page = input('post.current_page', 1, 'intval');
            $query = \think\facade\Db::name('withdraw')->alias('w')
                ->join('user u', 'w.user_id = u.id', 'left')
                ->order('w.id', 'desc')
                ->field('w.*, u.username');
            $list = $query->paginate(['list_rows' => $limit, 'page' => $page]);
            return json(message('ok', true, ['data' => $list->items(), 'total' => $list->total()]));
        }
        return $this->render();
    }
}