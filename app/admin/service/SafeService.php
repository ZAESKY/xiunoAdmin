<?php

namespace app\admin\service;

use app\common\service\BaseService;
use app\admin\model\Admin;
/**
 * 安全中心-服务类
 * @author 陌上花开
 * @since 2022/1/3
 * Class SafeService
 * @package app\admin\service
 */

class SafeService extends BaseService
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

        return message(t('system.detect_success'), true, ['safe' => $safenum, 'check_msg' => $check_msg]);
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
        // 安全检测必须保持只读。旧实现会在页面检测时直接删除命中特定名称的压缩包，
        // 既不可回滚，也可能误删部署备份；下方仅报告压缩包风险，不再改动文件。

        /***********************系统环境检测区 开始***********************/
        $SF_danger = array();
        $SF_warning = array();
        $SF_info = array();
        if (strpos($_SERVER['SERVER_SOFTWARE'], 'kangle') !== false && function_exists('pcntl_exec')) {
            $SF_danger[] = t('safe_report.kangle_pcntl');
        }
        if (strpos($_SERVER['SERVER_SOFTWARE'], 'kangle') !== false && count(glob('/vhs/kangle/etc/*')) > 1) {
            $SF_danger[] = t('safe_report.kangle_open_basedir');
        }
        /***********************系统环境检测区 结束***********************/

        /***********************密码强度检测区 开始***********************/
        $secondPwd = (string) (conf('second_pwd') ?? '');
        $kfqq = (string) (conf('kfqq') ?? '');
        $adminUsername = (string) ($this->adminInfo['username'] ?? '');
        $adminPassword = (string) ($this->adminInfo['password'] ?? '');
        $adminQq = (string) ($this->adminInfo['qq'] ?? '');

        if ($secondPwd === '123456') {
            $SF_warning[] = t('safe_report.default_secondary_password', ['url' => url('/Set/index')]);
        } else {
            if (strlen($secondPwd) < 6 || is_numeric($secondPwd) && strlen($secondPwd) <= 10 || $secondPwd === $kfqq) {
                $SF_warning[] = t('safe_report.secondary_password_weak');
            } else {
                if ($adminUsername === $secondPwd) {
                    $SF_warning[] = t('safe_report.secondary_password_matches_username');
                }
            }
        }

        if ($adminPassword === '123456') {
            $SF_warning[] = t('safe_report.default_admin_password', ['url' => url('/Index/EditPassword')]);
        } else {
            if (strlen($adminPassword) < 6 || is_numeric($adminPassword) && strlen($adminPassword) <= 10 || $adminPassword === $kfqq || $adminPassword === $adminQq) {
                $SF_warning[] = t('safe_report.admin_password_weak');
            } else {
                if ($adminUsername === $adminPassword) {
                    $SF_warning[] = t('safe_report.admin_password_matches_username');
                }
            }
        }
        if ($this->checkPassword($adminPassword) >0 && $this->checkPassword($adminPassword) <= 4 ) {
            $SF_warning[] = t('safe_report.password_strength_weak');
        }else if ($this->checkPassword($adminPassword) >=5 && $this->checkPassword($adminPassword) <= 7 ) {
            $SF_info[] = t('safe_report.password_strength_medium');
        }
        /***********************密码强度检测区 结束***********************/

        /***********************数据库密码强度检测区 开始***********************/
        $dbconfig = config('database.connections.mysql');
        $dbPassword = (string) ($dbconfig['password'] ?? '');
        $dbUsername = (string) ($dbconfig['username'] ?? '');
        if (strlen($dbPassword) < 5 || is_numeric($dbPassword) && strlen($dbPassword) <= 10 || $dbPassword === $kfqq) {
            $SF_warning[] = t('safe_report.database_password_weak');
        } else {
            if ($dbPassword === $dbUsername) {
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
