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
            return $list;
        }catch (\Exception $e){
            return [];
        }
    }

}