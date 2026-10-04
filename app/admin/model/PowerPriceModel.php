<?php

namespace app\admin\model;

use app\admin\validate\PowerPrice;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;

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
            $pid = intval($pid);
            $currentId = intval($sid);
            if ($pid <= 0 || $currentId <= 0 || $pid === $currentId) {
                return false;
            }

            $visited = [];
            while ($currentId > 0) {
                if (isset($visited[$currentId])) {
                    return false;
                }
                $visited[$currentId] = true;

                $row = self::where(['id' => $currentId, 'status' => 1])->find();
                if (!$row) {
                    return false;
                }
                $parentId = intval($row['parentid']);
                if ($parentId === $pid) {
                    return true;
                }
                $currentId = $parentId;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    public function getUpdatePowerList($power, $userPower){
        try{
            $row = $this->getInfo($userPower);
            if(!$row){
                throw new Exception(t('user.power_format_error'));
            }
            $row = $this->getInfo($power);
            if(!$row){
                throw new Exception(t('user.power_format_error'));
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
                throw new Exception(t('user.power_format_error'));
            }
            if($userRow['status'] == 0){
                throw new Exception(t('power.not_exist'));
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
                throw new Exception(t('user.power_format_error'));
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
        $money = !empty($post['money'])?$post['money']:'0.00';
        $addauth_power = !empty($post['addauth_power']) ? 1 : 0;
        $adduser_power = !empty($post['adduser_power']) ? 1 : 0;
        $addauth_discount = !empty($post['addauth_discount'])?intval($post['addauth_discount']):0;
        $adduser_discount = !empty($post['adduser_discount'])?intval($post['adduser_discount']):0;
        $rebate_enabled = !empty($post['rebate_enabled']) ? 1 : 0;
        $rebate_rate = isset($post['rebate_rate']) ? floatval($post['rebate_rate']) : 0.00;
        $discount_code_enabled = !empty($post['discount_code_enabled']) ? 1 : 0;
        $introduce = !empty($post['introduce'])?htmlentities($post['introduce']):null;
        $parentid = !empty($post['parentid'])?intval($post['parentid']):0;
        $status = !empty($post['status'])?1:0;

        try {
            validate(PowerPrice::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        try {
            $money = sf_money_format($money);
        } catch (\InvalidArgumentException $e) {
            return message('validation.amount_format', false);
        }
        try {
            $this->assertValidParent($id, $tid, $parentid);
        } catch (\Exception $e) {
            return message($e->getMessage(), false);
        }
        if(!empty($id)){
            $current = $this->getInfo($id);
            if (!$current) {
                return message(t('power.not_exist'), false);
            }
            if (intval($current['default_power']) === 1 && $status !== 1) {
                return message(t('power.default_cannot_disable'), false);
            }
            if (intval($current['default_power']) === 1 && intval($current['tid']) !== $tid) {
                return message(t('power.default_cannot_move'), false);
            }
            $data = [
                "tid" => $tid,
                "name" => $name,
                "money" => $money,
                "addauth_power" => $addauth_power,
                "adduser_power" => $adduser_power,
                "addauth_discount" => $addauth_discount,
                "adduser_discount" => $adduser_discount,
                "rebate_enabled" => $rebate_enabled,
                "rebate_rate" => $rebate_rate,
                "discount_code_enabled" => $discount_code_enabled,
                "introduce" => $introduce,
                "status" => $status,
                "parentid" => $parentid
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
            $data = [
                "tid" => $tid,
                "name" => $name,
                "money" => $money,
                "addauth_power" => $addauth_power,
                "addpay_power" => 0,
                "pirate_power" => 0,
                "adduser_power" => $adduser_power,
                "addauth_discount" => $addauth_discount,
                "adduser_discount" => $adduser_discount,
                "rebate_enabled" => $rebate_enabled,
                "rebate_rate" => $rebate_rate,
                "discount_code_enabled" => $discount_code_enabled,
                "introduce" => $introduce,
                "default_power" => 0,
                "status" => $status,
                "addtime" => datetime(),
                "parentid" => $parentid
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
                throw new Exception(t('power.not_exist'));
            }
            if (intval($row['default_power']) === 1) {
                throw new Exception(t('power.default_cannot_delete'));
            }
            if (self::where('parentid', intval($id))->find()) {
                throw new Exception(t('power.has_children'));
            }
            if (Db::name('user')->where('power', intval($id))->find()) {
                throw new Exception(t('power.in_use'));
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
                throw new Exception(t('power.not_exist'));
            }
            if ($status === 0 && intval($row['default_power']) === 1) {
                throw new Exception(t('power.default_cannot_disable'));
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
                throw new Exception(t('power.not_exist'));
            }
            $allowedColumns = ['addauth_power', 'adduser_power'];
            if (!in_array($type, $allowedColumns, true)) {
                throw new Exception(t('validation.invalid_field'));
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
                throw new Exception(t('validation.missing_id'));
            }
            $powerPriceInfo = $this->getInfo($id);
            if(!$powerPriceInfo){
                throw new Exception(t('power.not_exist'));
            }
            if ($default_power !== 1) {
                throw new Exception(t('power.default_required'));
            }
            if (intval($powerPriceInfo['status']) !== 1) {
                throw new Exception(t('power.default_must_enabled'));
            }
            Db::transaction(function () use ($id, $powerPriceInfo) {
                self::where('tid', intval($powerPriceInfo['tid']))
                    ->data(['default_power' => 0])
                    ->update();
                self::where('id', $id)
                    ->data(['default_power' => 1])
                    ->update();
            });
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getDefaultPower($tid = ''){
        try{
            if(empty($tid)){
                throw new Exception(t('validation.params_missing'));
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
                throw new Exception(t('power.get_info_failed'));
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getPowerList($tid = '', $excludeId = 0, $includeDisabled = false){
        try{
            if(empty($tid)){
                throw new Exception(t('validation.params_missing'));
            }
            $data = array();
            $query = self::field('id,name,money,status')->where('tid', $tid);
            if (!$includeDisabled) {
                $query->where('status', 1);
            }
            if (intval($excludeId) > 0) {
                $query->where('id', '<>', intval($excludeId));
            }
            $list = $query->select();
            foreach ($list as $res){
                $data[] = array(
                    'id' => $res['id'],
                    'name' => $res['name'],
                    'money' => $res['money'],
                    'status' => $res['status'],
                );
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
            $data = $this->buildSearchWhere('id|name');

            if(!empty($tid)){
                $data[] = ['tid', '=', $tid];
            }

            $list = self::order('id' ,'asc')->where($data)->select();
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    private function assertValidParent($id, $tid, $parentId): void
    {
        $id = intval($id);
        $tid = intval($tid);
        $parentId = intval($parentId);
        if ($parentId === 0) {
            return;
        }
        if ($id > 0 && $parentId === $id) {
            throw new Exception(t('power.parent_self'));
        }

        $visited = [];
        $currentId = $parentId;
        while ($currentId > 0) {
            if (isset($visited[$currentId])) {
                throw new Exception(t('power.parent_cycle'));
            }
            $visited[$currentId] = true;

            $row = self::where('id', $currentId)->find();
            if (!$row || intval($row['tid']) !== $tid) {
                throw new Exception(t('power.parent_invalid'));
            }
            if ($id > 0 && intval($row['id']) === $id) {
                throw new Exception(t('power.parent_cycle'));
            }
            $currentId = intval($row['parentid']);
        }
    }
}
