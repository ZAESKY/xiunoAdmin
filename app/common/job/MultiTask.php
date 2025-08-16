<?php

namespace app\common\job;

use app\api\model\PirateModel;
use app\common\extend\Rsa;
use think\queue\Job;
use think\facade\Cache;
use think\facade\Db;

class MultiTask{
    public function checkAuth(Job $job,$data){
        $isJobDone = $this->_doCheckAuth($data);
        if ($isJobDone) {
            $job->delete();
            print("检测授权执行成功");
        }else{
            $job->release(3);
            print("任务3s后再次被执行");
        }
        if ($job->attempts() > 3) {
            $job->delete();
            print("Job has been retried more than 3 times!");
        }
    }

    public function taskB(Job $job,$data){
        $isJobDone = $this->_doTaskB($data);
        if ($isJobDone) {
            $job->delete();
            print("Info: TaskB of Job MultiTask has been done and deleted");
        }else{
            $job->release(3);
            print("任务3s后再次被执行");
        }
        if ($job->attempts() > 3) {
            $job->delete();
            print("Job has been retried more than 3 times!");
        }
    }

    private static function cache($key, $value){
        Cache::tag('SF_CheckAuth')->set('queue_check'.$key, $value);
        return true;
    }

    private function _doCheckAuth($data) {
        try{
            $param = $data['param'];
            $get = $data['get'];
            $post = $data['post'];
            $appid = !empty($param['appid'])?intval($param['appid']):null;
            $api_key = !empty($param['api_key'])?$param['api_key']:null;
            $param_auth_info = !empty($param['auth_info'])?$param['auth_info']:null;
            $appInfo = Db::name('app')
                ->where([['id','=',$appid]])
                ->field([
                    'check_auth_method',
                    'api_key',
                    'private_key',
                    'public_key',
                    'pirate_switch',
                    'pirate_msg_switch',
                    'authcode_switch',
                    'ip_switch',
                    'pirate_msg',
                    'authcode_msg',
                    'status_msg',
                    'ip_msg',
                    'endtime_msg'
                ])
                ->find();
            if($appInfo == false){
                return self::cache($param_auth_info, json_encode(message('不存在此应用！',false)));
            }
            if($api_key != $appInfo['api_key']){
                return self::cache($param_auth_info, json_encode(message('API_KEY错误！',false)));
            }
            $allInfo = $appInfo['check_auth_method'] == 0?$get:$post;
            $auth_info = !empty($allInfo['auth_info'])?$allInfo['auth_info']:null;

            $param = !empty($allInfo['param'])?$allInfo['param']:null;
            $param = json_decode(base64_decode($param), true);
            $authcode = !empty($param['authcode'])?$param['authcode']:null;
            if(empty($auth_info)){
                return self::cache($param_auth_info, json_encode(message('缺少授权信息参数！',false)));
            }
            $rsa = new Rsa($appInfo['private_key'],$appInfo['public_key']);
            $authInfo = Db::name('auth')
                ->where([
                    ['auth_info', '=', $auth_info],
                    ['appid', '=', $appid]
                ])
                ->field([
                    'status',
                    'authcode',
                    'ip',
                    'auth_info',
                    'permanent_switch',
                    'endtime',
                    'id'
                ])
                ->find();

            /*
             * 盗版入库
             */
            $this->pirateModel = new PirateModel();
            if($appInfo['pirate_switch'] != 0){
                if($appInfo['pirate_switch'] == 2){
                    $this->pirateModel->edit($param);
                }else{
                    if($authInfo == false || $authInfo['status'] != 1 || ($appInfo['authcode_switch'] == 1 && $authInfo['authcode'] != $authcode) || ($appInfo['ip_switch'] == 1 && !empty($authInfo['ip']) && !in_array(gethostbyname($authInfo['auth_info']),explode('|',$authInfo['ip'])) || ($authInfo['permanent_switch'] == 0 || $authInfo['endtime'] <= date('Y-m-d H:i:s')))){
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
                        'code' => -1,
                        'msg' => $appInfo['pirate_msg'],
                        'time' => $rsa::privateEncrypt((string)time())
                    ];
                    return self::cache($param_auth_info, json_encode(message($appInfo['pirate_msg'], false, $data)));
                }
                /*
                 * 验证授权状态
                 */
                if($authInfo['status'] != 1){
                    $data = [
                        'code' => -1,
                        'msg' => $appInfo['status_msg'],
                        'time' => $rsa::privateEncrypt((string)time())
                    ];
                    return self::cache($param_auth_info, json_encode(message($appInfo['status_msg'] ,false, $data)));
                }

                /*
                 * 验证授权码
                 */
                if($appInfo['authcode_switch'] == 1){
                    if(empty($authcode)){
                        $data = [
                            'code' => -1,
                            'msg' => $appInfo['authcode_msg'],
                            'time' => $rsa::privateEncrypt((string)time())
                        ];
                        return self::cache($param_auth_info, json_encode(message($appInfo['authcode_msg'] ,false, $data)));
                    }
                    if($authInfo['authcode'] != $authcode){
                        $data = [
                            'code' => -1,
                            'msg' => $appInfo['authcode_msg'],
                            'time' => $rsa::privateEncrypt((string)time())
                        ];
                        return self::cache($param_auth_info, json_encode(message($appInfo['authcode_msg'] ,false, $data)));
                    }
                }
                /*
                 * 验证IP
                 */
                if($appInfo['ip_switch'] == 1){
                    if(!empty($authInfo['ip'])){
                        if(!in_array(gethostbyname($authInfo['auth_info']),explode('|',$authInfo['ip']))){
                            $data = [
                                'code' => -1,
                                'msg' => $appInfo['ip_msg'],
                                'time' => $rsa::privateEncrypt((string)time())
                            ];
                            return self::cache($param_auth_info, json_encode(message($appInfo['ip_msg'] ,false, $data)));
                        }
                    }
                }

                /*
                 * 验证期限
                 */
                if($authInfo['permanent_switch'] == 0){
                    if($authInfo['endtime'] <= date('Y-m-d H:i:s')){
                        $data = [
                            'code' => -1,
                            'msg' => $appInfo['endtime_msg'],
                            'time' => $rsa::privateEncrypt((string)time())
                        ];
                        return self::cache($param_auth_info, json_encode(message($appInfo['endtime_msg'] ,false, $data)));
                    }
                }
                $data = [
                    'code' => 0,
                    'time' => $rsa::privateEncrypt((string)time())
                ];
                try{
                    Db::name('auth')
                        ->where('id', $authInfo['id'])
                        ->data(['checktime' => datetime()])
                        ->update();
                } catch (\Exception $e) {
                    return self::cache($param_auth_info, json_encode(message('更新检测授权时间失败！'.$e->getMessage() ,false)));
                }
                return self::cache($param_auth_info, json_encode(message('正版授权' ,true, $data)));
            } else {
                $data = [
                    'code' => 1,
                    'time' => $rsa::privateEncrypt((string)time())
                ];
                return self::cache($param_auth_info, json_encode(message('正版授权' ,true, $data)));
            }
        }catch (\Exception $e){
            return self::cache($param_auth_info, json_encode(message('服务器错误！'.$e->getMessage() ,false)));
        }
        return true;
    }

    private function _doTaskB($data) {
        print("Info: doing TaskB of Job MultiTask ");
        return true;
    }
}