<?php
namespace addons\encryption;	// 注意命名空间规范

use think\Addons;
/**
 * 插件测试
 * @author byron sampson
 */
class Plugin extends Addons	// 需继承think\Addons类
{
    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable(){
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable(){
        return true;
    }
}