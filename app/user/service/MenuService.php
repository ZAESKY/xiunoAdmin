<?php
namespace app\user\service;

use app\user\model\Menu;
use app\common\service\UserBaseService;
use think\Exception;

/**
 * 菜单管理-服务类
 * @author 陌上花开
 * @since: 2022/6/30
 * Class MenuService
 * @package app\user\service
 */
class MenuService extends UserBaseService
{
    /**
     * 构造函数
     * MenuService constructor.
     */
    public function __construct()
    {
        $this->model = new Menu();
    }

    /**
     * 获取数据列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @since: 2022/6/30
     * @author 陌上花开
     */
    public function getList()
    {
        try{
            $list = $this->model->getList();
            $titleMap = [
                '系统管理' => 'menu.system',
                '用户管理' => 'menu.user',
                '应用管理' => 'menu.app',
                '授权管理' => 'menu.auth',
                '认证管理' => 'menu.payment',
                '价格管理' => 'menu.price',
                '模板管理' => 'menu.template',
                '日志管理' => 'menu.log',
                '插件管理' => 'menu.addon',
                '系统设置' => 'menu.settings',
                '支付管理' => 'menu.pay',
                '卡密管理' => 'menu.cdkey',
                '盗版管理' => 'menu.pirate',
                '版本管理' => 'menu.version',
                '订单管理' => 'menu.order',
                '权限管理' => 'menu.power',
                '个人中心' => 'menu.profile',
                '修改密码' => 'menu.change_password',
                '退出登录' => 'menu.logout',
                '控制台' => 'menu.dashboard',
                '首页' => 'menu.home',
                '判断规则' => 'menu.check_type',
                '安装管理' => 'menu.install',
                '反馈管理' => 'menu.feedback',
                '功能反馈' => 'menu.feedback',
            ];
            array_walk_recursive($list, function (&$item, $key) use ($titleMap) {
                if ($key === 'title' && is_string($item) && isset($titleMap[$item])) {
                    $item = t($titleMap[$item]);
                }
            });
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }

    }

}