<?php

namespace app\admin\model;

use app\admin\validate\User;
use app\common\model\BaseModel;
use app\common\model\BalanceLogModel;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;

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
        $qq = !empty($post['qq'])?intval($post['qq']):null;
        $email = !empty($post['email'])?$post['email']:'';
        $balance = !empty($post['balance'])?floatval($post['balance']):0;
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
        if(parent::getPowerPriceInfo($power) == false){
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
            $data = [
                'power' => $power,
                'username' => $username,
                'password' => !empty($password) ? get_password($password) : $row['password'],
                'qq' => $qq,
                'email' => $email,
                'balance' => $balance,
                'integral' => $integral,
                'ip' => $ip,
                'status' => $status,
                'appid' => $appid,
                'userid' => $userid
            ];
            $oldBalance = floatval($row['balance']);
            try{
                self::where('id', $id)
                    ->data($data)
                    ->update();
                if ($balance != $oldBalance) {
                    $diff = round($balance - $oldBalance, 2);
                    BalanceLogModel::add($id, 'admin_edit', $diff, '管理员修改余额 '.($diff >= 0 ? '+' : '').$diff.' 元');
                }
                Cache::delete('SF_UserMenu'.$id);
                return message(t('user.edit_success') ,true);
            } catch (\Exception $e) {
                return message(t('user.edit_failed').$e->getMessage() ,false);
            }
        }else{
            if (empty($password)) {
                return message('请填写密码', false);
            }
            $row = self::where(['username'=>$username, 'appid'=>$appid])->find();
            if($row){
                return message(t('app.username_exists') ,false);
            }
            $data = [
                'power' => $power,
                'username' => $username,
                'password' => get_password($password),
                'qq' => $qq,
                'email' => $email,
                'balance' => $balance,
                'integral' => $integral,
                'ip' => $ip,
                'addtime' => datetime(),
                'status' => $status,
                'appid' => $appid,
                'userid' => $userid
            ];
            try{
                $newId = self::insertGetId($data);
                if ($balance > 0) {
                    BalanceLogModel::add($newId, 'admin_edit', $balance, '新用户初始余额 +'.$balance.' 元');
                }
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
                throw new Exception(t('user.not_exist'));
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
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $appid = !empty($post['appid'])?$post['appid']:null;
            $power = isset($post['power']) && $post['power'] !== '' ? intval($post['power']) : null;
            $data = $this->buildSearchWhere('id|username|qq');
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
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
