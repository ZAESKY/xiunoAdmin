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
            $SF_danger[] = '当前主机为kangle且开启了php的pcntl组件，会被黑客入侵，请联系主机商修复或更换主机';
        }
        if (strpos($_SERVER['SERVER_SOFTWARE'], 'kangle') !== false && count(glob('/vhs/kangle/etc/*')) > 1) {
            $SF_danger[] = '当前主机为kangle且未设置open_basedir防跨站，会被黑客入侵，请联系主机商修复或更换主机';
        }
        /***********************系统环境检测区 结束***********************/

        /***********************密码强度检测区 开始***********************/
        if (conf('second_pwd') === '123456') {
            $SF_warning[] = '请及时修改默认二级密码 <a ew-href="'.url('/Set/index').'" class="layui-btn layui-btn-xs">修改密码</a>';
        } else {
            if (strlen(conf('second_pwd')) < 6 || is_numeric(conf('second_pwd')) && strlen(conf('second_pwd')) <= 10 || conf('second_pwd') === conf('kfqq')) {
                $SF_warning[] = '二级密码过于简单，请不要使用较短的纯数字或自己的QQ号当做密码';
            } else {
                if ($this->adminInfo['username'] === conf('second_pwd')) {
                    $SF_warning[] = '网站管理员用户名与二级密码相同，极易被黑客破解，请及时修改密码';
                }
            }
        }

        if ($this->adminInfo['password'] === '123456') {
            $SF_warning[] = '请及时修改默认管理员密码 <a ew-href="'.url('/Index/EditPassword').'" class="layui-btn layui-btn-xs">修改密码</a>';
        } else {
            if (strlen($this->adminInfo['password']) < 6 || is_numeric($this->adminInfo['password']) && strlen($this->adminInfo['password']) <= 10 || $this->adminInfo['password'] === conf('kfqq') || $this->adminInfo['password'] === $this->adminInfo['qq']) {
                $SF_warning[] = '网站管理员密码过于简单，请不要使用较短的纯数字或自己的QQ号当做密码';
            } else {
                if ($this->adminInfo['username'] === $this->adminInfo['password']) {
                    $SF_warning[] = '网站管理员用户名与密码相同，极易被黑客破解，请及时修改密码';
                }
            }
        }
        if ($this->checkPassword($this->adminInfo['password']) >0 && $this->checkPassword($this->adminInfo['password']) <= 4 ) {
            $SF_warning[] = '<b style="color:red">弱</b> 建议使用复杂一点管理员密码';
        }else if ($this->checkPassword($this->adminInfo['password']) >=5 && $this->checkPassword($this->adminInfo['password']) <= 7 ) {
            $SF_info[] = '<b style="color:orange">中</b> 当前密码等级较强，可适当加些符号提高管理员密码难度';
        }
        /***********************密码强度检测区 结束***********************/

        /***********************数据库密码强度检测区 开始***********************/
        $dbconfig = config('database.connections.mysql');
        if (strlen($dbconfig['password']) < 5 || is_numeric($dbconfig['password']) && strlen($dbconfig['password']) <= 10 || $dbconfig['password'] === conf('kfqq')) {
            $SF_warning[] = '当前主机的数据库密码过于简单，请不要使用较短的纯数字或自己的QQ号当做数据库密码';
        } else {
            if ($dbconfig['password'] === $dbconfig['username']) {
                $SF_warning[] = '当前主机的数据库用户名与密码相同，极易被黑客破解，请及时修改数据库密码';
            }
        }
        /***********************数据库密码强度检测区 结束***********************/

        /***********************压缩包检测区 开始***********************/
        $SF_all_zip = glob(ROOT_PATH . '*.zip');
        $SF_all_7z = glob(ROOT_PATH . '*.7z');
        $SF_all_rar = glob(ROOT_PATH . '*.rar');
        if ($SF_all_zip && count($SF_all_zip) > 0 || $SF_all_7z && count($SF_all_7z) > 0 || $SF_all_rar && count($SF_all_rar) > 0) {
            $SF_info[] = '网站根目录存在压缩包文件，可能会被人恶意获取并泄露数据库密码，请及时删除';
        }
        /***********************压缩包检测区 结束***********************/
        $SF_msg = array("danger" => $SF_danger, "warning" => $SF_warning, "info" => $SF_info);
        return $SF_msg;
    }
}
