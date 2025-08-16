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
                return message("不存在此授权！" ,false);
            }
            $cdkeyInfo = json_decode($row['info'],true);
            if(empty($cdkey)){
                return message("请输入卡密内容！" ,false);
            }
            if($cdkey != $row['cdkey']){
                $result = self::where('cdkey', $cdkey)->find();
                if($result){
                    return message("系统已存在该卡密，请更换卡密内容！" ,false);
                }
            }
            switch ($cdkey_type){
                case 'auth':
                    if($permanent_switch == 0){
                        if(empty($type)){
                            return message("请选择授权时间！" ,false);
                        }else{
                            if($type == 'diy'){
                                if(empty($endtime)){
                                    return message("请输入到期时间！" ,false);
                                }
                            }else{
                                try{
                                    $authPriceInfo = parent::getAuthPriceInfo($type);
                                    if($authPriceInfo == false){
                                        return message("获取价格信息失败！",false);
                                    }
                                    $endtime = date("Y-m-d H:i:s",strtotime(' +'.$authPriceInfo['day'].' day'));
                                } catch (\Exception $e) {
                                    return message("获取价格信息失败！".$e->getMessage() ,false);
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
                        return message("该权限不存在！" ,false);
                    }
                    $info['power'] = $power;
                    $info['user_status'] = $user_status;
                    break;
                case 'balance':
                    if($balance <= 0){
                        return message("余额不能小于等于0！" ,false);
                    }
                    $info['balance'] = $balance;
                    break;
                case 'integral':
                    if($integral <= 0){
                        return message("积分不能小于等于0！" ,false);
                    }
                    $info['integral'] = $integral;
                    break;
                default:
                    return message("卡密类型错误！" ,false);
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
                return message("编辑成功！" ,true);
            } catch (\Exception $e) {
                return message("编辑失败！".$e->getMessage() ,false);
            }
        }else{
            if($number <= 0){
                return message("生成的数量不能小于等于0！" ,false);
            }
            switch ($cdkey_type){
                case 'auth':
                    if($permanent_switch == 0){
                        if(empty($type)){
                            return message("请选择授权时间！" ,false);
                        }else{
                            if($type == 'diy'){
                                if(empty($endtime)){
                                    return message("请输入到期时间！" ,false);
                                }
                            }else{
                                try{
                                    $authPriceInfo = parent::getAuthPriceInfo($type);
                                    if($authPriceInfo == false){
                                        return message("获取价格信息失败！",false);
                                    }
                                    $endtime = date("Y-m-d H:i:s",strtotime(' +'.$authPriceInfo['day'].' day'));
                                } catch (\Exception $e) {
                                    return message("获取价格信息失败！".$e->getMessage() ,false);
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
                        return message("该权限不存在！" ,false);
                    }
                    $info['power'] = $power;
                    $info['user_status'] = $user_status;
                    break;
                case 'balance':
                    if($balance <= 0){
                        return message("余额不能小于等于0！" ,false);
                    }
                    $info['balance'] = $balance;
                    break;
                case 'integral':
                    if($integral <= 0){
                        return message("积分不能小于等于0！" ,false);
                    }
                    $info['integral'] = $integral;
                    break;
                default:
                    return message("卡密类型错误！" ,false);
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
                    return message("成功生成 ".$i." 张卡密，失败！".$e->getMessage() ,false);
                }
            }
            return message("成功生成 ".$i." 张卡密！" ,true);
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此卡密！');
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
                throw new Exception('缺少ID参数！');
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此卡密！');
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
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']):null;

            $data = [];

            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }

            if($text !== null) {
                $data[] = ['id|cdkey', 'like', '%'.$text.'%'];
            }

            if($status !== null){
                $data[] = ['status', '=', $status];
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