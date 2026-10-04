<?php

namespace app\admin\service;

use app\admin\model\PowerPriceModel;
use app\admin\model\PowerTemplateModel;
use app\common\service\BaseService;
use think\Exception;
use think\facade\Cache;

/**
 * 权限-服务类
 * @author 陌上花开
 * @since 2022/1/30
 * Class PowerPriceService
 * @package app\admin\service
 */
class PowerPriceService extends BaseService
{
    public function __construct()
    {
        $this->model = new PowerPriceModel();
        $this->powerTemplateModel = new PowerTemplateModel();
    }

    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                $powerInfo = $this->powerTemplateModel->getInfo($res['tid']);
                if($powerInfo){
                    $res['templateName'] = $powerInfo['name'];
                }else{
                    $res['templateName'] = t('common.no_data');
                }
                $res['open'] = true;
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setPower(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $type = !empty($post['type']) ? trim((string)$post['type']) : null;
            $rawStatus = $post['status'] ?? null;
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            if (!in_array($rawStatus, [0, 1, '0', '1'], true)) {
                throw new Exception(t('validation.invalid_status'));
            }
            $status = intval($rawStatus);
            $this->model->setPower($id, $type, $status);
            Cache::tag('SF_Menu')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setDefaultPower(){
        try{
            $result = $this->model->setDefaultPower();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 测试权限价格(预览价格配置对当前请求的应用是否生效)
     * @param int $pid
     * @return array
     */
    public function test($pid = 0){
        try{
            if (empty($pid)) {
                return ['msg' => 'Missing ID parameter.', 'data' => [], 'success' => false, 'code' => -1];
            }
            $info = $this->model->getInfo($pid);
            if (!$info) {
                return ['msg' => t('power_price.not_found'), 'data' => [], 'success' => false, 'code' => -1];
            }
            return ['msg' => t('power_price.available'), 'data' => $info, 'success' => true, 'code' => 0];
        }catch (\Exception $e){
            return ['msg' => $e->getMessage(), 'data' => [], 'success' => false, 'code' => -1];
        }
    }
}
