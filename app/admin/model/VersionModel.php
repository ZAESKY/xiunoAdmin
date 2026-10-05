<?php

namespace app\admin\model;

use app\admin\validate\Version;
use app\common\model\BaseModel;
use app\common\extend\CheckInfo;
use app\common\service\ReleasePackageService;
use app\common\service\PluginStorageService;
use app\common\service\VersionReleaseService;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * 版本-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class VersionModel
 * @package app\admin\model
 */
class VersionModel extends BaseModel
{
    // 设置数据表名
    protected $name = 'version';

    public function getInfo($id){
        try{
            $result = self::where('id', $id)->find();
            if($result){
                return $result;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function deleteFile(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('version.not_exist'));
            }
            if (($row['storage_driver'] ?? 'local') === 'oss') {
                $key = (string)($row['package_object_key'] ?? '');
                if ($key === '' || !(new PluginStorageService())->deleteObject($key)) {
                    throw new Exception(t('user.delete_failed'));
                }
                self::where('id', $id)->update([
                    'storage_driver' => 'local',
                    'package_object_key' => '',
                    'package_sha256' => '',
                    'package_size' => 0,
                ]);
            } else {
                if(empty($row['download_catalogue'])){
                    throw new Exception(t('version.get_download_dir_empty'));
                }
                $filePath = ReleasePackageService::existingDir($row['type'], $row['download_catalogue']);
                if($filePath === ''){
                    throw new Exception(t('version.download_dir_not_exist'));
                }
                $packageFile = ReleasePackageService::file($row['type'], $row['download_catalogue']);
                if ($packageFile === '' || is_link($packageFile) || !@unlink($packageFile)) {
                    throw new Exception(t('user.delete_failed'));
                }
            }
            VersionReleaseService::deleteForVersion($this->rowArray($row));
            Cache::tag('QH_Version')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function checkFile(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('version.not_exist'));
            }
            if (($row['storage_driver'] ?? 'local') === 'oss') {
                (new PluginStorageService())->objectDownloadUrl((string)($row['package_object_key'] ?? ''), 30);
                return true;
            }
            if(empty($row['download_catalogue'])){
                throw new Exception(t('version.get_download_dir_empty'));
            }
            $filePath = ReleasePackageService::existingDir($row['type'], $row['download_catalogue']);
            if($filePath === ''){
                throw new Exception(t('version.download_dir_not_exist'));
            }
            if(ReleasePackageService::file($row['type'], $row['download_catalogue']) === ''){
                throw new Exception(t('common.no_data'));
            }else{
                return true;
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function edit(){
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($post['appid'])?intval($post['appid']):null;
        $edition = !empty($post['edition'])?$post['edition']:null;
        $version = !empty($post['version'])?intval($post['version']):null;
        $update_log = !empty($post['update_log'])?$post['update_log']:'';
        // 版本记录只保存完整发布包；差分包由发布流程自动生成。
        $type = 0;
        $beta = !empty($post['beta'])?intval($post['beta']):0;
        $status = !empty($post['status'])?1:0;

        try {
            validate(Version::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        if(!empty($id)) {
            $row = $this->getInfo($id);
            if(!$row){
                return message(t('version.not_exist') ,false);
            }
            // 应用决定产品标识、签名发布物和差分链，版本创建后禁止跨应用迁移。
            $appid = intval($row['appid']);
            if($edition != $row['edition']){
                $row2 = self::where(['edition' => $edition, 'appid' => $appid])->find();
                if ($row2) {
                    return message(t('version.already_exist'), false);
                }
            }
            if($version != $row['version']){
                $row3 = self::where(['version' => $version, 'appid' => $appid])->find();
                if ($row3) {
                    return message(t('version.number_exists'), false);
                }
            }
            if(empty($row['download_catalogue'])){
                return message(t('version.get_download_dir_empty'), false);
            }
            $hasPackage = $this->hasPackage($row);
            if ($hasPackage && (
                (string)$edition !== (string)$row['edition']
                || (int)$version !== (int)$row['version']
                || (int)$beta !== (int)$row['beta']
            )) {
                return message('version.package_blocks_metadata_change', false);
            }
            $currentDir = ReleasePackageService::existingDir($row['type'], $row['download_catalogue']);
            if($currentDir === ''){
                return message(t('version.download_dir_not_exist'), false);
            }
            $data = [
                'edition' => $edition,
                'version' => $version,
                'update_log' => $update_log,
                'type' => $type,
                'beta' => $beta,
                'status' => $status,
                'appid' => $appid,
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                $publishedRow = array_merge($this->rowArray($row), $data, ['id' => $id]);
                VersionReleaseService::syncStatus($publishedRow);
                Cache::tag('QH_Version')->clear();
                return message(t('user.edit_success') ,true);
            } catch (\Exception $e) {
                return message(t('user.edit_failed').$e->getMessage() ,false);
            }
        }else{
            $row = self::where(['edition' => $edition, 'appid' => $appid])->find();
            if ($row) {
                return message(t('version.already_exist'), false);
            }
            $row = self::where(['version' => $version, 'appid' => $appid])->find();
            if ($row) {
                return message(t('version.number_exists'), false);
            }
            $download_catalogue = $appid.'_'.$version.'_'.qh_secure_token(16) /* A-15: 原 md5(time().常量) 可预测 */;
            try {
                $newDir = ReleasePackageService::dir($type, $download_catalogue);
                $result = $newDir !== '' && mkdirs($newDir, 0755);
                if(!$result){
                    return message(t('app.create_dir_failed'), false);
                }
            } catch (\Exception $e) {
                return message(t('app.create_dir_failed') . $e->getMessage(), false);
            }
            $data = [
                'edition' => $edition,
                'version' => $version,
                'update_log' => $update_log,
                'type' => $type,
                'beta' => $beta,
                'download_catalogue' => $download_catalogue,
                'addtime' => datetime(),
                'status' => $status,
                'appid' => $appid,
            ];
            try{
                self::insert($data);
                Cache::tag('QH_Version')->clear();
                return message(t('user.add_success') ,true);
            } catch (\Exception $e) {
                if (!empty($newDir) && is_dir($newDir) && !is_link($newDir)) {
                    rmdirs($newDir);
                }
                return message(t('user.add_failed').$e->getMessage() ,false);
            }
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('version.not_exist'));
            }
            $rowData = $this->rowArray($row);
            if (($rowData['storage_driver'] ?? 'local') === 'oss') {
                (new PluginStorageService())->deleteObject((string)($rowData['package_object_key'] ?? ''));
            } else {
                $package = ReleasePackageService::file($rowData['type'] ?? 0, $rowData['download_catalogue'] ?? '');
                if ($package !== '' && !is_link($package)) {
                    @unlink($package);
                }
            }
            VersionReleaseService::deleteForVersion($rowData);
            self::where('id', $id)->delete();
            $dir = ReleasePackageService::existingDir($rowData['type'] ?? 0, $rowData['download_catalogue'] ?? '');
            if ($dir !== '') {
                @rmdir(rtrim($dir, DIRECTORY_SEPARATOR));
            }
            Cache::tag('QH_Version')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setStatus(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $status = !empty($post['status'])?1:0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('version.not_exist'));
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            $version = $this->rowArray($row);
            $version['status'] = $status;
            VersionReleaseService::syncStatus($version);
            Cache::tag('QH_Version')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setBeta(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $beta = !empty($post['beta'])?intval($post['beta']):0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('version.not_exist'));
            }
            if ((int)$row['beta'] !== $beta
                && $this->hasPackage($row)) {
                throw new Exception(t('version.package_blocks_channel_change'));
            }
            self::where('id', $id)
                ->data(['beta' => $beta])
                ->update();
            Cache::tag('QH_Version')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    private function hasPackage($row): bool
    {
        $data = $this->rowArray($row);
        if (($data['storage_driver'] ?? 'local') === 'oss') {
            return !empty($data['package_object_key'])
                && preg_match('/^[a-f0-9]{64}$/D', (string)($data['package_sha256'] ?? ''))
                && (int)($data['package_size'] ?? 0) > 0;
        }
        return ReleasePackageService::file($data['type'] ?? 0, $data['download_catalogue'] ?? '') !== '';
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $data = $this->buildSearchWhere('id|edition|version');

            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }

            $list = self::order('id' ,'desc')->where($data)->paginate([
                'list_rows'=> $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    private function rowArray($row): array
    {
        return is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : (array)$row;
    }
}
