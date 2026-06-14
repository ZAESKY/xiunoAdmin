<?php

namespace app\api\service;

use app\api\model\AuthModel;
use app\api\model\BlackModel;
use app\common\service\BaseService;
use think\Exception;
use think\facade\Db;

class QueryService extends BaseService
{
    public function __construct()
    {
        $this->model = new AuthModel();
        $this->blackModel = new BlackModel();
    }

    /**
     * 黑名单查询 — 由 /api.php/Query/black 调用
     * 默认按 IP/QQ 查询
     */
    public function black(){
        try{
            $param = request()->param();
            $ip = !empty($param['ip'])?trim($param['ip']):null;
            $qq = !empty($param['qq'])?trim($param['qq']):null;
            $auth = !empty($param['auth'])?trim($param['auth']):null;
            $where = [];
            if($ip) $where[] = ['ip', 'like', "%{$ip}%"];
            if($qq) $where[] = ['qq', '=', $qq];
            if($auth) $where[] = ['auth_info', '=', $auth];
            $list = $this->blackModel->getList($where);
            return json(message('success', true, $list));
        }catch (Exception $e){
            return json(message($e->getMessage(), false));
        }
    }
}