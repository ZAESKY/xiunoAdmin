<?php

namespace app\admin\service;

use app\admin\model\PowerPriceModel;
use app\admin\model\PowerTemplateModel;
use app\common\service\BaseService;
use think\Exception;

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
                    $res['templateName'] = '获取模板名称失败';
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
            $type = !empty($post['type'])?$post['type']:null;
            $status = !empty($post['status'])?1:0;
            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            $this->model->setPower($id, $type, $status);
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
}