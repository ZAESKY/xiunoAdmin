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
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }

    }

}