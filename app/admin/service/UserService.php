<?php
namespace app\admin\service;

use app\admin\model\UserModel;
use app\common\service\BaseService;
use think\Exception;
use think\facade\Db;

/**
 * 用户管理-服务类
 * @author 陌上花开
 * @since 2022/7/3
 * Class UserService
 * @package app\admin\service
 */
class UserService extends BaseService
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

    public function getAppUserList($appid){
        try{
            return $this->model->getAppUserList($appid);
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $result = $this->model->list();
            foreach($result as $res){
                $res['powerSpan'] = 'gray';
                $res['powerName'] = t('user.power_error');
                $res['appName'] = t('app.not_exist');
                $powerInfo = parent::getPowerPriceInfo($res['power']);
                $appInfo = parent::getAppInfo($res['appid']);
                if($powerInfo) {
                    if ($powerInfo['default'] == 0) {
                        if ($powerInfo['parentid'] == 0) {
                            $res['powerSpan'] = 'red';
                        } else {
                            $res['powerSpan'] = 'blue';
                        }
                    } else {
                        $res['powerSpan'] = 'gray';
                    }
                    $res['powerName'] = $powerInfo['name'];
                }
                if($appInfo) {
                    $res['appName'] = $appInfo['name'];
                }
                if(!empty($res['ip'])){
                    $res['ip'] = implode('|', unserialize($res['ip']));
                }
                $res['cdkeyCount'] = Db::name('cdkey')->where('userid', $res['id'])->count();
                $res['userCount'] = Db::name('user')->where('userid', $res['id'])->count();
                $res['authCount'] = Db::name('auth')->where('userid', $res['id'])->count();
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}