<?php
declare(strict_types=1);

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\CheckinService;
use think\facade\Db;

class Checkin extends UserBackend
{
    /**
     * 获取打卡状态
     */
    public function status()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false));
        }

        $service = new CheckinService($this->userId, $this->userInfo['username']);
        $status = $service->getStatus();
        return json(message('ok', true, $status));
    }

    /**
     * 执行打卡
     */
    public function doCheckin()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false));
        }

        $service = new CheckinService($this->userId, $this->userInfo['username']);
        $result = $service->doCheckin(get_client_ip());
        return json(message($result['msg'], $result['success'], $result['data'] ?? []));
    }

    /**
     * 打卡记录页面
     */
    public function records()
    {
        return $this->render();
    }

    /**
     * 获取打卡统计
     */
    public function stats()
    {
        try {
            $records = Db::name('checkin_record')
                ->where('user_id', $this->userId)
                ->field('consecutive_days, points_earned')
                ->select()
                ->toArray();

            $totalDays = count($records);
            $totalPoints = array_sum(array_column($records, 'points_earned'));
            $maxConsecutive = $totalDays > 0 ? max(array_column($records, 'consecutive_days')) : 0;

            return json(message('ok', true, [
                'total_days' => $totalDays,
                'total_points' => $totalPoints,
                'max_consecutive' => $maxConsecutive,
            ]));
        } catch (\Exception $e) {
            return json(message('ok', true, ['total_days' => 0, 'total_points' => 0, 'max_consecutive' => 0]));
        }
    }

    /**
     * 打卡记录列表（JSON数据）
     */
    public function myRecords()
    {
        if (!IS_POST) {
            return json(message('common.illegal_request', false));
        }

        $page = sf_page_number(input('current_page', input('page', 1)));
        $limit = sf_page_limit(input('limit', null), 15);

        try {
            $total = Db::name('checkin_record')
                ->where('user_id', $this->userId)
                ->count();

            $list = Db::name('checkin_record')
                ->where('user_id', $this->userId)
                ->order('id', 'desc')
                ->page($page, $limit)
                ->field('checkin_date, consecutive_days, points_earned, created_at')
                ->select()
                ->toArray();

            return json([
                'code' => 0,
                'msg' => '',
                'count' => $total,
                'data' => $list,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '', 'count' => 0, 'data' => []]);
        }
    }
}
