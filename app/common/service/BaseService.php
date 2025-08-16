<?php
/*
* +----------------------------------------------------------------------
* | SF 综合验证授权系统
* +----------------------------------------------------------------------
* | Quotes [ 花开的再灿烂，也有凋谢的一天，致我们过去的青春 ]
* +----------------------------------------------------------------------
* | Author: 陌上花开 <2129876388@qq.com>
* +----------------------------------------------------------------------
* | Date: 2022年1月19日 18:48:32
* +----------------------------------------------------------------------
*/

namespace app\common\service;


use MongoDB\Driver\Exception\WriteConcernException;
use think\Exception;

/**
 * 服务基类
 * @author 陌上花开
 * @since 2022-01-21
 */
class BaseService
{
    // 模型
    protected $model;

    /**
     * 获取用户信息
     * @return array
     * @since: 2022/6/30
     * @author 陌上花开
     */
    protected function getUserInfo(){
        try{
            $userModel = new \app\user\model\User();
            $userInfo = $userModel->getInfo();
            if(!$userInfo){
                throw new Exception('获取用户信息失败！');
            }
            return $userInfo;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 获取信息
     * @return array
     * @since: 2022/6/30
     * @author 陌上花开
     */
    public function getInfo($id){
        try{
            $result = $this->model->getInfo($id);
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 获取列表
     * @return array
     * @since: 2022/6/30
     * @author 陌上花开
     */
    public function list(){
        try{
            $result = $this->model->list();
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 获取信息
     * @return array
     * @since: 2022/6/30
     * @author 陌上花开
     */
    public function getOne(array $map){
        try{
            $result = $this->model->getOne($map);
            return $result;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 添加或编辑
     * @return array
     * @since: 2022/6/30
     * @author 陌上花开
     */
    public function edit()
    {
        return $this->model->edit();
    }

    /**
     * 设置记录状态
     * @return array
     * @since 2020/7/2
     * @author 陌上花开
     */
    public function setStatus()
    {
        try{
            $this->model->setStatus();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 删除单条记录
     * @return array
     * @author 陌上花开
     * @date 2022/1/25
     */
    public function drop($id)
    {
        try{
            $this->model->drop($id);
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 获取应用信息
     * @return mixed
     * @author 陌上花开
     * @date 2022/1/25
     */
    public function getAppInfo($id)
    {
        try{
            if(empty($id)) return false;
            $appModel = new \app\admin\model\AppModel();
            $result = $appModel->getInfo($id);
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取权限信息
     * @return mixed
     * @author 陌上花开
     * @date 2022/1/25
     */
    public function getPowerPriceInfo($id)
    {
        if (empty($id)) return false;
        $powerPriceModel = new \app\admin\model\PowerPriceModel();
        try {
            $result = $powerPriceModel->getInfo($id);
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取价格列表
     * @return mixed
     * @author 陌上花开
     * @date 2022/1/25
     */
    public function getAuthPriceList()
    {
        try{
            if(IS_POST){
                $appid = intval(input('post.appid'));
                if(empty($appid)){
                    throw new Exception('APPID不能为空！');
                }
                $authPriceModel = new \app\admin\model\AuthPriceModel();
                $appInfo = $this->getAppInfo($appid);
                $tid = intval($appInfo['auth_template']);
                $result = $authPriceModel->getAuthPriceList($tid);
                return $result;
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

}