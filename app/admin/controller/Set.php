<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\service\SetService;
use app\common\extend\Template as GetTemplateList;
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
                        $value = isset($row[$v['name']])?1:0;
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
                return message('保存成功！' ,true);
            }
            return message('保存失败！' ,false);
        }
    }

    public function settingTemplate(){
        if(IS_POST){
            $post = $this->request->post();
            $type = !empty($post['type'])?$post['type']:null;
            if(empty($type)){
                return message('缺少TYPE参数！' ,false);
            }
            switch ($type){
                case 'home':
                case 'login':
                case 'maintain':
                case 'notice':
                    break;
                default:
                    return message('类型错误！' ,false);
            }
            $templates = config('self_template');
            $path = PUBLIC_PATH . DS . 'template' . DS . 'modules' . DS . $type . DS . $templates[$type]['name'] . DS;
            if (!is_file($path. 'config.ini')) {
                return message('模板配置文件不存在',false);
            }
            $config = include($path. 'config.ini');
            $params = $this->request->post('row/a', [], 'trim');
            if ($params) {
                foreach ($config as $k => &$v) {
                    if($v['type'] == 'bool'){
                        $value = isset($params[$v['name']])?'1':'0';
                        $v['value'] = $value;
                    }else {
                        if (isset($params[$v['name']])) {
                            if ($v['type'] == 'array') {
                                $params[$v['name']] = getArrayData($params[$v['name']]);
                                $value = $params[$v['name']];
                            } else {
                                $value = is_array($params[$v['name']]) ? implode(',', $params[$v['name']]) : $params[$v['name']];
                            }
                            $v['value'] = $value;
                        }
                    }
                }
                try {
                    //更新配置文件
                    if (!is_really_writable($path . 'config.ini')) {
                        return message('文件没有写入权限',false);
                    }
                    if ($handle = fopen($path . 'config.ini', 'w')) {
                        fwrite($handle, '<?php'.PHP_EOL.PHP_EOL.'return ' . VarExporter::export($config) . ';'.PHP_EOL);
                        fclose($handle);
                        Cache::tag('SF_Template')->clear();
                        return message('更新模板配置成功',true);
                    } else {
                        return message('文件没有写入权限',false);
                    }
                } catch (Exception $e) {
                    return message('更新配置信息失败！'.$e->getMessage(), false);
                }
            }
            return message('获取模板配置成功！', true, $config);
        }
    }

    public function editTemplate(){
        if(IS_POST){
            $post = $this->request->post();
            $type = !empty($post['type'])?$post['type']:null;
            $name = !empty($post['name'])?$post['name']:null;
            if(empty($type)){
                return message('缺少TYPE参数！' ,false);
            }
            if(empty($name)){
                return message('缺少NAME参数！' ,false);
            }
            switch ($type){
                case 'home':
                case 'login':
                case 'maintain':
                case 'notice':
                    break;
                default:
                    return message('类型错误！' ,false);
            }
            $templates = config('self_template');
            $path = PUBLIC_PATH . DS . 'template' . DS . 'modules' . DS . $type . DS . $name . DS;
            if (!is_file($path. 'info.ini')) {
                return message('模板配置文件不存在',false);
            }
            if (!is_file($path. 'config.ini')) {
                return message('模板配置文件不存在',false);
            }
            $config = include($path. 'config.ini');
            $templates[$type]['name'] = $name;
            $templates[$type]['view_base'] = '../public/template/modules/' . $type . '/' . $name . '/';
            $templates[$type]['config'] = $config;
            return $this->extracted($templates);
        }
    }

    public function previewTemplate(){
        if(IS_POST){
            $value = !empty($this->request->post('value'))?1:0;
            $templates = config('self_template');
            $templates['preview'] = $value;
            return $this->extracted($templates);
        }
    }

    public function deleteTemplate(){
        if(IS_POST) {
            $post = $this->request->post();
            $type = !empty($post['type']) ? $post['type'] : null;
            $name = !empty($post['name']) ? $post['name'] : null;
            if(empty($type)){
                return message('缺少TYPE参数！' ,false);
            }
            if(empty($name)){
                return message('缺少NAME参数！' ,false);
            }
            if($name == 'default'){
                return message('默认模板无法删除！' ,false);
            }
            //获取模板信息
            switch ($type){
                case 'home':
                case 'login':
                case 'maintain':
                case 'notice':
                    break;
                default:
                    return message('类型错误！' ,false);
            }
            $path = PUBLIC_PATH . DS . 'template' . DS . 'modules' . DS . $type . DS . $name . DS;
            if (!is_dir($path)) {
                return message('模板目录不存在！' ,false);
            }
            //判断模板是否使用中，如果使用中不给删除
            if(config('self_template.' . $type . '.name') == $name){
                return message('使用中的模板不能删除！' ,false);
            }
            //删除模板
            rmdirs($path);
            //删除资源文件
            $assets = PUBLIC_PATH . DS . 'template' . DS . 'assets' . DS . 'home' . DS . $name . DS;
            if (!is_dir($assets)) {
                rmdirs($assets);
            }
            return message('删除成功！' ,true);
        }
    }

    public function package()
    {
        if(IS_POST) {
            $post = $this->request->post();
            $type = !empty($post['type']) ? $post['type'] : null;
            $name = !empty($post['name']) ? $post['name'] : null;
            if (empty($type)) {
                return message('缺少TYPE参数！', false);
            }
            if (empty($name)) {
                return message('缺少NAME参数！', false);
            }
            //获取模板信息
            switch ($type){
                case 'home':
                case 'login':
                case 'maintain':
                case 'notice':
                    break;
                default:
                    return message('类型错误！' ,false);
            }
            $templates_relativePath = str_replace(DS, '/', 'public' . DS . 'template' . DS . 'modules' . DS . $type . DS . $name . DS);
            $assets_relativePath = str_replace(DS, '/', 'public' . DS . 'template' . DS . 'assets' . DS . $type . DS . $name . DS);
            $path = ROOT_PATH . $templates_relativePath . 'info.ini';
            if (!is_file($path)) {
                return message('模板配置文件不存在',false);
            }
            $config_info = parse_ini_file($path, true, INI_SCANNER_TYPED) ?: [];//获取模板配置信息
            //移动项目更新的文件end
            $tmp_dir = RUNTIME_PATH . DS . 'template' . DS . $type . DS;
            if (!is_dir($tmp_dir)) {
                @mkdir($tmp_dir, 0755, true);
            }
            $templates_name = 'SF-' . $type . '-' . $name . '-' . $config_info['version'] . '.zip';
            $templates_file = $tmp_dir . $templates_name;
            if (!class_exists('ZipArchive')) {
                return message('ZinArchive 没有安装',false);
            }

            $template_dir = ROOT_PATH . $templates_relativePath;
            $template_assets_dir = ROOT_PATH . $assets_relativePath;

            $zip = new \ZipArchive;
            $zip->open($templates_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            try {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($template_dir), \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $filename => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = str_replace(DS, '/', substr($filePath, strlen($template_dir)));
                        $zip->addFile($filePath, $templates_relativePath . $relativePath);
                    }
                }
                unset($files);
            } catch (\Exception $e) {
                return message('打包模板出错'.$e->getMessage(),false);
            }
            //打包资源
            try {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($template_assets_dir), \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = str_replace(DS, '/', substr($filePath, strlen($template_assets_dir)));
                        $zip->addFile($filePath, $assets_relativePath . $relativePath);
                    }
                }
            } catch (\Exception $e) {
                return message('打包模板出错'.$e->getMessage(),false);
            }

            $zip->close();
            return message('打包成功',true, $templates_name);
        }

    }

    public function defaultTemplate(){
        if(IS_POST) {
            $post = $this->request->post();
            $type = !empty($post['type']) ? $post['type'] : null;
            if (empty($type)) {
                return message('缺少TYPE参数！', false);
            }
            //获取模板信息
            switch ($type) {
                case 'home':
                case 'login':
                case 'maintain':
                case 'notice':
                    break;
                default:
                    return message('类型错误！', false);
            }
            $templates = config('self_template');
            $templates[$type]['name'] = 'default';
            $templates[$type]['view_base'] = '../public/template/modules/' . $type . '/default/';
            return $this->extracted($templates);
        }
    }

    public function download($name = null, $type = null){
        if(empty($name)){
            return $this->render('/public/error', ['msg' => '压缩包名不能为空！']);
        }
        if(empty($type)){
            return $this->render('/public/error', ['msg' => '类型不能为空！']);
        }
        $templates_file = RUNTIME_PATH . DS . 'template' . DS . $type . DS . $name;
        if(!is_file($templates_file)){
            return $this->render('/public/error', ['msg' => '不存在此压缩包！']);
        }
        return download($templates_file, $name);
    }

    public function template(){
        View::assign([
            'home_template_list' => GetTemplateList::getList('home'),
            'login_template_list' => GetTemplateList::getList('login'),
            'maintain_template_list' => GetTemplateList::getList('maintain'),
            'notice_template_list' => GetTemplateList::getList('notice'),
            'preview' => config('self_template.preview')
        ]);
        return $this->render();
    }

    /**
     * @param $templates
     * @return array
     */
    private function extracted($templates): array
    {
        $file = ROOT_PATH . DS . 'config' . DS . 'self_template.php';
        if (!file_exists($file)) {
            file_put_contents($file, '');
        }
        if (!is_really_writable($file)) {
            return message('文件没有写入权限', false);
        }
        if ($handle = fopen($file, 'w')) {
            fwrite($handle, "<?php\n\n" . "return " . var_export($templates, TRUE) . ";\n");
            fclose($handle);
        } else {
            return message('文件没有写入权限', false);
        }
        return message('操作成功！', true);
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
                        return message('链接更新服务器失败！', false);
                    }
                    //如果传回的是一个下载链接,则再次下载
                }
            } catch (TransferException $e) {
                return message('链接更新服务器失败！'.$e->getMessage(), false);
            }
        }
    }

    public function updateVersion(){
        if(IS_POST){
            $json = session('updateSession');
            if(empty($json)){
                return message('请刷新页面重试！', false, ['code' => -1]);
            }
            $json = json_decode($json, true);
            if(!is_array($json)){
                return message('请刷新页面重试！', false, ['code' => -1]);
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
                    return message('软件更新失败！'.$e->getMessage(), false, ['code' => -1]);
                }
                file_put_contents($tempCatalogue . $res, $content);
                $zip = new ZipFile();
                try {
                    $zip->openFile($tempCatalogue . $res);
                } catch (ZipException $e) {
                    return message('打开压缩文件'.$res.'失败！', false, ['code' => -1]);
                }
                try {
                    $zip->extractTo(ROOT_PATH);
                    self::importsql();
                } catch (ZipException $e) {
                    return message('解压压缩文件'.$res.'失败！', false, ['code' => -1]);
                }
                unset($json['data']['download'][$k]);
                Session::set('updateSession', json_encode($json));
                Session::save();
                return message('更新包'.$res.'解压成功', true, ['code' => 1, 'count' => $count]);
            }
            $file = ROOT_PATH . DS . 'config' . DS . 'sf.php';
            $config = config('sf');
            $config['version'] = $json['data']['version'];
            $config['edition'] = $json['data']['edition'];
            if (!file_exists($file)) {
                file_put_contents($file, '');
            }
            if (!is_really_writable($file)) {
                return message('软件版本MOS '.$config['edition'].'更新失败！', false, ['code' => -1]);
            }
            if ($handle = fopen($file, 'w')) {
                fwrite($handle, "<?php\n\n" . "return " . var_export($config, TRUE) . ";\n");
                fclose($handle);
            } else {
                return message('软件版本MOS '.$config['edition'].'更新失败！', false, ['code' => -1]);
            }
            session('updateSession',null);
            return message('软件版本MOS '.$config['edition'].'更新成功', true, ['code' => 2]);

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
}