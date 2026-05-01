<?php

namespace app\admin\service;

use app\admin\model\AuthPriceModel;
use app\admin\model\AuthTemplateModel;
use app\common\service\BaseService;
use think\Exception;

/**
 * 价格-服务类
 * @author 陌上花开
 * @since 2022/1/30
 * Class AuthPriceService
 * @package app\admin\service
 */
class AuthPriceService extends BaseService
{
    public function __construct()
    {
        $this->model = new AuthPriceModel();
        $this->authTemplateModel = new AuthTemplateModel();
    }

    public function setSort(){
        try{
            $this->model->setSort();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                $authInfo = $this->authTemplateModel->getInfo($res['tid']);
                if($authInfo){
                    $res['templateName'] = $authInfo['name'];
                }else{
                    $res['templateName'] = t('auth.get_template_info_failed');
                }
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}