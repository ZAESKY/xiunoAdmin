<?php

namespace app\admin\model;

use app\admin\validate\User;
use app\common\model\BaseModel;
use app\common\model\BalanceLogModel;
use app\common\service\PhoneVerificationService;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 用户-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class UserModel
 * @package app\admin\model
 */
class UserModel extends BaseModel
{
    protected $name = 'user';

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
        $username = !empty($post['username'])?$post['username']:null;
        $password = !empty($post['password'])?$post['password']:null;
        $power = !empty($post['power'])?$post['power']:null;
        $qq = !empty($post['qq']) ? trim($post['qq']) : null;
        $email = !empty($post['email'])?$post['email']:'';
        $phone = trim((string)($post['phone'] ?? ''));
        $phoneVerified = !empty($post['phone_verified']);
        $balance = !empty($post['balance']) ? qh_money_format($post['balance']) : '0.00';
        $integral = !empty($post['integral'])?intval($post['integral']):0;
        $ip = !empty($post['ip'])?serialize(explode('|',$post['ip'])):'';
        $status = !empty($post['status'])?1:0;
        $userid = !empty($post['userid'])?intval($post['userid']):0;

        try {
            $scene = ($post['form_mode'] ?? '') === 'edit' ? 'edit' : 'add';
            validate(User::class)->scene($scene)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        if ($phone !== '' && PhoneVerificationService::normalizePhone($phone) === '') {
            return message('phone.invalid_mainland_number', false);
        }
        if ($phoneVerified && $phone === '') {
            return message('phone.required_when_verified', false);
        }
        $phone = $phone === '' ? '' : PhoneVerificationService::normalizePhone($phone);
        if($this->getPowerPriceInfo($power) == false){
            return message(t('user.power_not_exist') ,false);
        }
        if(!empty($id)){
            $row = $this->getInfo($id);
            if(!$row){
                return message(t('user.not_exist') ,false);
            }
            if($username != $row['username']){
                $row2 = self::where(['username'=>$username, 'appid'=>$appid])->find();
                if($row2){
                    return message(t('app.username_exists') ,false);
                }
            }
            if (!empty($qq) && $qq != $row['qq']) {
                $rowQq = self::where('qq', $qq)->where('id', '<>', $id)->find();
                if ($rowQq) {
                    return message('user_action.qq_already_bound', false);
                }
            }
            $data = [
                'power' => $power,
                'username' => $username,
                'password' => !empty($password) ? qh_password_make($password) : $row['password'],
                'qq' => $qq,
                'email' => $email,
                'balance' => $balance,
                'integral' => $integral,
                'ip' => $ip,
                'status' => $status,
                'appid' => $appid,
                'userid' => $userid
            ];
            $oldBalance = qh_money_format($row['balance']);
            Db::startTrans();
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                $phoneResult = PhoneVerificationService::syncAdminPhone($id, $phone, $phoneVerified);
                if (!$phoneResult['ok']) {
                    Db::rollback();
                    return message($phoneResult['message'], false);
                }
                if ($balance != $oldBalance) {
                    $diff = qh_money_subtract($balance, $oldBalance);
                    BalanceLogModel::add($id, 'admin_edit', $diff, t('user_action.admin_balance_adjustment', [
                        'amount' => ($diff >= 0 ? '+' : '') . $diff,
                    ]));
                }
                Db::commit();
                \app\user\model\Menu::clearUserCache(intval($id));
                return message(t('user.edit_success') ,true);
            } catch (\Throwable $e) {
                Db::rollback();
                return message(t('user.edit_failed'), false);
            }
        }else{
            if (empty($password)) {
                return message('validation.password_required', false);
            }
            $row = self::where(['username'=>$username, 'appid'=>$appid])->find();
            if($row){
                return message(t('app.username_exists') ,false);
            }
            if (!empty($qq)) {
                $rowQq = self::where('qq', $qq)->find();
                if ($rowQq) {
                    return message('user_action.qq_already_bound', false);
                }
            }
            $data = [
                'power' => $power,
                'username' => $username,
                'password' => qh_password_make($password),
                'qq' => $qq,
                'phone' => '',
                'phone_verified_at' => null,
                'phone_verified_source' => '',
                'wechat_openid' => '',
                'email' => $email,
                'balance' => $balance,
                'integral' => $integral,
                'ip' => $ip,
                'addtime' => datetime(),
                'status' => $status,
                'appid' => $appid,
                'userid' => $userid
            ];
            Db::startTrans();
            try{
                $newId = self::insertGetId($data);
                $phoneResult = PhoneVerificationService::syncAdminPhone($newId, $phone, $phoneVerified);
                if (!$phoneResult['ok']) {
                    Db::rollback();
                    return message($phoneResult['message'], false);
                }
                if ($balance > 0) {
                    BalanceLogModel::add($newId, 'admin_edit', $balance, t('user_action.initial_balance', [
                        'amount' => $balance,
                    ]));
                }
                Db::commit();
                return message(t('user.add_success') ,true);
            } catch (\Throwable $e) {
                Db::rollback();
                return message(t('user.add_failed'), false);
            }
        }
    }

    protected function getPowerPriceInfo($id)
    {
        if (empty($id)) {
            return false;
        }
        return (new \app\admin\model\PowerPriceModel())->getInfo($id);
    }

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('user.not_exist'));
            }
            Db::startTrans();
            try {
                Db::name('user_phone_identity')->where('user_id', intval($id))->delete();
                self::where('id', $id)->delete();
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                throw new Exception(t('user.delete_failed'));
            }
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
                throw new Exception(t('user.not_exist'));
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function getAppUserList($appid){
        try{
            $list = self::where('appid', $appid)->select();
            return $list->toArray();
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $appid = !empty($post['appid'])?$post['appid']:null;
            $userid = isset($post['userid']) && $post['userid'] !== '' ? intval($post['userid']) : null;
            $power = isset($post['power']) && $post['power'] !== '' ? intval($post['power']) : null;
            $data = $this->buildSearchWhere('id|username|qq|phone');
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }
            if($userid !== null && $userid > 0){
                $data[] = ['userid', '=', $userid];
            }
            if($power !== null){
                $data[] = ['power', '=', $power];
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
