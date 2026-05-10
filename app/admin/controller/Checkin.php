<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\SetService;
use think\facade\Db;
use think\facade\View;

class Checkin extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->request->filter(['trim', 'addslashes']);
    }

    /**
     * 打卡配置页面
     */
    public function config()
    {
        if ($this->request->isPost()) {
            $row = $this->request->post("row/a", [], 'trim,html_entity_decode');
            if ($row) {
                $setService = new SetService();
                $configList = [];
                foreach ($setService->all() as $v) {
                    if ($v['group'] !== 'checkin') continue;

                    if ($v['type'] == 'bool') {
                        $value = isset($row[$v['name']]) ? intval($row[$v['name']]) : 0;
                    } else {
                        $value = $row[$v['name']] ?? $v['value'];
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

        $page = intval(input('page', 1));
        $limit = intval(input('limit', 15));
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
