<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\SetService;
use app\common\service\PluginCommissionService;
use app\common\service\SecretConfigService;
use app\common\service\EmailNotificationService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use Symfony\Component\VarExporter\VarExporter;

class Set extends Backend
{
    public function initialize()
    {
        parent::initialize();
        $this->request->filter(['trim','addslashes']);
        $this->service = new SetService();
    }

    public function index(){
        try{
            $siteList = [];
            $groupList = SetService::getGroupList();
            foreach ($groupList as $k => $v) {
                $siteList[$k]['name'] = $k;
                $siteList[$k]['title'] = $v;
                $siteList[$k]['list'] = [];
            }
            foreach ($this->service->all() as $res) {
                if (!isset($siteList[$res['group']])) {
                    continue;
                }
                $res = $res->toArray();
                if (in_array($res['type'], ['select', 'selects', 'checkbox', 'radio'])) {
                    $res['value'] = explode(',', $res['value']);
                }
                if($res['type'] != 'config'){
                    $res['content'] = json_decode($res['content'], true);
                }
                if (in_array($res['name'], ['notice_home'], true)) {
                    $res['type'] = 'ueditor';
                }
                $res['is_secret'] = in_array($res['name'], ['oss_access_key_secret', 'sms_access_key_secret'], true);
                if ($res['is_secret']) {
                    // Never render the stored secret back into HTML. An empty
                    // field on save means "keep the current value".
                    $res['value'] = '';
                    $res['tip'] = t('settings.secret_tip');
                }
                $res['tip'] = $res['tip'];
                $siteList[$res['group']]['list'][] = $res;
            }
            $index = 0;
            foreach ($siteList as $k => &$v) {
                $v['active'] = !$index ? true : false;
                $index++;
            }
            View::assign('siteList', $siteList);
            return $this->render();
        }catch (\Exception $e){
            return $this->render('/public/error', ['msg' => $e->getMessage()]);
        }
    }

    public function edit()
    {
        if ($this->request->isPost()) {
            $row = $this->request->post("row/a", [], 'trim,html_entity_decode');
            // return json($row);
            if ($row) {
                $smsError = $this->validateSmsSettings($row);
                if ($smsError !== null) {
                    return message($smsError, false);
                }
                $recoveryError = $this->validatePasswordRecoverySettings($row);
                if ($recoveryError !== null) {
                    return message($recoveryError, false);
                }
                $configList = [];
                foreach ($this->service->all() as $v) {
                    if($v['type'] == 'bool'){
                        // Dedicated configuration pages (for example check-in)
                        // are not part of this form. Missing boolean fields must
                        // remain unchanged instead of being silently disabled.
                        if (!array_key_exists($v['name'], $row)) {
                            continue;
                        }
                        $value = (int)$row[$v['name']] === 1 ? 1 : 0;
                        $v['value'] = $value;
                        $configList[] = $v->toArray();
                    }else{
                        if (isset($row[$v['name']])) {
                            $value = $row[$v['name']];
                            if (in_array($v['name'], ['oss_access_key_secret', 'sms_access_key_secret'], true)
                                && trim((string)$value) === '') {
                                continue;
                            }
                            if ($v['name'] === 'sms_access_key_secret') {
                                try {
                                    $value = SecretConfigService::encrypt((string)$value);
                                } catch (\Throwable $e) {
                                    return message($e->getMessage(), false);
                                }
                            }
                            if ($v['name'] === 'alipay_config' && is_array($value) && isset($value['field'])) {
                                $alipayConfig = $this->normalizeAlipayConfig(getArrayData($value));
                                if ((int)($row['alipay_api'] ?? 0) === 3) {
                                    $configError = $this->validateAlipayConfig($alipayConfig);
                                    if ($configError !== null) {
                                        return message($configError, false);
                                    }
                                }
                                $value = json_encode($alipayConfig, JSON_UNESCAPED_UNICODE);
                            } elseif (is_array($value) && isset($value['field'])) {
                                $value = json_encode(getArrayData($value), JSON_UNESCAPED_UNICODE);
                            } else {
                                $value = is_array($value) ? implode(',', array_values(array_filter($value))) : $value;
                            }
                            if ($v['name'] === 'plugin_commission_rate') {
                                try {
                                    $value = PluginCommissionService::validateRate($value);
                                } catch (\InvalidArgumentException $e) {
                                    return message($e->getMessage(), false);
                                }
                            }
                            if (in_array($v['name'], ['notice_home'], true)) {
                                $value = clean_rich_text($value);
                            }
                            $v['value'] = $value;
                            $configList[] = $v->toArray();
                        }
                    }
                }

                try {
                    $this->service->saveAll($configList);
                } catch (\Exception $e) {
                    return message($e->getMessage() ,false);
                }
                return message(t('system.save_success') ,true);
            }
            return message(t('system.save_failed') ,false);
        }
    }

    private function validateSmsSettings(array $row): ?string
    {
        $enabled = array_key_exists('sms_enabled', $row)
            ? intval($row['sms_enabled']) === 1
            : (string)conf('sms_enabled') === '1';
        foreach (['sms_code_ttl' => [120, 600], 'sms_daily_limit' => [1, 30]] as $name => $bounds) {
            if (!array_key_exists($name, $row)) {
                continue;
            }
            $value = intval($row[$name]);
            if ($value < $bounds[0] || $value > $bounds[1]) {
                return $name === 'sms_code_ttl'
                    ? t('settings.sms_ttl_range')
                    : t('settings.sms_daily_limit_range');
            }
        }
        if (!$enabled) {
            return null;
        }

        $accessKeyId = trim((string)($row['sms_access_key_id'] ?? conf('sms_access_key_id')));
        $secretInput = trim((string)($row['sms_access_key_secret'] ?? ''));
        $storedSecret = trim((string)conf('sms_access_key_secret'));
        $hasStoredSecret = $storedSecret !== '' && SecretConfigService::isEncrypted($storedSecret);
        $hasSecret = $secretInput !== '' || $hasStoredSecret || trim((string)env('sms_access_key_secret', '')) !== '';
        $signName = trim((string)($row['sms_sign_name'] ?? conf('sms_sign_name')));
        $templateFields = [
            'sms_template_login_register' => t('settings.sms_template_login_register'),
            'sms_template_phone_change' => t('settings.sms_template_phone_change'),
            'sms_template_password_reset' => t('settings.sms_template_password_reset'),
            'sms_template_phone_bind' => t('settings.sms_template_phone_bind'),
            'sms_template_phone_verify' => t('settings.sms_template_phone_verify'),
            'sms_template_code' => t('settings.sms_template_fallback'),
        ];

        if ($accessKeyId === '' || strlen($accessKeyId) > 128 || preg_match('/\s/', $accessKeyId)) {
            return t('settings.sms_access_key_id_invalid');
        }
        if (!$hasSecret) {
            return $storedSecret !== ''
                ? t('settings.sms_stored_secret_invalid')
                : t('settings.sms_secret_required');
        }
        if ($secretInput !== '' && (strlen($secretInput) > 512 || preg_match('/\s/', $secretInput))) {
            return t('settings.sms_secret_invalid');
        }
        if ($signName === '' || mb_strlen($signName) > 100 || preg_match('/[【】<>]/u', $signName)) {
            return t('settings.sms_sign_invalid');
        }
        $configuredTemplates = 0;
        foreach ($templateFields as $field => $label) {
            $templateCode = trim((string)($row[$field] ?? conf($field)));
            if ($templateCode === '') {
                continue;
            }
            $configuredTemplates++;
            if (!preg_match('/^(?:SMS_[0-9]{6,24}|[0-9]{6,20})$/D', $templateCode)) {
                return t('settings.sms_template_invalid', ['label' => $label]);
            }
        }
        if ($configuredTemplates === 0) {
            return t('settings.sms_template_required');
        }
        return null;
    }

    private function validatePasswordRecoverySettings(array $row): ?string
    {
        $channel = strtolower(trim((string)($row['password_recovery_channel'] ?? conf('password_recovery_channel') ?? 'email')));
        if (!in_array($channel, ['email', 'sms'], true)) {
            return t('settings.recovery_channel_invalid');
        }
        if ($channel !== 'sms') {
            return null;
        }
        $smsEnabled = array_key_exists('sms_enabled', $row)
            ? intval($row['sms_enabled']) === 1
            : (string)conf('sms_enabled') === '1';
        if (!$smsEnabled) {
            return t('settings.recovery_sms_not_configured');
        }
        return null;
    }

    private function normalizeAlipayConfig(array $config): array
    {
        return [
            'appid' => trim((string)($config['appid'] ?? '')),
            'publickey' => $this->normalizeRsaBody((string)($config['publickey'] ?? '')),
            'privatekey' => $this->normalizeRsaBody((string)($config['privatekey'] ?? '')),
        ];
    }

    private function normalizeRsaBody(string $key): string
    {
        $key = html_entity_decode(trim($key), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $key = preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----/', '', $key);
        return (string)preg_replace('/[^A-Za-z0-9+\/=]/', '', $key);
    }

    private function validateAlipayConfig(array $config): ?string
    {
        if (!preg_match('/^[0-9]{16,32}$/', $config['appid'])) {
            return t('settings.alipay_appid_invalid');
        }
        if (!$this->loadAlipayPublicKey($config['publickey'])) {
            return t('settings.alipay_public_key_invalid');
        }
        if (!$this->loadAlipayPrivateKey($config['privatekey'])) {
            return t('settings.alipay_private_key_invalid');
        }
        return null;
    }

    private function loadAlipayPublicKey(string $body)
    {
        if ($body === '') {
            return false;
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($body, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        return @openssl_pkey_get_public($pem);
    }

    private function loadAlipayPrivateKey(string $body)
    {
        if ($body === '') {
            return false;
        }
        foreach (['PRIVATE KEY', 'RSA PRIVATE KEY'] as $type) {
            $pem = "-----BEGIN {$type}-----\n" . wordwrap($body, 64, "\n", true) . "\n-----END {$type}-----";
            $key = @openssl_pkey_get_private($pem);
            if ($key) {
                return $key;
            }
        }
        return false;
    }

    private static function getServerUrl()
    {
        return config('sf.api_url');
    }

    private static function getClient()
    {
        $options = [
            'base_uri'        => self::getServerUrl(),
            'timeout'         => 30,
            'connect_timeout' => 30,
            'verify'          => true,
            'http_errors'     => false,
            'headers'         => [
                'X-REQUESTED-WITH' => 'XMLHttpRequest',
                'Referer'          => dirname(request()->root(true)),
                'User-Agent'       => 'FastAddon',
            ]
        ];
        static $client;
        if (empty($client)) {
            $client = new Client($options);
        }
        return $client;
    }
    public function checkUpdate(){
        if(IS_POST){
            // The legacy channel downloads executable ZIP/SQL payloads without
            // a signed manifest. Keep it disabled until the server supports the
            // signed release protocol used by Update V2.
            return message('update_ui.legacy_channel_disabled', false);
            /* @deprecated unreachable legacy implementation retained only for migration reference.
            try {
                $client = self::getClient();
                $response = $client->post('update', ['query' => ['version' => config('sf.version')]]);
                $body = $response->getBody();
                $content = $body->getContents();

                if (substr($content, 0, 1) === '{') {
                    $json = json_decode($content, true);
                    if(is_array($json)){
                        // 需要更新
                        session('updateSession', $content);
                        return message($json['msg'], true, $json['data']);
                    }else{
                        return message(t('system.update_check_failed'), false);
                    }
                    //如果传回的是一个下载链接,则再次下载
                }
            } catch (TransferException $e) {
                return message(t('system.update_check_failed'), false);
            }
            */
        }
    }

    public function updateVersion(){
        if(IS_POST){
            return message('update_ui.legacy_channel_disabled', false, ['code' => -1]);
            /* @deprecated unreachable legacy implementation retained only for migration reference.
            $json = session('updateSession');
            if(empty($json)){
                return message(t('system.update_retry'), false, ['code' => -1]);
            }
            $json = json_decode($json, true);
            if(!is_array($json)){
                return message(t('system.update_retry'), false, ['code' => -1]);
            }

            $tempCatalogue = RUNTIME_PATH . DS . 'update' . DS;
            if(!is_dir($tempCatalogue)){
                @mkdir($tempCatalogue, 0755, true);
            }

            $count = count($json['data']['download']);
            foreach ($json['data']['download'] as $k => $res){
                try {
                    $client = self::getClient();
                    $response = $client->get('download', ['query' => ['param' => $res]]);
                    $body = $response->getBody();
                    $content = $body->getContents();
                    if (substr($content, 0, 1) === '{') {
                        $json = (array)json_decode($content, true);
                        return message($json['msg'], false, ['code' => -1]);
                    }
                } catch (TransferException $e) {
                    return message(t('system.update_failed'), false, ['code' => -1]);
                }
                file_put_contents($tempCatalogue . $res, $content);
                $zip = new ZipFile();
                try {
                    $zip->openFile($tempCatalogue . $res);
                } catch (ZipException $e) {
                    return message(t('system.update_failed'), false, ['code' => -1]);
                }
                try {
                    $zip->extractTo(ROOT_PATH);
                    self::importsql();
                } catch (ZipException $e) {
                    return message(t('system.update_failed'), false, ['code' => -1]);
                }
                unset($json['data']['download'][$k]);
                Session::set('updateSession', json_encode($json));
                Session::save();
                return message(t('system.update_unzip_success', ['res' => $res]), true, ['code' => 1, 'count' => $count]);
            }
            $file = ROOT_PATH . DS . 'config' . DS . 'sf.php';
            $config = config('sf');
            $config['version'] = $json['data']['version'];
            $config['edition'] = $json['data']['edition'];
            if (!file_exists($file)) {
                file_put_contents($file, '');
            }
            if (!is_really_writable($file)) {
                return message(t('system.update_version_success', ['edition' => $config['edition']]), false, ['code' => -1]);
            }
            if ($handle = fopen($file, 'w')) {
                fwrite($handle, "<?php\n\n" . "return " . var_export($config, TRUE) . ";\n");
                fclose($handle);
            } else {
                return message(t('system.update_version_success', ['edition' => $config['edition']]), false, ['code' => -1]);
            }
            session('updateSession',null);
            return message(t('system.update_version_success', ['edition' => $config['edition']]), true, ['code' => 2]);
            */

        }
    }

    private static function importsql()
    {
        $sqlFile = ROOT_PATH . 'update.sql';
        if (is_file($sqlFile)) {
            $lines = file($sqlFile);
            $templine = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*') {
                    continue;
                }
                $templine .= $line;
                if (substr(trim($line), -1, 1) == ';') {
                    try {
                        Db::getPdo()->exec($templine);
                    } catch (\PDOException $e) {

                    }
                    $templine = '';
                }
            }
            @unlink($sqlFile);
        }
        return true;
    }

    public function update(){
        View::assign('edition', config('sf.edition'));
        return $this->render();
    }

    public function carousel()
    {
        if (!IS_POST) return $this->render();
        $post = $this->request->post();
        $action = $post['action'] ?? 'list';

        if ($action === 'edit') {
            $id = !empty($post['id']) ? intval($post['id']) : null;
            $title = !empty($post['title']) ? trim($post['title']) : '';
            $image = !empty($post['image']) ? trim($post['image']) : '';
            $url = !empty($post['url']) ? trim($post['url']) : '';
            $sort = isset($post['sort']) ? intval($post['sort']) : 0;
            if (empty($title)) return json(message('operation.title_required', false));
            if (empty($image)) return json(message('upload.please_select_image', false));
            if ($id) {
                \think\facade\Db::name('carousel')->where('id', $id)->data([
                    'title' => $title, 'image' => $image, 'url' => $url,
                    'sort' => $sort, 'updated_at' => datetime(),
                ])->update();
                return json(message('operation.update_success', true));
            } else {
                \think\facade\Db::name('carousel')->insert([
                    'title' => $title, 'image' => $image, 'url' => $url,
                    'sort' => $sort, 'status' => 1,
                    'created_at' => datetime(), 'updated_at' => datetime(),
                ]);
                return json(message('operation.add_success', true));
            }
        }
        if ($action === 'drop') {
            $id = intval($post['id'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('carousel')->where('id', $id)->delete();
                return json(message('operation.delete_success', true));
            }
            return json(message('operation.invalid_id', false));
        }
        if ($action === 'setStatus') {
            $id = intval($post['id'] ?? 0);
            $status = intval($post['status'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('carousel')->where('id', $id)->data(['status' => $status])->update();
                return json(message('operation.success', true));
            }
            return json(message('operation.invalid_id', false));
        }

        $list = \think\facade\Db::name('carousel')->order('sort', 'asc')->order('id', 'desc')->select()->toArray();
        return json([
            'code' => 0, 'msg' => '', 'count' => count($list), 'data' => $list,
        ]);
    }

    public function userNotice()
    {
        if (!IS_POST) return $this->render();
        $post = $this->request->post();
        $action = $post['action'] ?? 'list';

        if ($action === 'edit') {
            $id = !empty($post['id']) ? intval($post['id']) : null;
            $title = !empty($post['title']) ? trim($post['title']) : '';
            $content = $post['content'] ?? '';
            $sort = isset($post['sort']) ? intval($post['sort']) : 0;
            if (empty($title)) return json(message('operation.title_required', false));
            if (empty($content)) return json(message('operation.content_required', false));
            if ($id) {
                \think\facade\Db::name('user_notice')->where('id', $id)->data([
                    'title' => $title, 'content' => $content,
                    'sort' => $sort, 'updated_at' => datetime(),
                ])->update();
                return json(message('operation.update_success', true));
            } else {
                \think\facade\Db::name('user_notice')->insert([
                    'title' => $title, 'content' => $content,
                    'sort' => $sort, 'status' => 1,
                    'created_at' => datetime(), 'updated_at' => datetime(),
                ]);
                return json(message('operation.add_success', true));
            }
        }
        if ($action === 'drop') {
            $id = intval($post['id'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('user_notice')->where('id', $id)->delete();
                return json(message('operation.delete_success', true));
            }
            return json(message('operation.invalid_id', false));
        }
        if ($action === 'setStatus') {
            $id = intval($post['id'] ?? 0);
            $status = intval($post['status'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('user_notice')->where('id', $id)->data(['status' => $status])->update();
                return json(message('operation.success', true));
            }
            return json(message('operation.invalid_id', false));
        }

        $list = \think\facade\Db::name('user_notice')->order('sort', 'asc')->order('id', 'desc')->select()->toArray();
        return json([
            'code' => 0, 'msg' => '', 'count' => count($list), 'data' => $list,
        ]);
    }

    public function emailNotification()
    {
        if (!IS_POST) {
            View::assign([
                'emailNotificationEnabled' => (string)conf('email_notification_enabled') === '1',
                'adminEmailEvents' => EmailNotificationService::catalog('admin', intval($this->adminId)),
                'mailTransportStatus' => EmailNotificationService::mailTransportStatus(),
                'adminEmail' => (string)($this->adminInfo['email'] ?? ''),
                'adminEmailJson' => json_encode((string)($this->adminInfo['email'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            ]);
            return $this->render('set/email_notification');
        }

        $action = trim((string)$this->request->post('action', ''));
        try {
            if ($action === 'save_settings') {
                EmailNotificationService::saveGlobalEnabled(intval($this->request->post('global_enabled', 0)) === 1);
                $enabled = $this->request->post('events/a', []);
                EmailNotificationService::savePreferences('admin', intval($this->adminId), is_array($enabled) ? $enabled : []);
                return json(message(t('notification_email.settings_saved'), true));
            }
            if ($action === 'template_list') {
                $rows = EmailNotificationService::templates();
                return json(['code' => 0, 'msg' => '', 'count' => count($rows), 'data' => $rows]);
            }
            if ($action === 'template_get') {
                $eventCode = trim((string)$this->request->post('event_code', ''));
                $template = EmailNotificationService::template($eventCode);
                return json(message('', true, $template));
            }
            if ($action === 'template_save') {
                EmailNotificationService::saveTemplate(
                    trim((string)$this->request->post('event_code', '')),
                    (string)$this->request->post('subject', ''),
                    (string)$this->request->post('html_body', ''),
                    intval($this->request->post('enabled', 0)) === 1,
                    intval($this->adminId)
                );
                return json(message(t('notification_email.template_saved'), true));
            }
            if ($action === 'template_reset') {
                EmailNotificationService::resetTemplate(trim((string)$this->request->post('event_code', '')));
                return json(message(t('notification_email.template_reset_success'), true));
            }
            if ($action === 'preview') {
                $preview = EmailNotificationService::preview(
                    trim((string)$this->request->post('event_code', '')),
                    (string)$this->request->post('subject', ''),
                    (string)$this->request->post('html_body', '')
                );
                return json(message('', true, $preview));
            }
            if ($action === 'test') {
                $result = EmailNotificationService::sendTest(
                    trim((string)$this->request->post('event_code', '')),
                    trim((string)$this->request->post('email', '')),
                    (string)($this->adminInfo['username'] ?? '')
                );
                if (empty($result['success'])) {
                    return json(message(t('notification_email.test_failed'), false));
                }
                return json(message(t('notification_email.test_sent'), true));
            }
            if ($action === 'transport_test') {
                $result = EmailNotificationService::sendTransportTest(intval($this->adminId));
                if (empty($result['success'])) {
                    return json(message(t('notification_email.test_failed'), false));
                }
                return json(message(t('notification_email.transport.test_sent'), true));
            }
            return json(message(t('validation.param_error'), false));
        } catch (\InvalidArgumentException $e) {
            return json(message($e->getMessage(), false));
        } catch (\Throwable $e) {
            return json(message(t('notification_email.operation_failed'), false));
        }
    }
}
