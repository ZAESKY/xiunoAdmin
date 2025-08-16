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
                if(!parent::isSubordinatePower($res['power'])){
                    $res['password'] = '权限不足无法查看';
                }
                $res['powerSpan'] = 'gray';
                $res['powerName'] = '权限错误';
                $res['appName'] = '应用错误';
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