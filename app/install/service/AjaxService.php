<?php
namespace app\install\service;

use app\common\service\BaseService;
use Symfony\Component\VarExporter\VarExporter;

class AjaxService extends BaseService
{
    public function check()
    {
        if(file_exists(APP_PATH . DS . 'install' . DS . 'SF_Auth.Lock')){
            return message('您已经安装过，如需重新安装请删除app/install/SF_Auth.Lock文件后再安装！', false);
        }
        $check_msg = $this->checkSafeMsg();
        $error = count($check_msg['error']);
        $safenum = intval(100 - $error * 10);

        return message('检测成功', true, ['safe' => $safenum, 'check_msg' => $check_msg]);
    }

    private function checkSafeMsg()
    {
        session('SF_CheckSession', 2129876388);
        $success = array();
        $error = array();
        if (class_exists('PDO')) {
            $success[] = 'PDO';
        } else {
            $error[] = 'PDO';
        }
        if (version_compare(PHP_VERSION, '7.1', '>')) {
            $success[] = 'PHP7.1+ 当前' . PHP_VERSION;
        } else {
            $error[] = 'PHP7.1+ 当前' . PHP_VERSION;
        }
        if ($fp = @fopen(__DIR__ . '/musicAnalysis.txt', 'w')) {
            @fclose($fp);
            @unlink(__DIR__ . '/musicAnalysis.txt');
            $success[] = '写入文件';
        } else {
            $error[] = '写入文件';
        }
        if (function_exists('file_get_contents')) {
            $success[] = 'file_get_contents()';
        } else {
            $error[] = 'file_get_contents()';
        }
        if (function_exists('curl_exec')) {
            $success[] = 'curl_exec()';
        } else {
            $error[] = 'curl_exec()';
        }
        if (function_exists('mkdir')) {
            $success[] = 'mkdir()';
        } else {
            $error[] = 'mkdir()';
        }
        if (session('SF_CheckSession') == 2129876388) {
            $success[] = 'session()';
        } else {
            $error[] = 'session()';
        }
        if (extension_loaded('fileinfo')){
            $success[] = 'fileinfo()';
        }else{
            $error[] = 'fileinfo()';
        }

        $SF_msg = array('success' => $success, 'error' => $error);
        return $SF_msg;
    }

    public function install()
    {
        if(file_exists(APP_PATH . DS . 'install' . DS . 'SF_Auth.Lock')){
            return message('您已经安装过，如需重新安装请删除app/install/SF_Auth.Lock文件后再安装！', false);
        }
        $post = request()->post();
        $hostname = !empty($post['hostname']) ? $post['hostname'] : null;
        $hostport = !empty($post['hostport']) ? intval($post['hostport']) : null;
        $username = !empty($post['username']) ? $post['username'] : null;
        $password = !empty($post['password']) ? $post['password'] : null;
        $database = !empty($post['database']) ? $post['database'] : null;
        if (empty($hostname)) {
            return message('请填写数据库地址！', false);
        }
        if (empty($hostport)) {
            return message('请填写数据库端口！', false);
        }
        if (empty($username)) {
            return message('请填写数据库用户名！', false);
        }
        if (empty($password)) {
            return message('请填写数据库密码！', false);
        }
        if (empty($database)) {
            return message('请填写数据库库名！', false);
        }
        $config = config('database');
        $config['connections']['mysql']['hostname'] = $hostname;
        $config['connections']['mysql']['database'] = $database;
        $config['connections']['mysql']['username'] = $username;
        $config['connections']['mysql']['password'] = $password;
        $config['connections']['mysql']['hostport'] = $hostport;
        $config['connections']['mysql']['prefix'] = 'SF_';
        try {
            $db = new \PDO('mysql:host=' . $hostname . ';dbname=' . $database . ';port=' . $hostport, $username, $password);
            $file = ROOT_PATH . 'config/database.php';
            if (!is_really_writable($file)) {
                return message('无法写入配置，请手动更改数据库配置！', false);
            }

            if ($handle = fopen($file, 'w')) {
                fwrite($handle, "<?php\n\n" . "return " . VarExporter::export($config) . ";\n");
                fclose($handle);
            } else {
                return message('无法写入配置，请手动更改数据库配置！', false);
            }
            if (!$db->query("SELECT * FROM `SF_config` where 1")) {
                return message('数据库配置文件保存成功！', true, ['type' => 1]);
            } else {
                return message('数据库配置文件保存成功！', true, ['type' => 2]);
            }
        } catch (\Exception $e) {
            return message('连接数据库失败' . $e->getMessage(), false);
        }

    }

    public function importSQL()
    {
        if (file_exists(APP_PATH . DS . 'install' . DS . 'SF_Auth.Lock')) {
            return message('您已经安装过，如需重新安装请删除app/install/SF_Auth.Lock文件后再安装！', false);
        } else {
            $dbconfig = config('database.connections.mysql');
            if (!$dbconfig['username'] || !$dbconfig['password'] || !$dbconfig['database']) {
                return message('请先填写好数据库并保存后再安装！', false);
            } else {
                $sql = file_get_contents(APP_PATH . DS . 'SF_Auth.sql');
                $sql = explode(';', $sql);
                try {
                    $cn = new \PDO('mysql:host=' . $dbconfig['hostname'] . ';dbname=' . $dbconfig['database'] . ';port=' . $dbconfig['hostport'], $dbconfig['username'], $dbconfig['password']);
                } catch (\Exception $e) {
                    return message('连接数据库失败' . $e->getMessage(), false);
                }

                $cn->exec("set sql_mode = ''");
                $cn->exec("set names utf8");
                $t = 0;
                $e = 0;

                $error = '';
                for ($i = 0; $i < count($sql); $i++) {
                    if ($sql[$i] == '') continue;
                    try {
                        $cn->exec($sql[$i]);
                        ++$t;
                    } catch (\PDOException $e) {
                        ++$e;
                        $error .= $e->getMessage() . '<br/>';
                    }
                }
            }
            if ($e == 0) {
                return message('安装成功！SQL导入成功' . $t . '句/失败' . $e . '句', true);
            } else {
                return message('安装失败！SQL导入成功' . $t . '句/失败' . $e . '句,错误信息：' . $error, false);
            }
        }
    }
    public function bindingCheck(){
        if(file_exists(APP_PATH . DS . 'install' . DS . 'SF_Auth.Lock')){
            return message('您已经安装过，如需重新安装请删除app/install/SF_Auth.Lock文件后再安装！', false);
        }
        $dbconfig = config('database.connections.mysql');
        if (!$dbconfig['username'] || !$dbconfig['password'] || !$dbconfig['database']) {
            return message('请先填写好数据库并保存后再安装！', false);
        }
        try {
            $db = new \PDO('mysql:host=' . $dbconfig['hostname'] . ';dbname=' . $dbconfig['database'] . ';port=' . $dbconfig['hostport'], $dbconfig['username'], $dbconfig['password']);
        } catch (\Exception $e) {
            return message('连接数据库失败' . $e->getMessage(), false);
        }
        $result = $db->query("SELECT access_token FROM `SF_admin` WHERE id = 1 limit 1");
        $result = $result->fetch(\PDO::FETCH_ASSOC);
        if(empty($result['access_token'])){
            return message('暂未绑定', false);
        }else{
            return message('已绑定', true);
        }
    }

    public function adminInfo(){
        if(file_exists(APP_PATH . DS . 'install' . DS . 'SF_Auth.Lock')){
            return message('您已经安装过，如需重新安装请删除app/install/SF_Auth.Lock文件后再安装！', false);
        }
        $post = request()->post();
        $admin_username = !empty($post['admin_username']) ? $post['admin_username'] : null;
        $admin_password = !empty($post['admin_password']) ? $post['admin_password'] : null;
        $admin_qq = !empty($post['admin_qq']) ? intval($post['admin_qq']) : null;
        $admin_email = !empty($post['admin_email']) ? $post['admin_email'] : null;
        $sitename = !empty($post['sitename']) ? $post['sitename'] : null;
        $login_address = !empty($post['login_address']) ? $post['login_address'] : 'admin';
        $SF_LOGIN_KEY = !empty($post['SF_LOGIN_KEY']) ? $post['SF_LOGIN_KEY'] : '';

        session('login_address', $login_address);
        if (empty($admin_username)) {
            return message('请填写管理员账号！', false);
        }
        if (empty($admin_password)) {
            return message('请填写数据库端口！', false);
        }
        if (empty($admin_qq)) {
            return message('请填写管理员QQ！', false);
        }
        if (empty($admin_email)) {
            return message('请填写管理员邮箱！', false);
        }
        if (empty($sitename)) {
            return message('请填写站点名称！', false);
        }

        $dbconfig = config('database.connections.mysql');
        if (!$dbconfig['username'] || !$dbconfig['password'] || !$dbconfig['database']) {
            return message('请先填写好数据库并保存后再安装！', false);
        }
        try {
            $db = new \PDO('mysql:host=' . $dbconfig['hostname'] . ';dbname=' . $dbconfig['database'] . ';port=' . $dbconfig['hostport'], $dbconfig['username'], $dbconfig['password']);
        } catch (\Exception $e) {
            return message('连接数据库失败' . $e->getMessage(), false);
        }

        $db->exec("update SF_admin set `username` = '".$admin_username."', `password` = '".$admin_password."', `qq` = '".$admin_qq."', `email` = '$admin_email' where `id` = 1");
        $db->exec("update SF_config set `value` = '".$sitename."' where `name` = 'title'");
        $db->exec("update SF_config set `value` = '".$SF_LOGIN_KEY."' where `name` = 'login_key'");
        session('SF_LOGIN_KEY', $SF_LOGIN_KEY);
        rename(PUBLIC_PATH . DS . 'admin.php',$login_address.'.php');
        return message('保存配置成功', true);
    }

    public function putInstallLock(){
        @file_put_contents(APP_PATH . DS . 'install' . DS . 'SF_Auth.Lock', 'SF 授权系统安装锁');
        if (file_exists(APP_PATH . DS . 'install' . DS . 'SF_Auth.Lock')) {
            return message('success', true);
        } else {
            return message('error', false);
        }
    }
}