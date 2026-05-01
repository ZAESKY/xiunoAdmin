<?php

namespace app\api\service;

use app\common\service\BaseService;
use app\api\validate\Auth;
use app\api\model\AuthModel;
use app\api\model\PirateModel;
use app\api\model\VersionModel;
use app\common\extend\Rsa;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Queue;

class AuthService extends BaseService
{

    public function __construct()
    {
        $this->model = new AuthModel();
        $this->pirateModel = new PirateModel();
        $this->versionModel = new VersionModel();
    }

    private static function joinQueue($type, $param, $post, $get){
        $taskType = $type;
        switch ($taskType) {
            case 'checkAuth':
                $jobHandlerClassName  = 'app\common\job\MultiTask@checkAuth';
                $jobDataArr = ['param' => $param, 'post' => $post, 'get' => $get];
                $jobQueueName = 'multiTaskJobQueue';
                break;
            case 'checkUpdate':
                $jobHandlerClassName  = 'app\common\job\MultiTask@checkUpdate';
                $jobDataArr = ['param' => $param, 'post' => $post, 'get' => $get];
                $jobQueueName = 'multiTaskJobQueue';
                break;
            default:
                break;
        }
        if(Cache::has('queue_check'.$param['auth_info'])){
            return json(message(t('login.in_queue'),true, ['queue' => 1]));
        }else{
            $isPushed = Queue::push($jobHandlerClassName, $jobDataArr, $jobQueueName);
            if ($isPushed !== false) {
                Cache::tag('SF_CheckAuth')->set('queue_check'.$param['auth_info'],json_encode(message(t('login.waiting'),true, ['queue' => 1])));
                return json(message(t('login.waiting_result'),true, ['queue' => 1]));
            }else{
                return json(message('push a new '.$taskType.' of MultiTask Job Failed!',false, ['queue' => 1]));
            }
        }
    }

    public function queueCheck(){
        $param = request()->param();
        if(Cache::has('queue_check'.$param['auth_info'])){
            $result = Cache::get('queue_check'.$param['auth_info']);
            $result = json_decode($result,true);
            if(!array_key_exists('queue',$result['data'])){
                Cache::delete('queue_check'.$param['auth_info']);
            }
            return json($result);
        }else{
            return json(message('null',true, ['queue' => 1]));
        }
    }

    public function checkAuth(){
        $param = request()->param();
        $appid = !empty($param['appid'])?intval($param['appid']):null;
        $api_key = !empty($param['api_key'])?$param['api_key']:null;
        $auth_info = !empty($param['auth_info'])?$param['auth_info']:null;
        if(empty($appid)){
            return json(message(t('auth.enter_qq_code'),false));
        }
        if(empty($api_key)){
            return json(message(t('app.api_key_error'),false));
        }
        // 队列
        if(conf('queue_query_switch') == 1){
            return self::joinQueue('checkAuth', $param, request()->post(), request()->get());
        }
        if(Cache::has('check_auth'.$auth_info)){
            return Cache::get('check_auth'.$auth_info);
        }
        return $this->checkAuthInfo($appid, $api_key);
    }

    private function checkAuthInfo($appid, $api_key, $param = null){
        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return json(message(t('app.not_exist'),false));
            }
            if($api_key != $appInfo['api_key']){
                return json(message(t('app.api_key_error'),false));
            }
        }catch (\Exception $e){
            return json(message(t('common.server_error').$e->getMessage() ,false));
        }
        if(empty($param)){
            $allInfo = $appInfo['check_auth_method'] == 0?request()->get():request()->post();
        }else{
            $allInfo = $param;
        }

        $allInfo['appid'] = $appid;
        $allInfo['api_key'] = $api_key;
        $auth_info = !empty($allInfo['auth_info'])?$allInfo['auth_info']:null;
        $param = !empty($allInfo['param'])?$allInfo['param']:null;
        $param = json_decode(base64_decode($param), true);
        $param['appid'] = $appid;
        $param['auth_info'] = $auth_info;
        $authcode = !empty($param['authcode'])?$param['authcode']:null;
        try {
            validate(Auth::class)->check($allInfo);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return json(message($e->getError() ,false));
        }
        $authInfo = $this->model->getInfo([['auth_info', '=', $auth_info],['appid', '=', $appid]]);

        /*
         * 盗版入库
         */
        if($appInfo['pirate_switch'] != 0){
            if($appInfo['pirate_switch'] == 2){
                $this->pirateModel->edit($param);
            }else{
                if($authInfo == false || $authInfo['status'] != 1 || ($appInfo['authcode_switch'] == 1 && $authInfo['authcode'] != $authcode) || ($appInfo['ip_switch'] == 1 && !empty($authInfo['ip']) && !in_array(gethostbyname($authInfo['auth_info']),explode('|',$authInfo['ip'])) || ($authInfo['permanent_switch'] == 0 && strtotime($authInfo['endtime']) <= time()))){
                    $this->pirateModel->edit($param);
                }
            }
        }
        /*
         * 验证授权
         */
        if($appInfo['pirate_msg_switch'] == 1) {
            if ($authInfo == false) {
                $data = [
                    'data' => [
                        'code' => -1,
                        'msg' => $appInfo['pirate_msg'],
                        'time' => time()
                    ]
                ];
                $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
                return json(message($appInfo['pirate_msg'], false, $data));
            }
            /*
             * 验证授权状态
             */
            if($authInfo['status'] != 1){
                $data = [
                    'data' => [
                        'code' => -1,
                        'msg' => $appInfo['status_msg'],
                        'time' =>time()
                    ]
                ];
                $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
                return json(message($appInfo['status_msg'] ,false, $data));
            }

            /*
             * 验证授权码
             */
            if($appInfo['authcode_switch'] == 1){
                if(empty($authcode)){
                    $data = [
                        'data' => [
                            'code' => -1,
                            'msg' => $appInfo['authcode_msg'],
                            'time' => time()
                        ]
                    ];
                    $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
                    return json(message($appInfo['authcode_msg'] ,false, $data));
                }
                if($authInfo['authcode'] != $authcode){
                    $data = [
                        'data' => [
                            'code' => -1,
                            'msg' => $appInfo['authcode_msg'],
                            'time' => time()
                        ]
                    ];
                    $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
                    return json(message($appInfo['authcode_msg'] ,false, $data));
                }
            }
            /*
             * 验证IP
             */
            if($appInfo['ip_switch'] == 1){
                if(!empty($authInfo['ip'])){
                    if(!in_array(gethostbyname($authInfo['auth_info']),explode('|',$authInfo['ip']))){
                        $data = [
                            'data' => [
                                'code' => -1,
                                'msg' => $appInfo['ip_msg'],
                                'time' => time()
                            ]
                        ];
                        $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
                        return json(message($appInfo['ip_msg'] ,false, $data));
                    }
                }
            }

            /*
             * 验证期限
             */
            if($authInfo['permanent_switch'] == 0){
                if($authInfo['endtime'] <= date('Y-m-d H:i:s')){
                    $data = [
                        'data' => [
                            'code' => -1,
                            'msg' => $appInfo['endtime_msg'],
                            'time' => time()
                        ]
                    ];
                    $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
                    return json(message($appInfo['endtime_msg'] ,false, $data));
                }
            }
            $data = [
                'data' => [
                    'code' => 0,
                    'time' => time()
                ]
            ];
            $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
            try{
                AuthModel::where('id', $authInfo['id'])
                    ->data(['checktime' => datetime()])
                    ->update();
            } catch (\Exception $e) {
                return json(message(t('auth.update_check_time_failed').$e->getMessage() ,false));
            }
            return json(message(t('auth.genuine') ,true, $data));
        } else {
            $data = [
                'data' => [
                    'code' => 1,
                    'time' => time()
                ]
            ];
            $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
            Cache::tag('SF_CheckAuth')->set('check_auth'.$auth_info,json_encode(message(t('auth.genuine') ,true, $data)),3600);
            return json(message(t('auth.genuine') ,true, $data));
        }
    }

    public function checkUpdate(){
        if(conf('queue_query_switch') == 1){
            if(!empty($result['data']['queue'])){
                return $this->queueCheckAuth();
            }else{
                $result = $this->queueCheckAuth()->getData();
                if($result['code'] != 0){
                    return json($result);
                }
            }
        }else{
            $result = $this->checkAuth()->getData();
            if($result['code'] != 0){
                return json($result);
            }
        }
        $param = request()->param();
        $appid = !empty($param['appid'])?intval($param['appid']):null;
        if(!$appid){
            return json(message(t('auth.enter_qq_code'),false));
        }
        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return json(message(t('app.not_exist'),false));
            }
        }catch (\Exception $e){
            return json(message(t('common.server_error') ,false));
        }
        $allInfo = $appInfo['check_auth_method'] == 0?request()->get():request()->post();
        $auth_info = !empty($allInfo['auth_info'])?$allInfo['auth_info']:null;
        $appid = !empty($allInfo['appid'])?intval($allInfo['appid']):null;
        $param = !empty($allInfo['param'])?$allInfo['param']:null;
        $param = json_decode(base64_decode($param), true);
        $version = !empty($param['version'])?$param['version']:null;
        $beta = !empty($param['beta'])?intval($param['beta']):0;
        $authInfo = $this->model->getInfo([['auth_info', '=', $auth_info],['appid', '=', $appid]]);
        if(empty($version)){
            $data = [
                'data' => [
                    'code' => -1,
                    'time' => time()
                ]
            ];
            $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
            return json(message(t('version.version_empty') ,false, $data));
        }
        $betaFilter = ($authInfo['beta'] == 1) ? [0, 1] : [$beta];
        $versionData = $this->versionModel->getAppUpdateVersionList($appid,$version,$betaFilter);
        if($versionData['count'] == 0){
            $data = [
                'data' => [
                    'code' => 0,
                    'time' => time()
                ]
            ];
            $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
            return json(message(t('version.is_latest') ,true, $data));
        }
        if($beta == 1 && $authInfo['beta'] != 1){
            $data = [
                'data' => [
                    'code' => -1,
                    'msg' => t('version.no_beta_access'),
                    'time' => time()
                ]
            ];
            $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
            return json(message(t('version.no_beta_access') ,false, $data));
        }
        try{
            $cache = cache($auth_info.'-'.$appid.'-'.$version.'-'.$beta);
            if(!empty($cache)){
                $data = json_decode($cache,true);
                $allVersion = $data['data']['data'];
            }else{
                $allVersion = [
                    'count' => $versionData['count'],
                    'edition' => '1.0',
                    'version' => $version,
                    'update_log' => [],
                    'download' => [],
                    'filesize' => 0,
                    'introduce' => '',
                    'url' => 'http://'.DOMAIN.'/api.php/Download/download/?sign='
                ];
                foreach($versionData['list'] as $res){
                    $value = serialize([
                        'versionInfo' => $res,
                        'authInfo' => $authInfo
                    ]);
                    $key = md5(uniqid());
                    cache($key, $value, 43200);
                    if($allVersion['version'] < $res['version']){
                        $allVersion['version'] = $res['version'];
                        $allVersion['edition'] = $res['edition'];
                    }
                    $allVersion['filesize'] += round(filesize(APP_PATH . DS . 'common' . DS . 'download' . DS . 'update' . DS . $res['download_catalogue'] . DS . 'SF.zip') / 1048576 * 100) / 100;
                    $allVersion['download'][] = $key;
                    $allVersion['introduce'] .= $res['update_log'].',';
                    $update_log = [$res['addtime'] => explode("\n", $res['update_log'])];
                    $allVersion['update_log'] = array_merge($update_log??[] ,$allVersion['update_log']);
                }
                $allVersion['introduce'] = rtrim($allVersion['introduce'], ',');
                $data = [
                    'data' => [
                        'code' => 1,
                        'data' =>$allVersion,
                        'time' => time()
                    ]
                ];
                $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
                cache($auth_info.'-'.$appid.'-'.$version.'-'.$beta, json_encode($data), 600);
            }
            return json(message(t('version.latest_version').$allVersion['edition'] ,true , $data));
        }catch (\Exception $e){
            return json(message(t('common.server_error').$e->getMessage() ,false));
        }
    }
}


