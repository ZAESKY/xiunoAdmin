<?php

namespace app\admin\model;

use app\admin\validate\Version;
use app\common\model\BaseModel;
use app\common\extend\CheckInfo;
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
            if(empty($row['download_catalogue'])){
                throw new Exception(t('version.get_download_dir_empty'));
            }
            $filePath = APP_PATH.'/common/download/'.(($row['type'] == 0)?'release':'update').'/'.$row['download_catalogue'];
            if(!is_dir($filePath)){
                throw new Exception(t('version.download_dir_not_exist'));
            }
            if(rmdirs($filePath,false)){
                Cache::tag('SF_Version')->clear();
                return true;
            }else{
                throw new Exception(t('user.delete_failed'));
            }
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
            if(empty($row['download_catalogue'])){
                throw new Exception(t('version.get_download_dir_empty'));
            }
            $filePath = APP_PATH.'/common/download/'.(($row['type'] == 0)?'release':'update').'/'.$row['download_catalogue'];
            if(!is_dir($filePath)){
                throw new Exception(t('version.download_dir_not_exist'));
            }
            if(!is_file($filePath.'/SF.zip')){
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
        $type = !empty($post['type'])?intval($post['type']):0;
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
            if(!is_dir(APP_PATH.'/common/download/'.(($row['type'] == 0)?'release':'update').'/'.$row['download_catalogue'])){
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
                Cache::tag('SF_Version')->clear();
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
            $download_catalogue = $appid.'_'.$version.'_'.md5(time() . 'SF2129876388');
            try {
                $result = mkdirs(APP_PATH.'/common/download/'.(($type == 0)?'release':'update').'/'.$download_catalogue);
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
                Cache::tag('SF_Version')->clear();
                return message(t('user.add_success') ,true);
            } catch (\Exception $e) {
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
            self::where('id', $id)->delete();
            Cache::tag('SF_Version')->clear();
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
            Cache::tag('SF_Version')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setType(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $type = !empty($post['type'])?intval($post['type']):0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('version.not_exist'));
            }
            self::where('id', $id)
                ->data(['type' => $type])
                ->update();
            Cache::tag('SF_Version')->clear();
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
            self::where('id', $id)
                ->data(['beta' => $beta])
                ->update();
            Cache::tag('SF_Version')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
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
}
