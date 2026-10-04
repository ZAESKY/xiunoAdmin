<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\user\model\Admin;
use think\facade\Db;
/**
 * 安全中心-服务类
 * @author 陌上花开
 * @since 2022/1/3
 * Class SafeService
 * @package app\user\service
 */

class SafeService extends UserBaseService
{
    /**
     * 构造函数
     * @author 陌上花开
     * @since 2022/1/3
     * SafeService constructor.
     */
    public function __construct()
    {
        $adminId = session('adminId');
        $this->adminId = $adminId;

        // 登录用户信息
        $adminModel = new Admin();
        $adminInfo = $adminModel->getInfo($adminId);
        $this->adminInfo = $adminInfo;
    }

    public function check(){
        $check_msg = $this->checkSafeMsg();
        $SF_danger = count($check_msg['danger']);
        $SF_warning = count($check_msg['warning']);
        $SF_info = count($check_msg['info']);
        $safenum = intval(100- $SF_danger*50 - $SF_warning*10 - $SF_info*1);

        return message(t('system.detect_success'), true, ["safe" => $safenum, "check_msg" => $check_msg]);
    }

    public function checkPassword($pwd)
    {
        $score = 0;
        if(!empty($pwd)){ //接收的值
            $str = $pwd;
        } else {
            $str = '';
        }
        if(preg_match("/[0-9]+/",$str)) {
            $score ++;
        }
        if(preg_match("/[0-9]{3,}/",$str)) {
            $score ++;
        }
        if(preg_match("/[a-z]+/",$str)) {
            $score ++;
        }
        if(preg_match("/[a-z]{3,}/",$str)) {
            $score ++;
        }
        if(preg_match("/[A-Z]+/",$str)) {
            $score ++;
        }
        if(preg_match("/[A-Z]{3,}/",$str)) {
            $score ++;
        }
        if(preg_match("/[_|\-|+|=|*|!|@|#|$|%|^|&|(|)]+/",$str)) {
            $score += 2;
        }
        if(preg_match("/[_|\-|+|=|*|!|@|#|$|%|^|&|(|)]{3,}/",$str)) {
            $score ++ ;
        }
        if(strlen($str) >= 10) {
            $score ++;
        }
        return $score;
    }
    private function checkSafeMsg()
    {
        /***********************压缩包检测区 开始***********************/
        $SF_zip_arr = array('readme.txt.zip', 'SF_auth.sql.zip', 'think.zip', 'README.md.zip', 'wwwroot.zip', 'www.zip', 'web.zip', 'bf.zip', 'beifen.zip', 'backup.zip', 'yuanma.zip', '1.zip', '2.zip', 'shouquan.zip', 'sq.zip', 'sf.zip', 'wz.zip', '1.zip', '2.zip', '123.zip');
        foreach ($SF_zip_arr as $SF_zip) {
            if (file_exists(ROOT_PATH . $SF_zip)) {
                unlink(ROOT_PATH . $SF_zip);
            }
        }
        $SF_ALL = glob(ROOT_PATH . 'SF授权系统*');
        foreach ($SF_ALL as $SF_all) {
            unlink($SF_all);
        }
        /***********************压缩包检测区 结束***********************/

        /***********************系统环境检测区 开始***********************/
        $SF_danger = array();
        $SF_warning = array();
        $SF_info = array();
        if (strpos($_SERVER['SERVER_SOFTWARE'], 'kangle') !== false && function_exists('pcntl_exec')) {
            $SF_danger[] = t('safe_report.kangle_pcntl');
        }
        if (strpos($_SERVER["SERVER_SOFTWARE"], "kangle") !== false && count(glob("/vhs/kangle/etc/*")) > 1) {
            $SF_danger[] = t('safe_report.kangle_open_basedir');
        }
        /***********************系统环境检测区 结束***********************/

        /***********************异地登陆检测区 开始***********************/
        $loginlognum = Db::name('loginlog')
            ->where('power', 'admin')
            ->where('status', 0)
            ->where('uid', $this->adminId)
            ->count();
        if($loginlognum > 0){
            $SF_danger[] = t('safe_report.remote_login_records', ['count' => $loginlognum, 'url' => 'Log/Login']);
        }
        /***********************异地登陆检测区 结束***********************/

        /***********************密码强度检测区 开始***********************/
        if (conf('second_pwd') === '123456') {
            $SF_warning[] = t('safe_report.default_secondary_password', ['url' => 'EditPassword/editSecondPassword']);
        } else {
            if (strlen(conf('second_pwd')) < 6 || is_numeric(conf('second_pwd')) && strlen(conf('second_pwd')) <= 10 || conf('second_pwd') === conf('kfqq')) {
                $SF_warning[] = t('safe_report.secondary_password_weak');
            } else {
                if ($this->adminInfo['username'] === conf('second_pwd')) {
                    $SF_warning[] = t('safe_report.secondary_password_matches_username');
                }
            }
        }

        if ($this->adminInfo['password'] === '123456') {
            $SF_warning[] = t('safe_report.default_admin_password', ['url' => 'EditPassword']);
        } else {
            if (strlen($this->adminInfo['password']) < 6 || is_numeric($this->adminInfo['password']) && strlen($this->adminInfo['password']) <= 10 || $this->adminInfo['password'] === conf('kfqq') || $this->adminInfo['password'] === $this->adminInfo['qq']) {
                $SF_warning[] = t('safe_report.admin_password_weak');
            } else {
                if ($this->adminInfo['username'] === $this->adminInfo['password']) {
                    $SF_warning[] = t('safe_report.admin_password_matches_username');
                }
            }
        }
        if ($this->checkPassword($this->adminInfo['password']) >0 && $this->checkPassword($this->adminInfo['password']) <= 4 ) {
            $SF_warning[] = t('safe_report.password_strength_weak');
        }else if ($this->checkPassword($this->adminInfo['password']) >=5 && $this->checkPassword($this->adminInfo['password']) <= 7 ) {
            $SF_info[] = t('safe_report.password_strength_medium');
        }
        /***********************密码强度检测区 结束***********************/

        /***********************数据库密码强度检测区 开始***********************/
        $dbconfig = config('database.connections.mysql');
        if (strlen($dbconfig['password']) < 5 || is_numeric($dbconfig['password']) && strlen($dbconfig['password']) <= 10 || $dbconfig['password'] === conf('kfqq')) {
            $SF_warning[] = t('safe_report.database_password_weak');
        } else {
            if ($dbconfig['password'] === $dbconfig['username']) {
                $SF_warning[] = t('safe_report.database_password_matches_username');
            }
        }
        /***********************数据库密码强度检测区 结束***********************/

        /***********************压缩包检测区 开始***********************/
        $SF_all_zip = glob(ROOT_PATH . '*.zip');
        $SF_all_7z = glob(ROOT_PATH . '*.7z');
        $SF_all_rar = glob(ROOT_PATH . '*.rar');
        if ($SF_all_zip && count($SF_all_zip) > 0 || $SF_all_7z && count($SF_all_7z) > 0 || $SF_all_rar && count($SF_all_rar) > 0) {
            $SF_info[] = t('safe_report.archive_in_root');
        }
        /***********************压缩包检测区 结束***********************/
        $SF_msg = array("danger" => $SF_danger, "warning" => $SF_warning, "info" => $SF_info);
        return $SF_msg;
    }
}
