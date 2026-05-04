<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\SetService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use think\facade\Cache;
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
                $configList = [];
                foreach ($this->service->all() as $v) {
                    if($v['type'] == 'bool'){
                        $value = isset($row[$v['name']]) ? intval($row[$v['name']]) : 0;
                        $v['value'] = $value;
                        $configList[] = $v->toArray();
                    }else{
                        if (isset($row[$v['name']])) {
                            $value = $row[$v['name']];
                            if (is_array($value) && isset($value['field'])) {
                                $value = json_encode(getArrayData($value), JSON_UNESCAPED_UNICODE);
                            } else {
                                $value = is_array($value) ? implode(',', array_values(array_filter($value))) : $value;
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
            'verify'          => false,
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
                return message(t('system.update_check_failed').$e->getMessage(), false);
            }
        }
    }

    public function updateVersion(){
        if(IS_POST){
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
                    return message(t('system.update_failed').$e->getMessage(), false, ['code' => -1]);
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
            if (empty($title)) return json(message('标题不能为空', false));
            if (empty($image)) return json(message('请上传图片', false));
            if ($id) {
                \think\facade\Db::name('carousel')->where('id', $id)->data([
                    'title' => $title, 'image' => $image, 'url' => $url,
                    'sort' => $sort, 'updated_at' => datetime(),
                ])->update();
                return json(message('修改成功', true));
            } else {
                \think\facade\Db::name('carousel')->insert([
                    'title' => $title, 'image' => $image, 'url' => $url,
                    'sort' => $sort, 'status' => 1,
                    'created_at' => datetime(), 'updated_at' => datetime(),
                ]);
                return json(message('添加成功', true));
            }
        }
        if ($action === 'drop') {
            $id = intval($post['id'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('carousel')->where('id', $id)->delete();
                return json(message('删除成功', true));
            }
            return json(message('ID无效', false));
        }
        if ($action === 'setStatus') {
            $id = intval($post['id'] ?? 0);
            $status = intval($post['status'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('carousel')->where('id', $id)->data(['status' => $status])->update();
                return json(message('操作成功', true));
            }
            return json(message('ID无效', false));
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
            if (empty($title)) return json(message('标题不能为空', false));
            if (empty($content)) return json(message('内容不能为空', false));
            if ($id) {
                \think\facade\Db::name('user_notice')->where('id', $id)->data([
                    'title' => $title, 'content' => $content,
                    'sort' => $sort, 'updated_at' => datetime(),
                ])->update();
                return json(message('修改成功', true));
            } else {
                \think\facade\Db::name('user_notice')->insert([
                    'title' => $title, 'content' => $content,
                    'sort' => $sort, 'status' => 1,
                    'created_at' => datetime(), 'updated_at' => datetime(),
                ]);
                return json(message('添加成功', true));
            }
        }
        if ($action === 'drop') {
            $id = intval($post['id'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('user_notice')->where('id', $id)->delete();
                return json(message('删除成功', true));
            }
            return json(message('ID无效', false));
        }
        if ($action === 'setStatus') {
            $id = intval($post['id'] ?? 0);
            $status = intval($post['status'] ?? 0);
            if ($id > 0) {
                \think\facade\Db::name('user_notice')->where('id', $id)->data(['status' => $status])->update();
                return json(message('操作成功', true));
            }
            return json(message('ID无效', false));
        }

        $list = \think\facade\Db::name('user_notice')->order('sort', 'asc')->order('id', 'desc')->select()->toArray();
        return json([
            'code' => 0, 'msg' => '', 'count' => count($list), 'data' => $list,
        ]);
    }
}
