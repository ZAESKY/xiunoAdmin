<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\SetService;
use app\common\service\CheckinConfigService;
use think\facade\Db;
use think\facade\View;

class Checkin extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->request->filter(['trim']);
    }

    /**
     * 打卡配置页面
     */
    public function config()
    {
        if ($this->request->isPost()) {
            $row = $this->request->post("row/a", [], 'trim,html_entity_decode');
            if ($row) {
                try {
                    [$days, $bonuses] = CheckinConfigService::normalizeMilestones(
                        $row['checkin_consecutive_days'] ?? '',
                        $row['checkin_consecutive_bonus'] ?? ''
                    );
                    $row['checkin_consecutive_days'] = implode(',', $days);
                    $row['checkin_consecutive_bonus'] = implode(',', $bonuses);
                } catch (\InvalidArgumentException $e) {
                    return json(message($e->getMessage(), false));
                }

                $setService = new SetService();
                $configList = [];
                foreach ($setService->all() as $v) {
                    if ($v['group'] !== 'checkin') continue;

                    if ($v['type'] == 'bool') {
                        if (!array_key_exists($v['name'], $row)) continue;
                        $value = (int)$row[$v['name']] === 1 ? 1 : 0;
                    } else {
                        $value = $row[$v['name']] ?? $v['value'];
                    }
                    if (in_array($v['name'], ['checkin_consecutive_days', 'checkin_consecutive_bonus'], true)) {
                        $v['type'] = 'string';
                    }
                    $v['value'] = $value;
                    $configList[] = $v->toArray();
                }
                try {
                    $setService->saveAll($configList);
                } catch (\Exception $e) {
                    return json(message($e->getMessage(), false));
                }
                return json(message(t('system.save_success'), true));
            }
            return json(message(t('system.save_failed'), false));
        }

        $setService = new SetService();
        $configList = [];
        foreach ($setService->all() as $res) {
            if ($res['group'] !== 'checkin') continue;
            $res = $res->toArray();
            if ($res['type'] != 'config') {
                $res['content'] = json_decode($res['content'], true);
            }
            if ($res['name'] === 'checkin_consecutive_days') {
                try {
                    $res['value'] = CheckinConfigService::canonicalize($res['value'], 1);
                } catch (\InvalidArgumentException $e) {
                    $res['value'] = '';
                }
            } elseif ($res['name'] === 'checkin_consecutive_bonus') {
                try {
                    $res['value'] = CheckinConfigService::canonicalize($res['value'], 0);
                } catch (\InvalidArgumentException $e) {
                    $res['value'] = '';
                }
            }
            $configList[] = $res;
        }
        View::assign('configList', $configList);
        return $this->render();
    }

    /**
     * 打卡记录页面（管理员查看所有用户）
     */
    public function records()
    {
        if (!IS_POST) return $this->render();

        $page = qh_page_number(input('page', null));
        $limit = qh_page_limit(input('limit', null), 15);
        $username = input('username', '');

        $query = Db::name('checkin_record')
            ->alias('c')
            ->join('user u', 'c.user_id = u.id', 'LEFT')
            ->field('c.*, u.username');

        if (!empty($username)) {
            $query->where('u.username', 'like', "%{$username}%");
        }

        $total = $query->count();
        $list = $query->order('c.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return json([
            'code' => 0,
            'msg' => '',
            'count' => $total,
            'data' => $list,
        ]);
    }
}
