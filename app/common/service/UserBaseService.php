<?php
namespace app\common\service;


use think\Exception;

/**
 * 用户服务基类
 * @author 陌上花开
 * @since 2022-01-21
 */
class UserBaseService
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
        $userModel = new \app\user\model\User();
        $userInfo = $userModel->getInfo();
        if(!$userInfo){
            return false;
        }
        return $userInfo;
    }

    /**
     * 获取信息
     * @return array
     * @since: 2022/6/30
     * @author 陌上花开
     */
    public function getInfo($id){
        return $this->model->getInfo($id);
    }

    /**
     * 获取列表
     * @return array
     * @since: 2022/6/30
     * @author 陌上花开
     */
    public function list(){
        try{
            return $this->model->list();
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
            $res = $this->model->getOne($map);
            if($res){
                return $res;
            }else{
                return false;
            }
        } catch (\Exception $e) {
            return false;
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
        if(IS_POST) {
            return $this->model->edit();
        }
    }

    /**
     * 判断是否为用户下级权限
     * @return mixed
     * @author 陌上花开
     * @date 2022/1/25
     */
    protected function isSubordinatePower($sid, $pid = null){
        if(empty($sid)) return false;
        $powerPriceModel = new \app\admin\model\PowerPriceModel();
        try{
            $result = $powerPriceModel->getInfo($sid);
            if(!$result) return false;
            if(empty($pid)){
                $userInfo = $this->getUserInfo();
                if(!$userInfo) return false;
                $result = $powerPriceModel->getInfo($userInfo['power']);
                if(!$result) return false;
                $result = $powerPriceModel->isUserSubordinatePower($userInfo['power'], $sid);
                if(!$result) return false;
            }else{
                $result = $powerPriceModel->getInfo($pid);
                if(!$result) return false;
                $result = $powerPriceModel->isUserSubordinatePower($pid, $sid);
                if(!$result) return false;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
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

//    /**
//     * 获取授权信息
//     * @return mixed
//     * @author 陌上花开
//     * @date 2022/1/25
//     */
//    public function getAuthInfo($id)
//    {
//        if(empty($id)) return false;
//        try{
//            $authModel = new \app\admin\model\AuthModel();
//            $result = $authModel->getInfo($id);
//            return $result;
//        } catch (\Exception $e) {
//            throw new Exception($e->getMessage());
//        }
//    }

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
            throw new Exception($e->getMessage());
        }
    }

//    /**
//     * 获取价格信息
//     * @return mixed
//     * @author 陌上花开
//     * @date 2022/1/25
//     */
//    public function getAuthPriceInfo($id)
//    {
//        if(empty($id)) return false;
//        $authPriceModel = new \app\admin\model\AuthPriceModel();
//        try{
//            $result = $authPriceModel->getInfo($id);
//            if(!$result) return false;
//            return $result;
//        } catch (\Exception $e) {
//            throw new Exception($e->getMessage());
//        }
//    }

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
            throw new Exception($e->getMessage());
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
                $appid = intval($this->userInfo['appid']);
                if(empty($appid)){
                    throw new Exception(t('app.appid_empty'));
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