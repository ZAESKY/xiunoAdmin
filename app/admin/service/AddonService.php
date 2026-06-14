<?php
namespace app\admin\service;

use app\common\service\BaseService;
use think\Exception;
use think\addons\Service;
use think\facade\Filesystem;

class AddonService extends BaseService
{
    public function __construct(){

    }

    public function list(){
        try{
            $list = get_addon_list();
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 解压/安装本地上传的插件包
     * 兼容 think-addons v2.x — 通过 Service::local 走安装流程
     */
    public function extractLocalAddon($file = null, array &$info = [])
    {
        try {
            if (!$file) {
                throw new Exception('请上传插件压缩包');
            }
            // think-addons 内部会处理 zip 解压、插件目录生成、Service::refresh 等
            // 保持对 Service::local 的兼容:若版本提供则调用,否则退回手动解压
            if (method_exists(Service::class, 'local')) {
                return Service::local($file);
            }
            // 退而求其次:仅做解压+刷新
            $addonsPath = (new Service)->getAddonsPath();
            $zip = new \ZipArchive();
            $tmpFile = $file->getPathname();
            $zip->open($tmpFile);
            $zip->extractTo($addonsPath);
            $zip->close();
            Service::refresh();
            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 更新已安装的插件
     */
    public function updateAddon($name, $file = null)
    {
        try {
            if (!$name) {
                throw new Exception('缺少插件名');
            }
            if (!$file) {
                throw new Exception('请上传更新包');
            }
            if (method_exists(Service::class, 'update')) {
                return Service::update($name, $file);
            }
            // 退路:解压覆盖,再 refresh
            $this->extractLocalAddon($file);
            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}