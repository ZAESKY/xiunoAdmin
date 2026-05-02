<?php

namespace app\admin\model;

use app\admin\validate\Cdkey;
use app\common\model\BaseModel;
use think\exception\ValidateException;

/**
 * 卡密-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class CdkeyModel
 * @package app\admin\model
 */
class CdkeyModel extends BaseModel
{
    // 设置数据表名
    protected $name = "cdkey";

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
        $cdkey_type = !empty($post['cdkey_type'])?$post['cdkey_type']:null;
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $permanent_switch = !empty($post['permanent_switch'])?intval($post['permanent_switch']):0;
        $auth_status = !empty($post['auth_status'])?intval($post['auth_status']):0;
        $power = !empty($post['power'])?intval($post['power']):null;
        $user_status = !empty($post['user_status'])?intval($post['user_status']):0;
        $balance = !empty($post['balance'])?floatval($post['balance']):0.00;
        $integral = !empty($post['integral'])?intval($post['integral']):0;
        $number = !empty($post['number'])?intval($post['number']):0;
        $cdkey = !empty($post['cdkey'])?$post['cdkey']:null;

        try {
            validate(Cdkey::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        $info = array();

        if(!empty($id)) {
            $row = $this->getInfo($id);
            if(!$row){
                return message(t('auth.not_exist') ,false);
            }
            $cdkeyInfo = json_decode($row['info'],true);
            if(empty($cdkey)){
                return message(t('cdkey.enter_content') ,false);
            }
            if($cdkey != $row['cdkey']){
                $result = self::where('cdkey', $cdkey)->find();
                if($result){
                    return message(t('cdkey.already_exist') ,false);
                }
            }
            switch ($cdkey_type){
                case 'auth':
                    if($permanent_switch == 0){
                        if(empty($type)){
                            return message(t('cdkey.select_time') ,false);
                        }else{
                            if($type == 'diy'){
                                if(empty($endtime)){
                                    return message(t('cdkey.enter_expire_time') ,false);
                                }
                            }else{
                                try{
                                    $authPriceInfo = parent::getAuthPriceInfo($type);
                                    if($authPriceInfo == false){
                                        return message(t('cdkey.get_price_failed'),false);
                                    }
                                    $endtime = date("Y-m-d H:i:s",strtotime(' +'.$authPriceInfo['day'].' day'));
                                } catch (\Exception $e) {
                                    return message(t('cdkey.get_price_failed').$e->getMessage() ,false);
                                }
                            }
                        }
                    }else{
                        $endtime = $cdkeyInfo['endtime'];
                    }
                    $info['permanent_switch'] = $permanent_switch;
                    $info['type'] = $type;
                    $info['endtime'] = $endtime;
                    $info['auth_status'] = $auth_status;
                    break;
                case 'user':
                    if(parent::getPowerPriceInfo($power) == false){
                        return message(t('cdkey.power_not_exist') ,false);
                    }
                    $info['power'] = $power;
                    $info['user_status'] = $user_status;
                    break;
                case 'balance':
                    if($balance <= 0){
                        return message(t('cdkey.balance_positive') ,false);
                    }
                    $info['balance'] = $balance;
                    break;
                case 'integral':
                    if($integral <= 0){
                        return message(t('cdkey.integral_positive') ,false);
                    }
                    $info['integral'] = $integral;
                    break;
                default:
                    return message(t('cdkey.type_error') ,false);
                    break;
            }
            $data = [
                "cdkey" => $cdkey,
                "cdkey_type" => $cdkey_type,
                "info" => json_encode($info),
                "appid" => $appid,
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
            if($number <= 0){
                return message(t('cdkey.count_positive') ,false);
            }
            switch ($cdkey_type){
                case 'auth':
                    if($permanent_switch == 0){
                        if(empty($type)){
                            return message(t('cdkey.select_time') ,false);
                        }else{
                            if($type == 'diy'){
                                if(empty($endtime)){
                                    return message(t('cdkey.enter_expire_time') ,false);
                                }
                            }else{
                                try{
                                    $authPriceInfo = parent::getAuthPriceInfo($type);
                                    if($authPriceInfo == false){
                                        return message(t('cdkey.get_price_failed'),false);
                                    }
                                    $endtime = date("Y-m-d H:i:s",strtotime(' +'.$authPriceInfo['day'].' day'));
                                } catch (\Exception $e) {
                                    return message(t('cdkey.get_price_failed').$e->getMessage() ,false);
                                }
                            }
                        }
                    }else{
                        $endtime = datetime();
                    }
                    $info['permanent_switch'] = $permanent_switch;
                    $info['type'] = $type;
                    $info['endtime'] = $endtime;
                    $info['auth_status'] = $auth_status;
                    break;
                case 'user':
                    if(parent::getPowerPriceInfo($power) == false){
                        return message(t('cdkey.power_not_exist') ,false);
                    }
                    $info['power'] = $power;
                    $info['user_status'] = $user_status;
                    break;
                case 'balance':
                    if($balance <= 0){
                        return message(t('cdkey.balance_positive') ,false);
                    }
                    $info['balance'] = $balance;
                    break;
                case 'integral':
                    if($integral <= 0){
                        return message(t('cdkey.integral_positive') ,false);
                    }
                    $info['integral'] = $integral;
                    break;
                default:
                    return message(t('cdkey.type_error') ,false);
                    break;
            }
            for($i=0;$i<$number;$i++){
                $cdkey = (conf('cdkey_head')??'SF').'_'.get_random_str(20,5);
                $data = [
                    "cdkey" => $cdkey,
                    "cdkey_type" => $cdkey_type,
                    "info" => json_encode($info),
                    "addtime" => datetime(),
                    "status" => 0,
                    "appid" => $appid,
                    "userid" => 0,
                ];
                try{
                    self::insert($data);
                } catch (\Exception $e) {
                    return message(t('cdkey.generate_success', ['count' => $i]).t('common.failed').$e->getMessage() ,false);
                }
            }
            return message(t('cdkey.generate_success', ['count' => $i]) ,true);
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('cdkey.not_exist'));
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
                throw new Exception(t('cdkey.not_exist'));
            }

            self::where('id', $id)
                ->data(['status' => $status])
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
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $data = $this->buildSearchWhere('id|cdkey');

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
