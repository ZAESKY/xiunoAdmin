<?php
namespace app\admin\model;

use app\common\model\BaseModel;
use app\admin\validate\App;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * 应用-模型
 * @author 陌上花开
 * @since 2022/7/3
 * Class AppModel
 * @package app\admin\model
 */
class AppModel extends BaseModel
{
    // 设置数据表
    protected $name = 'app';

    public function getInfo($id){
        try{
            $result = self::where('id', $id)->find();
            if($result){
                foreach (['app_notice', 'cdkey_notice', 'pay_notice', 'register_notice', 'replace_notice'] as $field) {
                    if (isset($result[$field])) {
                        $result[$field] = clean_rich_text($result[$field]);
                    }
                }
                return $result;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function count(){
        try{
            if(!empty(Cache::get('appCount'))){
                return Cache::get('appCount');
            }else{
                $count = self::count();
                Cache::tag('QH_App')->set('appCount', $count);
                return $count;
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function edit(){
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $name = !empty($post['name'])?$post['name']:null;
        $logo = !empty($post['logo'])?$post['logo']:'/Assets/img/logo.png';
        $introduce = !empty($post['introduce'])?$post['introduce']:null;
        $check_type = !empty($post['check_type'])?$post['check_type']:null;
        $auth_query_switch = !empty($post['auth_query_switch'])?1:0;
        $user_query_switch = !empty($post['user_query_switch'])?1:0;
        $register_switch = !empty($post['register_switch'])?1:0;
        $cdkey_switch = !empty($post['cdkey_switch'])?1:0;
        $replace_switch = !empty($post['replace_switch'])?1:0;
        $api_switch = !empty($post['api_switch'])?1:0;
        $ip_switch = !empty($post['ip_switch'])?1:0;
        $authcode_switch = !empty($post['authcode_switch'])?1:0;
        $check_auth_method = !empty($post['check_auth_method'])?1:0;
        $pay_switch = !empty($post['pay_switch'])?1:0;
        $binding_auth_switch = !empty($post['binding_auth_switch'])?1:0;
        $update_switch = !empty($post['update_switch'])?1:0;
        $register_blacklist = !empty($post['register_blacklist'])?$post['register_blacklist']:null;
        $auth_template = !empty($post['auth_template'])?intval($post['auth_template']):0;
        $power_template = !empty($post['power_template'])?intval($post['power_template']):0;
        $pirate_switch = !empty($post['pirate_switch'])?intval($post['pirate_switch']):0;
        $pirate_money = !empty($post['pirate_money'])?$post['pirate_money']:'0.00';
        $replace_money = !empty($post['replace_money'])?$post['replace_money']:'0.00';
        $give_money = !empty($post['give_money'])?$post['give_money']:'0.00';
        $public_key = !empty($post['public_key'])?$post['public_key']:null;
        $private_key = !empty($post['private_key'])?$post['private_key']:null;
        $authcode_file = !empty($post['authcode_file'])?$post['authcode_file']:'';
        $sql_file = !empty($post['sql_file'])?$post['sql_file']:'';
        $pirate_msg = !empty($post['pirate_msg'])?htmlspecialchars($post['pirate_msg']):null;
        $endtime_msg = !empty($post['endtime_msg'])?htmlspecialchars($post['endtime_msg']):null;
        $status_msg = !empty($post['status_msg'])?htmlspecialchars($post['status_msg']):null;
        $authcode_msg = !empty($post['authcode_msg'])?htmlspecialchars($post['authcode_msg']):null;
        $ip_msg = !empty($post['ip_msg'])?htmlspecialchars($post['ip_msg']):null;
        $hacker_file = !empty($post['hacker_file'])?$post['hacker_file']:'';
        $hacker_key = !empty($post['hacker_key'])?$post['hacker_key']:'';
        $pirate_msg_switch = !empty($post['pirate_msg_switch'])?1:0;
        $app_notice = !empty($post['app_notice'])?clean_rich_text($post['app_notice']):'';
        $cdkey_notice = !empty($post['cdkey_notice'])?clean_rich_text($post['cdkey_notice']):'';
        $pay_notice = !empty($post['pay_notice'])?clean_rich_text($post['pay_notice']):'';
        $register_notice = !empty($post['register_notice'])?clean_rich_text($post['register_notice']):'';
        $replace_notice = !empty($post['replace_notice'])?clean_rich_text($post['replace_notice']):'';
        $status = !empty($post['status'])?intval($post['status']):0;
        $api_key = !empty($post['api_key'])?$post['api_key']:md5(get_random_code().time());
        $free_replace_number = !empty($post['free_replace_number'])?intval($post['free_replace_number']):0;
        try {
            validate(App::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        try {
            $pirate_money = qh_money_format($pirate_money);
            $replace_money = qh_money_format($replace_money);
            $give_money = qh_money_format($give_money);
        } catch (\InvalidArgumentException $e) {
            return message('validation.amount_format', false);
        }
        $authcode_file = str_replace('\\\\', '/', $authcode_file);
        if(substr($authcode_file, 0, 1) == '/'){
            $authcode_file = substr($authcode_file, 1);
        }
        if(!empty($id)) {

            $data = [
                'name' => $name,
                'logo' => $logo,
                'introduce' => $introduce,
                'check_type' => $check_type,
                'auth_query_switch' => $auth_query_switch,
                'user_query_switch' => $user_query_switch,
                'register_switch' => $register_switch,
                'cdkey_switch' => $cdkey_switch,
                'replace_switch' => $replace_switch,
                'api_switch' => $api_switch,
                'ip_switch' => $ip_switch,
                'authcode_switch' => $authcode_switch,
                'check_auth_method' => $check_auth_method,
                'pay_switch' => $pay_switch,
                'update_switch' => $update_switch,
                'register_blacklist' => $register_blacklist,
                'auth_template' => $auth_template,
                'power_template' => $power_template,
                'pirate_switch' => $pirate_switch,
                'pirate_money' => $pirate_money,
                'replace_money' => $replace_money,
                'give_money' => $give_money,
                'public_key' => $public_key,
                'private_key' => $private_key,
                'authcode_file' => $authcode_file,
                'sql_file' => $sql_file,
                'pirate_msg' => $pirate_msg,
                'endtime_msg' => $endtime_msg,
                'status_msg' => $status_msg,
                'authcode_msg' => $authcode_msg,
                'ip_msg' => $ip_msg,
                'hacker_file' => $hacker_file,
                'hacker_key' => $hacker_key,
                'pirate_msg_switch' => $pirate_msg_switch,
                'binding_auth_switch' => $binding_auth_switch,
                'status' => $status,
                'app_notice' => $app_notice,
                'cdkey_notice' => $cdkey_notice,
                'pay_notice' => $pay_notice,
                'register_notice' => $register_notice,
                'replace_notice' => $replace_notice,
                'api_key' => $api_key,
                'free_replace_number' => $free_replace_number
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                Cache::tag('QH_App')->clear();
                return message(t('user.edit_success') ,true);
            } catch (\Exception $e) {
                return message(t('user.edit_failed').$e->getMessage() ,false);
            }
        }else{
            $download_file = qh_secure_token(16) /* A-15: 原 md5(time().常量) 可预测 */;
            try {
                $downloadPath = APP_PATH . DS . 'common' . DS . 'download' . DS . $download_file;
                if (!mkdir($downloadPath . DS . 'release', 0755, true)
                    || !mkdir($downloadPath . DS . 'update', 0755, true)) {
                    throw new \RuntimeException(t('app.download_directory_create_failed'));
                }
            } catch (\Exception $e) {
                $this->deleteDownloadPath($download_file);
                return message(t('app.create_dir_failed') . $e->getMessage(), false);
            }
            $data = [
                'name' => $name,
                'logo' => $logo,
                'introduce' => $introduce,
                'check_type' => $check_type,
                'auth_query_switch' => $auth_query_switch,
                'user_query_switch' => $user_query_switch,
                'register_switch' => $register_switch,
                'cdkey_switch' => $cdkey_switch,
                'replace_switch' => $replace_switch,
                'api_switch' => $api_switch,
                'ip_switch' => $ip_switch,
                'authcode_switch' => $authcode_switch,
                'check_auth_method' => $check_auth_method,
                'pay_switch' => $pay_switch,
                'update_switch' => $update_switch,
                'register_blacklist' => $register_blacklist,
                'auth_template' => $auth_template,
                'power_template' => $power_template,
                'pirate_switch' => $pirate_switch,
                'pirate_money' => $pirate_money,
                'replace_money' => $replace_money,
                'give_money' => $give_money,
                'public_key' => $public_key,
                'private_key' => $private_key,
                'authcode_file' => $authcode_file,
                'sql_file' => $sql_file,
                'pirate_msg' => $pirate_msg,
                'endtime_msg' => $endtime_msg,
                'status_msg' => $status_msg,
                'authcode_msg' => $authcode_msg,
                'ip_msg' => $ip_msg,
                'hacker_file' => $hacker_file,
                'hacker_key' => $hacker_key,
                'pirate_msg_switch' => $pirate_msg_switch,
                'binding_auth_switch' => $binding_auth_switch,
                'status' => $status,
                'app_notice' => $app_notice,
                'cdkey_notice' => $cdkey_notice,
                'pay_notice' => $pay_notice,
                'register_notice' => $register_notice,
                'replace_notice' => $replace_notice,
                'download_file' => $download_file,
                'addtime' => datetime(),
                'api_key' => $api_key,
                'free_replace_number' => $free_replace_number
            ];
            try {
                self::insert($data);
                Cache::tag('QH_App')->clear();
                return message(t('user.add_success'), true);
            } catch (\Exception $e) {
                $this->deleteDownloadPath($download_file);
                return message(t('user.add_failed') . $e->getMessage(), false);
            }
        }
    }

    public function getAllList(){
        try{
            if(!empty(Cache::get('AppList'))){
                return Cache::get('AppList');
            }else{
                $list = self::order('id' ,'desc')->where('status','2')->field('id,name')->select();
                Cache::tag('QH_App')->set('AppList',$list);
                return $list;
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('app.not_exist'));
            }
            self::where('id', $id)->delete();
            $this->deleteDownloadPath($row['download_file']);
            Cache::tag('QH_App')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    private function deleteDownloadPath($downloadFile)
    {
        $downloadFile = trim((string)$downloadFile);
        if (!preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $downloadFile)) {
            return;
        }

        $root = APP_PATH . DS . 'common' . DS . 'download';
        $path = $root . DS . $downloadFile;
        if (is_link($path) || !is_dir($path)) {
            return;
        }

        $realRoot = realpath($root);
        $realPath = realpath($path);
        if ($realRoot === false || $realPath === false) {
            return;
        }
        $prefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realPath . DIRECTORY_SEPARATOR, $prefix) || dirname($realPath) !== $realRoot) {
            return;
        }
        rmdirs($realPath);
    }

    /**
     * 清理已删除应用的孤立下载目录
     */
    public function cleanOrphanDownloadDirs()
    {
        $downloadRoot = APP_PATH . DS . 'common' . DS . 'download';
        if (!is_dir($downloadRoot)) return;
        $activeFiles = array_map('strval', self::column('download_file'));
        $handle = opendir($downloadRoot);
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..' || $entry === 'release' || $entry === 'update' || $entry === 'AuthInfo.php') continue;
            if (!in_array($entry, $activeFiles, true)
                && preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $entry)) {
                $path = $downloadRoot . DS . $entry;
                if (is_dir($path) && !is_link($path)) {
                    $this->deleteDownloadPath($entry);
                }
            }
        }
        closedir($handle);
    }

    public function setStatus(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $status = !empty($post['status'])?intval($post['status']):0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('app.not_exist'));
            }
            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            Cache::tag('QH_App')->clear();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $data = $this->buildSearchWhere('id|name');
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
