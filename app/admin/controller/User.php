<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\UserService;
use app\common\service\AccountingLogService;
use think\facade\View;

class User extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new UserService();
    }

    public function balanceLog(){
        try{
            if(IS_POST){
                $userId = request()->post('user_id/d');
                $limit = sf_page_limit(request()->post('limit', null), 15);
                $page = sf_page_number(request()->post('current_page', null));
                if(empty($userId)) return json(message(t('validation.missing_id'), false));
                $list = \app\common\model\BalanceLogModel::where('user_id', $userId)
                    ->order('id', 'desc')
                    ->paginate(['list_rows' => $limit, 'page' => $page]);
                $data = $list->toArray();
                $data['data'] = AccountingLogService::decorateItems(
                    $data['data'] ?? [],
                    AccountingLogService::LEDGER_BALANCE
                );
                return json(message('ok', true, ['data' => $data]));
            }
        }catch (\Exception $e){
            return json(message($e->getMessage(), false));
        }
    }

    public function pointLog(){
        try{
            if(IS_POST){
                $userId = request()->post('user_id/d');
                $limit = sf_page_limit(request()->post('limit', null), 15);
                $page = sf_page_number(request()->post('current_page', null));
                if(empty($userId)) return json(message(t('validation.missing_id'), false));
                $list = \app\common\model\PointLogModel::where('user_id', $userId)
                    ->order('id', 'desc')
                    ->paginate(['list_rows' => $limit, 'page' => $page]);
                $data = $list->toArray();
                $data['data'] = AccountingLogService::decorateItems(
                    $data['data'] ?? [],
                    AccountingLogService::LEDGER_POINT
                );
                return json(message('ok', true, ['data' => $data]));
            }
        }catch (\Exception $e){
            return json(message($e->getMessage(), false));
        }
    }

    public function edit()
    {
        try {
            if (IS_POST) {
                return json($this->service->edit());
            }
            return json(message('common.illegal_request', false));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function getAppUserList(){
        try{
            if(IS_POST){
                $appid = $this->request->post('appid/d');
                $result = $this->service->getAppUserList($appid);
                return json(message(t('common.list_success') ,true, ['data' => $result]));
            }
        }catch (\Exception $e){
            return json(message($e->getMessage(), false));
        }
    }

    public function list($appid = ''){
        try{
            if(IS_POST){
                $result = $this->service->list();
                $data = method_exists($result, 'toArray') ? $result->toArray() : $result;
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as &$item) {
                        unset($item['password']);
                    }
                    unset($item);
                }
                return json(message(t('common.list_success') ,true, ['data' => $data]));
            }
        }catch (\Exception $e){
            return json(message($e->getMessage(), false));
        }
        try{
            $userid = max(0, (int)$this->request->get('userid', 0));
            View::assign('appid', $appid);
            View::assign('userid', $userid);
            View::assign('app_list', parent::getAppList());
            View::assign('power_list', (new \app\admin\model\PowerPriceModel())->field('id,name')->select()->toArray());
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }
}
