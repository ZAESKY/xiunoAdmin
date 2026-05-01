<?php
namespace app\user\model;
use app\common\model\BaseModel;
use think\Exception;

/**
 * 盗版-模型
 * @author 陌上花开
 * @since 2022/7/3
 * Class PirateModel
 * @package app\user\model
 */
class PirateModel extends BaseModel
{
    // 设置数据表
    protected $name = 'pirate';

    public function getInfo($id){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return false;
            }
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            if(empty($appid)) return false;
            $result = self::where(['id' => $id, 'appid' => $appid])->find();
            if($result){
                $content = [
                    'Title' => '查找盗版',
                    '操作' => '查找盗版',
                    '盗版ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $result;
            }else{
                $content = [
                    'Title' => '查找盗版',
                    '操作' => '查找盗版',
                    '盗版ID' => $id,
                    'Result' => '[errorCode:GetPirateInfoError]'
                ];
                event('ActionLog', $content);
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    public function edit(){
        $userInfo = parent::getUserInfo();
        if(!$userInfo){
            return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
        }
        $userPowerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$userPowerPriceInfo) {
            return message(t('user.power_info_error').'[errorCode:GetUserPowerInfoError]' ,false);
        }
        $userAppInfo = parent::getAppInfo($userInfo['appid']);
        if(!$userAppInfo) {
            return message(t('user.app_info_error').'[errorCode:GetUserAppInfoError]' ,false);
        }
        $post = request()->post();
        $id = !empty($post['id'])?intval($post['id']):null;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        if(empty($id)){
            return message(t('validation.missing_id') ,false);
        }
        if(empty($appid)){
            return message(t('user.app_info_error').'[errroCode:UserAppIdEmpty]' ,false);
        }
        $row = $this->getInfo($id);
        if(!$row){
            return message(t('pirate.not_exist') ,false);
        }
        $allmoney = round($userAppInfo['pirate_money'] * floatval($userPowerPriceInfo['pirate_discount'] / 100), 2);
        if($allmoney > $userInfo['balance']){
            return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total').'<br>'.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total') ,false);
        }

        $remainderBalance = $userInfo['balance'] - $allmoney;

        try{
            $result = parent::updateUserInfo(['balance' => $remainderBalance]);
            if(!$result){
                return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]' ,false);
            }
        } catch (\Exception $e) {
            return message(t('user.update_info_failed').'[errorCode:ReduceUserBalanceError]',false);
        }
        $content = [
            'Title' => '查看盗版',
            '操作' => '查看盗版信息',
            '花费' => '- '.$allmoney.' 元',
            '余额' => $remainderBalance.' 元',
            'Info' => json_encode($row),
            'Result' => 'success'
        ];
        event('ActionLog', $content);
        return message(t('pirate.get_info_success').'<br> '.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total').' <br> '.t('common_ui.balance_field').$remainderBalance.' '.t('order_ui.total') ,true, ["data" => $row]);

    }

    public function list(){
        try{
            try{
                $userInfo = parent::getUserInfo();
                if(!$userInfo){
                    throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
                }
            }catch (\Exception $e){
                throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
            }
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $text = isset($post['text'])?$post['text']:null;

            $data = [];
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }else{
                throw new Exception(t('user.info_error').'[errorCode:UserAppIdEmpty]');
            }
            if($text !== null){
                $data[] = ['id|pirate_info', 'like', '%'.$text.'%'];
            }
            try{
                $list = self::order('addtime' ,'desc')
                    ->where($data)
                    ->paginate([
                        'list_rows'=> $limit,
                        'page' => $current_page,
                    ]);
                $content = [
                    'Title' => '盗版列表',
                    '操作' => '获取盗版列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '盗版列表',
                    '操作' => '获取盗版列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetPirateListError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.list_failed').'[errorCode:GetPirateListError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.list_failed').'[errorCode:GetPirateListError]');
        }
    }
}
