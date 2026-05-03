<?php
// +----------------------------------------------------------------------
// | RXThinkCMF_TP6_PRO混编版框架 [ RXThinkCMF ]
// +----------------------------------------------------------------------
// | 版权所有 2022 南京RXThinkCMF研发中心
// +----------------------------------------------------------------------
// | 官方网站: http://www.rxthink.cn
// +----------------------------------------------------------------------
// | 作者: 陌上花开 <rxthinkcmf@163.com>
// +----------------------------------------------------------------------
// | 免责声明:
// | 本软件框架禁止任何单位和个人用于任何违法、侵害他人合法利益等恶意的行为，禁止用于任何违
// | 反我国法律法规的一切平台研发，任何单位和个人使用本软件框架用于产品研发而产生的任何意外
// | 、疏忽、合约毁坏、诽谤、版权或知识产权侵犯及其造成的损失 (包括但不限于直接、间接、附带
// | 或衍生的损失等)，本团队不承担任何法律责任。本软件框架只能用于公司和个人内部的法律所允
// | 许的合法合规的软件产品研发，详细声明内容请阅读《框架免责声明》附件；
// +----------------------------------------------------------------------

namespace app\admin\service;


use app\admin\model\Menu;
use app\common\service\BaseService;
use think\Exception;

/**
 * 菜单管理-服务类
 * @author 陌上花开
 * @since: 2022/6/30
 * Class MenuService
 * @package app\admin\service
 */
class MenuService extends BaseService
{
    /**
     * 构造函数
     * MenuService constructor.
     */
    public function __construct()
    {
        $this->model = new Menu();
    }

    public function getList()
    {
        try{
            $list = $this->model->getList();
            // Menu title i18n mapping (Chinese DB values → i18n keys)
            $titleMap = [
                '系统管理' => 'menu.system',
                '用户管理' => 'menu.user',
                '应用管理' => 'menu.app',
                '授权管理' => 'menu.auth',
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
                '插件中心' => 'menu.plugin_center',
                '插件列表' => 'menu.plugin_list',
                '插件订单' => 'menu.plugin_order',
                '插件评论' => 'menu.plugin_comments',
            ];
            array_walk_recursive($list, function (&$item, $key) use ($titleMap) {
                if ($key === 'title' && is_string($item) && isset($titleMap[$item])) {
                    $item = t($titleMap[$item]);
                }
            });
            return $list;
        }catch (\Exception $e){
            return [];
        }
    }

}