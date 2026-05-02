<?php
namespace app\user\service;

use app\user\model\UserModel;
use app\common\service\UserBaseService;
use think\Exception;

/**
 * 用户管理-服务类
 * @author 陌上花开
 * @since 2022/7/3
 * Class UserService
 * @package app\user\service
 */
class UserService extends UserBaseService
{
    /**
     * 构造函数
     * @author 陌上花开
     * @since 2022/7/3
     * UserService constructor.
     */
    public function __construct(){
        $this->model = new UserModel();
    }

    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                unset($res['password']);
                $res['powerSpan'] = 'gray';
                $res['powerName'] = t('user.power_error');
                $res['appName'] = t('app.get_info_failed');
                $powerInfo = parent::getPowerPriceInfo($res['power']);
                $appInfo = parent::getAppInfo($res['appid']);
                if($powerInfo){
                    if($powerInfo['default'] == 0){
                        if($powerInfo['parentid'] == 0){
                            $res['powerSpan'] = 'red';
                        }else{
                            $res['powerSpan'] = 'blue';
                        }
                    }else{
                        $res['powerSpan'] = 'gray';
                    }
                    $res['powerName'] = $powerInfo['name'];
                }

                if($appInfo){
                    $res['appName'] = $appInfo['name'];
                }
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}