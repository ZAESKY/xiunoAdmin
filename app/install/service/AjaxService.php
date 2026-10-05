<?php
namespace app\install\service;

use app\common\service\BaseService;
use Symfony\Component\VarExporter\VarExporter;

class AjaxService extends BaseService
{
    public function check()
    {
        if(file_exists(APP_PATH . DS . 'install' . DS . 'QH_Auth.Lock')){
            return message(t('install.already_installed'), false);
        }
        $check_msg = $this->checkSafeMsg();
        $error = count($check_msg['error']);
        $safenum = intval(100 - $error * 10);

        return message(t('system.detect_success'), true, ['safe' => $safenum, 'check_msg' => $check_msg]);
    }

    private function checkSafeMsg()
    {
        session('QH_CheckSession', 2129876388);
        $success = array();
        $error = array();
        if (class_exists('PDO')) {
            $success[] = 'PDO';
        } else {
            $error[] = 'PDO';
        }
        if (version_compare(PHP_VERSION, '7.1', '>')) {
            $success[] = 'PHP 7.1+ (' . t('install_bind.current') . ': ' . PHP_VERSION . ')';
        } else {
            $error[] = 'PHP 7.1+ (' . t('install_bind.current') . ': ' . PHP_VERSION . ')';
        }
        if ($fp = @fopen(__DIR__ . '/musicAnalysis.txt', 'w')) {
            @fclose($fp);
            @unlink(__DIR__ . '/musicAnalysis.txt');
            $success[] = t('validation.file_no_write');
        } else {
            $error[] = 'File write';
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
        if (session('QH_CheckSession') == 2129876388) {
            $success[] = 'session()';
        } else {
            $error[] = 'session()';
        }
        if (extension_loaded('fileinfo')){
            $success[] = 'fileinfo()';
        }else{
            $error[] = 'fileinfo()';
        }

        $QH_msg = array('success' => $success, 'error' => $error);
        return $QH_msg;
    }

    public function install()
    {
        if(file_exists(APP_PATH . DS . 'install' . DS . 'QH_Auth.Lock')){
            return message(t('install.already_installed'), false);
        }
        $post = request()->post();
        $hostname = !empty($post['hostname']) ? $post['hostname'] : null;
        $hostport = !empty($post['hostport']) ? intval($post['hostport']) : null;
        $username = !empty($post['username']) ? $post['username'] : null;
        $password = !empty($post['password']) ? $post['password'] : null;
        $database = !empty($post['database']) ? $post['database'] : null;
        if (empty($hostname)) {
            return message(t('install.db_host_empty'), false);
        }
        if (empty($hostport)) {
            return message(t('install.db_port_empty'), false);
        }
        if (empty($username)) {
            return message(t('install.db_user_empty'), false);
        }
        if (empty($password)) {
            return message(t('install.db_pass_empty'), false);
        }
        if (empty($database)) {
            return message(t('install.db_name_empty'), false);
        }
        $config = config('database');
        $config['connections']['mysql']['hostname'] = $hostname;
        $config['connections']['mysql']['database'] = $database;
        $config['connections']['mysql']['username'] = $username;
        $config['connections']['mysql']['password'] = $password;
        $config['connections']['mysql']['hostport'] = $hostport;
        $config['connections']['mysql']['prefix'] = 'QH_';
        try {
            $db = new \PDO('mysql:host=' . $hostname . ';dbname=' . $database . ';port=' . $hostport, $username, $password);
            $file = ROOT_PATH . 'config/database.php';
            if (!is_really_writable($file)) {
                return message(t('install.db_write_failed'), false);
            }

            if ($handle = fopen($file, 'w')) {
                fwrite($handle, "<?php\n\n" . "return " . VarExporter::export($config) . ";\n");
                fclose($handle);
            } else {
                return message(t('install.db_write_failed'), false);
            }
            if (!$db->query("SELECT * FROM `QH_config` where 1")) {
                return message(t('install.db_config_saved'), true, ['type' => 1]);
            } else {
                return message(t('install.db_config_saved'), true, ['type' => 2]);
            }
        } catch (\Exception $e) {
            return message(t('install.db_connect_failed').$e->getMessage(), false);
        }

    }

    public function importSQL()
    {
        if (file_exists(APP_PATH . DS . 'install' . DS . 'QH_Auth.Lock')) {
            return message(t('install.already_installed'), false);
        } else {
            $dbconfig = config('database.connections.mysql');
            if (!$dbconfig['username'] || !$dbconfig['password'] || !$dbconfig['database']) {
                return message(t('install.db_need_first'), false);
            } else {
                $sql = file_get_contents(APP_PATH . DS . 'QH_Auth.sql');
                $sql = explode(';', $sql);
                try {
                    $cn = new \PDO('mysql:host=' . $dbconfig['hostname'] . ';dbname=' . $dbconfig['database'] . ';port=' . $dbconfig['hostport'], $dbconfig['username'], $dbconfig['password']);
                } catch (\Exception $e) {
                    return message(t('install.db_connect_failed').$e->getMessage(), false);
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
                return message(t('install.sql_success', ['success' => $t, 'failed' => $e]), true);
            } else {
                return message(t('install.sql_failed', ['success' => $t, 'failed' => $e]) . ', ' . t('common.server_error') . ': ' . $error, false);
            }
        }
    }
    public function bindingCheck(){
        if(file_exists(APP_PATH . DS . 'install' . DS . 'QH_Auth.Lock')){
            return message(t('install.already_installed'), false);
        }
        $dbconfig = config('database.connections.mysql');
        if (!$dbconfig['username'] || !$dbconfig['password'] || !$dbconfig['database']) {
            return message(t('install.db_need_first'), false);
        }
        try {
            $db = new \PDO('mysql:host=' . $dbconfig['hostname'] . ';dbname=' . $dbconfig['database'] . ';port=' . $dbconfig['hostport'], $dbconfig['username'], $dbconfig['password']);
        } catch (\Exception $e) {
            return message(t('install.db_connect_failed').$e->getMessage(), false);
        }
        $result = $db->query("SELECT access_token FROM `QH_admin` WHERE id = 1 limit 1");
        $result = $result->fetch(\PDO::FETCH_ASSOC);
        if(empty($result['access_token'])){
            return message(t('install_bind.not_bound'), false);
        }else{
            return message(t('install_bind.bound'), true);
        }
    }

    public function adminInfo(){
        if(file_exists(APP_PATH . DS . 'install' . DS . 'QH_Auth.Lock')){
            return message(t('install.already_installed'), false);
        }
        $post = request()->post();
        $admin_username = !empty($post['admin_username']) ? $post['admin_username'] : null;
        $admin_password = !empty($post['admin_password']) ? $post['admin_password'] : null;
        $admin_qq = !empty($post['admin_qq']) ? intval($post['admin_qq']) : null;
        $admin_email = !empty($post['admin_email']) ? $post['admin_email'] : null;
        $sitename = !empty($post['sitename']) ? $post['sitename'] : null;
        $login_address = !empty($post['login_address']) ? $post['login_address'] : 'admin';
        $QH_LOGIN_KEY = !empty($post['QH_LOGIN_KEY']) ? $post['QH_LOGIN_KEY'] : '';

        session('login_address', $login_address);
        if (empty($admin_username)) {
            return message(t('install.admin_account_empty'), false);
        }
        if (empty($admin_password)) {
            return message(t('install.admin_password_empty'), false);
        }
        if (empty($admin_qq)) {
            return message(t('install.admin_qq_empty'), false);
        }
        if (empty($admin_email)) {
            return message(t('install.admin_email_empty'), false);
        }
        if (empty($sitename)) {
            return message(t('install.site_name_empty'), false);
        }

        $dbconfig = config('database.connections.mysql');
        if (!$dbconfig['username'] || !$dbconfig['password'] || !$dbconfig['database']) {
            return message(t('install.db_need_first'), false);
        }
        try {
            $db = new \PDO('mysql:host=' . $dbconfig['hostname'] . ';dbname=' . $dbconfig['database'] . ';port=' . $dbconfig['hostport'], $dbconfig['username'], $dbconfig['password']);
        } catch (\Exception $e) {
            return message(t('install.db_connect_failed').$e->getMessage(), false);
        }

        $stmt = $db->prepare("update QH_admin set `username` = ?, `password` = ?, `qq` = ?, `email` = ? where `id` = 1");
        $stmt->execute([$admin_username, qh_password_make($admin_password), $admin_qq, $admin_email]);
        $stmt = $db->prepare("update QH_config set `value` = ? where `name` = 'title'");
        $stmt->execute([$sitename]);
        $stmt = $db->prepare("update QH_config set `value` = ? where `name` = 'login_key'");
        $stmt->execute([$QH_LOGIN_KEY]);
        session('QH_LOGIN_KEY', $QH_LOGIN_KEY);
        rename(PUBLIC_PATH . DS . 'admin.php',$login_address.'.php');
        return message(t('install.config_save_success'), true);
    }

    public function putInstallLock(){
        @file_put_contents(APP_PATH . DS . 'install' . DS . 'QH_Auth.Lock', 'QH 授权系统安装锁');
        if (file_exists(APP_PATH . DS . 'install' . DS . 'QH_Auth.Lock')) {
            return message('success', true);
        } else {
            return message('error', false);
        }
    }
}
