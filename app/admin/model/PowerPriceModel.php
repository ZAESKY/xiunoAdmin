<?php

namespace app\admin\model;

use app\admin\validate\PowerPrice;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;

/**
 * 权限-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class PowerPriceModel
 * @package app\admin\model
 */
class PowerPriceModel extends BaseModel
{
    // 设置数据表名
    protected $name = "power_price";

    protected $data = [];

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

    public function isUserSubordinatePower($pid, $sid){
        try{
            if($pid == $sid){
                return false;
            }
            $row = self::where(['id' => $sid, 'status' => 1])->find();
            if(!$row){
                return false;
            }
            if($row['parentid'] == 0){
                return false;
            }
            if($row['parentid'] == $pid){
                return true;
            }
            return $this->isUserSubordinatePower($pid,$row['parentid']);
        }catch (\Exception $e){
            return false;
        }
    }

    public function getUpdatePowerList($power, $userPower){
        try{
            $row = $this->getInfo($userPower);
            if(!$row){
                throw new Exception('用户权限格式错误！[errorCode:UserPowerError]');
            }
            $row = $this->getInfo($power);
            if(!$row){
                throw new Exception('用户权限格式错误！[errorCode:UserPowerError]');
            }
            $row = self::where(['id' => $row['parentid'], 'status' => 1])->find();
            if($row){
                if($row['id'] == $userPower){
                    return $this->data;
                }
                array_push($this->data, array("id" => $row['id'], "name" => $row['name'], "money" => $row['money']));
                $this->getUpdatePowerList($row['id'], $userPower);
            }
            return $this->data;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getUserUpdatePowerList($userPower, $array = false, $parentid = null){
        try{
            $userRow = $this->getInfo($userPower);
            if(!$userRow){
                throw new Exception('用户权限格式错误！[errorCode:UserPowerError]');
            }
            if($userRow['status'] == 0){
                throw new Exception('不存在此权限！[errorCode:UserPowerStatus]');
            }
            if(empty($parentid)){
                $row = self::where(['id' => $userRow['parentid'], 'status' => 1])->find();
            }else{
                $parentRow = $this->getInfo($parentid);
                $row = self::where(['id' => $parentRow['parentid'], 'status' => 1])->find();
            }
            if($row){
                if($array){
                    array_push($this->data, $row['id']);
                }else{
                    $money = ($row['money'] - $userRow['money']) > 0 ? ($row['money'] - $userRow['money']) : 0;
                    array_push($this->data, ['id' => $row['id'], 'name' => $row['name'], 'money' => $money, 'introduce' => $row['introduce']]);
                }
                if($row['parentid'] == 0){
                    return $this->data;
                }
                $this->getUserUpdatePowerList($userPower, $array, $row['id']);
            }
            return $this->data;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getAddPowerList($pid){
        try{
            $row = $this->getInfo($pid);
            if(!$row){
                throw new Exception('您的权限格式错误，请联系站长处理！[errorCode:UserPowerError]');
            }
            $row = self::field('id,name,money')->where(['parentid' => $pid, 'status' => 1])->find();
            if($row){
                array_push($this->data, array('id' => $row['id'], 'name' => $row['name'], 'money' => $row['money']));
                $this->getAddPowerList($row['id']);
            }
            return $this->data;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function edit(){
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $tid = !empty($post['tid'])?intval($post['tid']):null;
        $name = !empty($post['name'])?$post['name']:null;
        $money = !empty($post['money'])?floatval($post['money']):'0.00';
        $addauth_discount = !empty($post['addauth_discount'])?intval($post['addauth_discount']):0;
        $addpay_discount = !empty($post['addpay_discount'])?intval($post['addpay_discount']):0;
        $pirate_discount = !empty($post['pirate_discount'])?intval($post['pirate_discount']):0;
        $adduser_discount = !empty($post['adduser_discount'])?intval($post['adduser_discount']):0;
        $introduce = !empty($post['introduce'])?htmlentities($post['introduce']):null;
        $default_power = !empty($post['default_power'])?intval($post['default_power']):0;
        $parentid = !empty($post['parentid'])?intval($post['parentid']):0;
        $status = !empty($post['status'])?1:0;

        try {
            validate(PowerPrice::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        if(!empty($id)){
            $data = [
                "tid" => $tid,
                "name" => $name,
                "money" => $money,
                "addauth_discount" => $addauth_discount,
                "addpay_discount" => $addpay_discount,
                "pirate_discount" => $pirate_discount,
                "adduser_discount" => $adduser_discount,
                "introduce" => $introduce,
                "default_power" => $default_power,
                "status" => $status,
                "parentid" => $parentid
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
            $data = [
                "tid" => $tid,
                "name" => $name,
                "money" => $money,
                "addauth_discount" => $addauth_discount,
                "addpay_discount" => $addpay_discount,
                "pirate_discount" => $pirate_discount,
                "adduser_discount" => $adduser_discount,
                "introduce" => $introduce,
                "default_power" => $default_power,
                "status" => $status,
                "addtime" => datetime(),
                "parentid" => $parentid
            ];
            try{
                self::insert($data);
                return message("添加成功！" ,true);
            } catch (\Exception $e) {
                return message("添加失败！".$e->getMessage() ,false);
            }
        }
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此权限！');
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
                throw new Exception('不存在此权限！');
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setPower($id, $type, $status){
        try{
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception('不存在此权限！');
            }
            self::where('id', $id)
                ->data([$type => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setDefaultPower(){
        try{
            $post = request()->post();
            $id = !empty($post['id'])?intval($post['id']):null;
            $default_power = !empty($post['default_power'])?1:0;

            if(empty($id)){
                throw new Exception('缺少ID参数！');
            }
            $powerPriceInfo = $this->getInfo($id);
            if(!$powerPriceInfo){
                throw new Exception('不存在此权限！');
            }
            $row = self::where(['tid' => $powerPriceInfo['tid'], 'default_power' => 1])->find();
            if($row){
                self::where('id', $row['id'])
                    ->data(['default_power' => 0])
                    ->update();
            }
            self::where('id', $id)
                ->data(['default_power' => $default_power])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getDefaultPower($tid = ''){
        try{
            if(empty($tid)){
                throw new Exception('缺少TID参数！');
            }
            $row = self::field('id')
                ->where([
                    'tid' => $tid,
                    'status' => 1,
                    'default_power' => 1
                ])
                ->find();
            if($row){
                return $row['id'];
            }else{
                throw new Exception('该应用未设置默认权限！');
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getPowerList($tid = ''){
        try{
            if(empty($tid)){
                throw new Exception('缺少TID参数！');
            }
            $data = array();
            $list = self::field('id,name,money')->where(['tid' => $tid, 'status' => 1])->select();
            foreach ($list as $res){
                $data[] = array('id' => $res['id'], 'name' => $res['name'], 'money' => $res['money']);
            }
            return $data;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $tid = !empty($post['tid'])?$post['tid']:null;
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']):null;
            $data = [];

            if(!empty($tid)){
                $data[] = ['tid', '=', $tid];
            }

            if($text !== null) {
                $data[] = ['id|name', 'like', '%'.$text.'%'];
            }

            if($status !== null){
                $data[] = ['status', '=', $status];
            }

            $list = self::order('id' ,'asc')->where($data)->select();
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}