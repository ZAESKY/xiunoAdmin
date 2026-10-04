<?php
namespace app\admin\service;

use app\common\service\BaseService;
use app\common\service\SafeZipService;
use think\Exception;
use think\addons\Service;
use think\facade\Cache;

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
                throw new Exception(t('addon_service.package_required'));
            }
            $tmpFile = (string)$file->getPathname();
            $extension = strtolower((string)$file->getOriginalExtension());
            $size = (int)$file->getSize();
            if ($extension !== 'zip' || $size <= 0 || $size > 200 * 1024 * 1024) {
                throw new Exception(t('addon_service.package_invalid'));
            }
            // 必须在交给第三方安装器前完成结构校验，否则 Service::local()
            // 自己解压时仍可能受到路径穿越、符号链接或压缩炸弹影响。
            $zipProblem = SafeZipService::validate($tmpFile, [], 10000, 536870912);
            if ($zipProblem !== null) {
                throw new Exception($zipProblem);
            }
            // think-addons 内部会处理 zip 解压、插件目录生成、Service::refresh 等
            // 保持对 Service::local 的兼容:若版本提供则调用,否则退回手动解压
            if (method_exists(Service::class, 'local')) {
                return Service::local($file);
            }
            // 退而求其次:仅做安全解压，并清除 hooks 缓存。当前
            // think-addons 2.x 没有静态 refresh()，下一次请求会自动扫描插件。
            $addonsPath = root_path() . 'addons' . DIRECTORY_SEPARATOR;
            if (!is_dir($addonsPath) && !mkdir($addonsPath, 0755, true) && !is_dir($addonsPath)) {
                throw new Exception(t('addon_service.create_directory_failed'));
            }
            $zip = new \ZipArchive();
            if ($zip->open($tmpFile) !== true) {
                throw new Exception(t('addon_service.open_archive_failed'));
            }
            try {
                if (!$zip->extractTo($addonsPath)) {
                    throw new Exception(t('addon_service.extract_failed'));
                }
            } finally {
                $zip->close();
            }
            Cache::delete('hooks');
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
                throw new Exception(t('addon_service.name_required'));
            }
            if (!$file) {
                throw new Exception(t('addon_service.update_package_required'));
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
