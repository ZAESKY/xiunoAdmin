<?php

namespace app\admin\model;

use app\admin\validate\Auth;
use app\common\model\BaseModel;
use app\common\extend\CheckInfo;
use think\Exception;
use think\exception\ValidateException;

/**
 * 授权-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class AuthModel
 * @package app\admin\model
 */
class AuthModel extends BaseModel
{
    // 设置数据表名
    protected $name = 'auth';

    public function getInfo($id){
        try{
            $result = self::where('id', $id)->find();
            if($result){
                return $result;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function edit(){
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($post['appid'])?intval($post['appid']):null;
        $qq = !empty($post['qq'])?intval($post['qq']):null;
        $auth_info = !empty($post['auth_info'])?$post['auth_info']:null;
        $ip = !empty($post['ip'])?$post['ip']:'';
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $beta = !empty($post['beta'])?1:0;
        $status = !empty($post['status'])?1:0;
        $permanent_switch = 0;

        try {
            validate(Auth::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }

        if(!empty($id)) {
            $row = $this->getInfo($id);
            if(!$row){
                return message(t('auth.not_exist') ,false);
            }
            if($auth_info != $row['auth_info']){
                $row2 = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
                if ($row2) {
                    return message(t('auth.already_exist'), false);
                }
            }
            try{
                $appInfo = parent::getAppInfo($appid);
                if($appInfo == false){
                    return message(t('app.get_info_failed'),false);
                }
                $checkInfo = new CheckInfo();
                $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
                if($checkResult['code'] != 0){
                    return $checkResult;
                }
            } catch (\Exception $e) {
                return message(t('common.failed').$e->getMessage() ,false);
            }

            if($type != -1) {
                try {
                    $authPriceInfo = parent::getAuthPriceInfo($type);
                    if ($authPriceInfo == false) {
                        return message(t('auth.get_price_failed'), false);
                    }
                    if ($authPriceInfo['diy_switch'] == 1) {
                        if (empty($endtime)) {
                            return message(t('auth.enter_expire_time'), false);
                        }
                    } else {
                        if ($authPriceInfo['permanent_switch'] == 1) {
                            $endtime = $row['endtime'];
                            $permanent_switch = 1;
                        } else {
                            $endtime = date('Y-m-d H:i:s', strtotime($row['endtime'] . ' +' . $authPriceInfo['day'] . ' day'));
                        }
                    }
                } catch (\Exception $e) {
                    return message(t('auth.get_price_failed') . $e->getMessage(), false);
                }
            }else{
                $endtime = $row['endtime'];
                $permanent_switch = $row['permanent_switch'];
            }

            $data = [
                'auth_info' => $auth_info,
                'qq' => $qq,
                'ip' => $ip,
                'permanent_switch' => $permanent_switch,
                'beta' => $beta,
                'endtime' => $endtime,
                'status' => $status,
                'appid' => $appid,
            ];
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                return message(t('user.edit_success') ,true);
            } catch (\Exception $e) {
                return message(t('user.edit_failed').$e->getMessage() ,false);
            }
        }else{
            $row = self::where(['auth_info' => $auth_info, 'appid' => $appid])->find();
            if ($row) {
                return message(t('auth.already_exist'), false);
            }
            try{
                $appInfo = parent::getAppInfo($appid);
                if($appInfo == false){
                    return message(t('auth.get_check_type_failed'),false);
                }
                $checkInfo = new CheckInfo();
                $checkResult = $checkInfo->check($appInfo['check_type'], $auth_info);
                if($checkResult['code'] != 0){
                    return $checkResult;
                }
                $authTemplateInfo = intval($appInfo['auth_template']);
                if(empty($authTemplateInfo))return message(t('auth.get_template_info_failed'),false);
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if($authPriceInfo == false){
                    return message(t('auth.get_price_failed'),false);
                }
                $endtime = date('Y-m-d H:i:s',strtotime(' +'.$authPriceInfo['day'].' day'));
                $permanent_switch = !empty($post['permanent_switch'])?intval($post['permanent_switch']):0;
            } catch (\Exception $e) {
                return message(t('user.add_failed').$e->getMessage() ,false);
            }
            try{
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if($authPriceInfo == false){
                    return message(t('auth.get_price_failed'),false);
                }
                if($authPriceInfo['diy_switch'] == 1){
                    if(empty($endtime)){
                        return message(t('auth.enter_expire_time') ,false);
                    }
                }else{
                    if($authPriceInfo['permanent_switch'] == 1){
                        $endtime = datetime();
                        $permanent_switch = 1;
                    } else {
                        $endtime = date('Y-m-d H:i:s',strtotime(' +'.$authPriceInfo['day'].' day'));
                    }
                }
            } catch (\Exception $e) {
                return message(t('auth.get_price_failed').$e->getMessage() ,false);
            }

            $row = self::where('qq', $qq)->field('sign,authcode')->find();
            if(empty($row)){
                $row = self::order('sign','desc')->where('qq', $qq)->field('sign')->find();
                if(empty($row)){
                    $sign = 1;
                }else{
                    $sign = $row['sign'] + 1;
                }
                $authcode = md5(time().$qq.'SF');
            }else{
                $sign = $row['sign'];
                $authcode = $row['authcode'];
            }
            $data = [
                'auth_info' => $auth_info,
                'qq' => $qq,
                'ip' => $ip,
                'authcode' => $authcode,
                'sign' => $sign,
                'replace_number' => 0,
                'permanent_switch' => $permanent_switch,
                'addtime' => datetime(),
                'endtime' => $endtime,
                'beta' => $beta,
                'status' => $status,
                'appid' => $appid,
                'bindingid' => 0,
                'userid' => 0,
            ];

            try{
                self::insert($data);
                return message(t('user.add_success') ,true);
            } catch (\Exception $e) {
                return message(t('user.add_failed').$e->getMessage() ,false);
            }
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('auth.not_exist'));
            }
            self::where('id', $id)->delete();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setStatus(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $status = !empty($post['status'])?1:0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('auth.not_exist'));
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setPermanentSwitch(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $permanent_switch = !empty($post['permanent_switch'])?1:0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('auth.not_exist'));
            }

            self::where('id', $id)
                ->data(['permanent_switch' => $permanent_switch])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setBetaSwitch(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $beta = !empty($post['beta'])?1:0;

            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('auth.not_exist'));
            }

            self::where('id', $id)
                ->data(['beta' => $beta])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $appid = !empty($post['appid'])?intval($post['appid']):'';
            $data = $this->buildSearchWhere('id|auth_info|qq');

            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }

            $list = self::order('id' ,'desc')->where($data)->paginate([
                'list_rows'=> $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
