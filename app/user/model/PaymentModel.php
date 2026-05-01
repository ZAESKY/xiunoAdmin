<?php
namespace app\user\model;

use app\user\validate\Payment;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;

/**
 * 易支付认证-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class PaymentModel
 * @package app\user\model
 */
class PaymentModel extends BaseModel
{
    // 设置数据表名
    protected $name = 'payment';

    public function getInfo($id)
    {
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return false;
            }
            $result = self::where(['id' => $id, 'userid' => $userInfo['id']])->find();
            if ($result) {
                $content = [
                    'Title' => '查找认证',
                    '操作' => '查找认证',
                    '认证ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $result;
            } else {
                $content = [
                    'Title' => '查找认证',
                    '操作' => '查找认证',
                    '认证ID' => $id,
                    'Result' => '[errorCode:GetPaymentInfoError]'
                ];
                event('ActionLog', $content);
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    public function editBinding(){
        $userInfo = parent::getUserInfo();
        if(!$userInfo){
            return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
        }
        $powerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message(t('user.power_info_error').'[errorCode:GetUserPowerInfoError]' ,false);
        }
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : null;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $qq = !empty($post['qq']) ? intval($post['qq']) : null;
        $url = !empty($post['url']) ? trim($post['url']) : null;
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $permanent_switch = 0;
        if(empty($id)) return message(t('validation.missing_id') ,false);
        if(empty($appid)) return message(t('validation.missing_appid') ,false);
        if(empty($qq)) return message(t('validation.missing_qq') ,false);
        if(empty($url)) return message(t('validation.missing_url') ,false);
        if(empty($type)) return message(t('validation.missing_type') ,false);
        $row = self::where(['id' => $id, 'bindingid' => $userInfo['id']])->find();
        if (!$row) {
            return message(t('payment.not_exist'), false);
        }

        if ($url != $row['url']) {
            $row2 = self::where(['url' => $url, 'appid' => $appid])->find();
            if ($row2) {
                return message(t('payment.already_exist'), false);
            }
        }

        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return message(t('app.get_info_failed').'[errorCode:GetUserAppInfoError]',false);
            }
            if($appInfo['replace_switch'] != 0){
                return message(t('app.replace_not_open'),false);
            }
        } catch (\Exception $e) {
            return message(t('app.get_info_failed').'[errorCode:GetUserAppInfoError]' ,false);
        }

        if($row['qq'] == $qq && $row['url'] == $url){
            $allmoney = 0;
            $replace_number = $row['replace_number'];
        }else{
            if($row['replace_number'] >= $appInfo['free_replace_number']){
                $allmoney = $appInfo['replace_money'];
            }else{
                $allmoney = 0;
            }
            $replace_number = $row['replace_number'] + 1;
        }
        if($type != -1) {
            try {
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if ($authPriceInfo == false) {
                    return message(t('payment.get_price_failed').'[errorCode:GetAuthPriceInfoError]', false);
                }
                if($row['permanent_switch'] == 1){
                    return message(t('payment.permanent_no_renew'), false);
                }
                if ($authPriceInfo['diy_switch'] == 1) {
                    if (empty($endtime)) {
                        return message(t('payment.enter_expire_time'), false);
                    }
                    $date = explode(' ', $endtime);
                    $time = explode(':', $date[1]);
                    $date2 = explode(' ', $row['endtime']);
                    $differDay = ceil((strtotime($date[0]) - strtotime($date2[0]))/3600/24);
                    if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                        $differDay++;
                    }
                    if($differDay <= 0){
                        return message(t('auth.correct_expire_time') ,false);
                    }else{
                        $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                        $allmoney += round(($price * $differDay) * floatval($powerPriceInfo['addpay_discount'] / 100), 2);
                    }
                } else {
                    $price = $authPriceInfo['money'];
                    $allmoney += round($price * floatval($powerPriceInfo['addpay_discount'] / 100) ,2);
                    if($authPriceInfo['permanent_switch'] == 1){
                        $endtime = $row['endtime'];
                        $permanent_switch = 1;
                    }else{
                        $endtime = date('Y-m-d H:i:s',strtotime($row['endtime'].' +'.$authPriceInfo['day'].' day'));
                    }
                }
            } catch (\Exception $e) {
                return message(t('payment.get_price_failed').'[errorCode:GetAuthPriceInfoError]', false);
            }
        }else{
            $endtime = $row['endtime'];
            $permanent_switch = $row['permanent_switch'];
        }
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
        $data = [
            'url' => $url,
            'qq' => $qq,
            'endtime' => $endtime,
            'replace_number' => $replace_number,
            'permanent_switch' => $permanent_switch
        ];
        try {
            self::where('id', $id)
                ->data($data)
                ->update();
            $content = [
                'Title' => '更换认证',
                '操作' => '更换认证',
                '认证ID' => $id,
                '花费' => '- '.$allmoney.' 元',
                '剩余余额' => $remainderBalance.' 元',
                'Result' => 'success'
            ];
            event('ActionLog', $content);
            return message(t('payment.change_deadline_success').'<br> '.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total').' <br> '.t('common_ui.balance_field').$remainderBalance.' '.t('order_ui.total'), true);
        } catch (\Exception $e) {
            $content = [
                'Title' => '编辑认证',
                '操作' => '编辑认证',
                '认证ID' => $id,
                '花费' => '- '.$allmoney.' 元',
                '剩余余额' => $remainderBalance.' 元',
                'Result' => '[errorCode:ReplacePaymentError]'
            ];
            event('ActionLog', $content);
            return message(t('replace.auth_failed').'[errorCode:ReplacePaymentError]', false);
        }
    }

    public function edit(){
        $userInfo = parent::getUserInfo();
        if(!$userInfo){
            return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
        }
        $powerPriceInfo = parent::getPowerPriceInfo($userInfo['power']);
        if(!$powerPriceInfo) {
            return message(t('user.power_info_error').'[errorCode:GetUserPowerInfoError]' ,false);
        }
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : null;
        $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
        $post['appid'] = $appid;
        $qq = !empty($post['qq']) ? intval($post['qq']) : null;
        $url = !empty($post['url']) ? trim($post['url']) : null;
        $type = !empty($post['type'])?$post['type']:null;
        $endtime = !empty($post['endtime'])?$post['endtime']:null;
        $status = !empty($post['status']) ? 1 : 0;
        $permanent_switch = 0;

        try {
            validate(Payment::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }

        if (!empty($id)) {
            $row = $this->getInfo($id);
            if (!$row) {
                return message(t('payment.not_exist'), false);
            }
            if ($url != $row['url']) {
                $row2 = self::where(['url' => $url, 'appid' => $appid])->find();
                if ($row2) {
                    return message(t('payment.already_exist'), false);
                }
            }
            if($type != -1) {
                try {
                    $authPriceInfo = parent::getAuthPriceInfo($type);
                    if ($authPriceInfo == false) {
                        return message(t('payment.get_price_failed').'[errorCode:GetAuthPriceInfoError]', false);
                    }
                    if($row['permanent_switch'] == 1){
                        return message(t('payment.permanent_no_renew'), false);
                    }
                    if ($authPriceInfo['diy_switch'] == 1) {
                        if (empty($endtime)) {
                            return message(t('payment.enter_expire_time'), false);
                        }
                        $date = explode(' ', $endtime);
                        $time = explode(':', $date[1]);
                        $date2 = explode(' ', $row['endtime']);
                        $differDay = ceil((strtotime($date[0]) - strtotime($date2[0]))/3600/24);
                        if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                            $differDay++;
                        }
                        if($differDay <= 0){
                            return message(t('auth.correct_expire_time') ,false);
                        }else{
                            $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                            $allmoney = round(($price * $differDay) * floatval($powerPriceInfo['addpay_discount'] / 100), 2);
                        }
                        if($allmoney > $userInfo['balance']){
                            return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total').'<br>'.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total') ,false);
                        }
                    } else {
                        $price = $authPriceInfo['money'];
                        $allmoney = round($price * floatval($powerPriceInfo['addpay_discount'] / 100) ,2);
                        if($allmoney > $userInfo['balance']){
                            return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total').'<br>'.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total'),false);
                        }
                        if($authPriceInfo['permanent_switch'] == 1){
                            $endtime = $row['endtime'];
                            $permanent_switch = 1;
                        }else{
                            $endtime = date('Y-m-d H:i:s',strtotime($row['endtime'].' +'.$authPriceInfo['day'].' day'));
                        }
                    }
                } catch (\Exception $e) {
                    return message(t('payment.get_price_failed').'[errorCode:GetAuthPriceInfoError]', false);
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
            }else{
                $remainderBalance = $userInfo['balance'];
                $allmoney = 0;
                $endtime = $row['endtime'];
                $permanent_switch = $row['permanent_switch'];
            }

            $data = [
                'url' => $url,
                'qq' => $qq,
                'status' => $status,
                'endtime' => $endtime,
                'permanent_switch' => $permanent_switch
            ];
            try {
                self::where('id', $id)
                    ->data($data)
                    ->update();
                $content = [
                    'Title' => '编辑认证',
                    '操作' => '编辑认证',
                    '认证ID' => $id,
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return message(t('user.edit_success').'<br> '.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total').' <br> '.t('common_ui.balance_field').$remainderBalance.' '.t('order_ui.total'), true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '编辑认证',
                    '操作' => '编辑认证',
                    '认证ID' => $id,
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => '[errorCode:EditPaymentError]'
                ];
                event('ActionLog', $content);
                return message(t('user.edit_failed').'[errorCode:EditPaymentError]', false);
            }
        } else {
            $row = self::where(['url' => $url, 'appid' => $appid])->find();
            if ($row) {
                return message(t('payment.already_exist'), false);
            }

            try{
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if($authPriceInfo == false){
                    return message(t('payment.get_price_failed').'[errorCode:GetAuthPriceInfoError]',false);
                }
                if($authPriceInfo['diy_switch'] == 1){
                    if(empty($endtime)){
                        return message(t('payment.enter_expire_time') ,false);
                    }
                    $date = explode(' ', $endtime);
                    $time = explode(':', $date[1]);
                    $differDay = ceil((strtotime($date[0]) - strtotime(date('Y-m-d')))/3600/24);
                    if($time[0] != '00' || $time[1] != '00' || $time[2] != '00'){
                        $differDay++;
                    }
                    if($differDay <= 0){
                        return message(t('auth.correct_expire_time') ,false);
                    }else{
                        $price = ceil(($authPriceInfo['money'] / $authPriceInfo['day']) * 100) / 100;
                        $allmoney = round(($price * $differDay) * floatval($powerPriceInfo['addpay_discount'] / 100), 2);
                    }
                    if($allmoney > $userInfo['balance']){
                        return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total').'<br>'.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total') ,false);
                    }
                }else{
                    $price = $authPriceInfo['money'];
                    $allmoney = round($price * floatval($powerPriceInfo['addpay_discount'] / 100) ,2);
                    if($allmoney > $userInfo['balance']){
                        return message(t('user.balance_insufficient').'<br> '.t('common_ui.balance_field').$userInfo['balance'].' '.t('order_ui.total').'<br>'.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total'),false);
                    }
                    if($authPriceInfo['permanent_switch'] == 1){
                        $endtime = datetime();
                        $permanent_switch = 1;
                    }else{
                        $endtime = date('Y-m-d H:i:s',strtotime(' +'.$authPriceInfo['day'].' day'));
                    }
                }
            } catch (\Exception $e) {
                return message(t('payment.get_price_failed').'[errorCode:GetAuthPriceInfoError]' ,false);
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

            $data = [
                'url' => $url,
                'qq' => $qq,
                'replace_number' => 0,
                'addtime' => datetime(),
                'endtime' => $endtime,
                'permanent_switch' => $permanent_switch,
                'status' => $status,
                'appid' => $appid,
                'userid' => $userInfo['id'],
            ];
            try {
                self::insert($data);
                $content = [
                    'Title' => '添加认证',
                    '操作' => '添加认证',
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return message(t('user.add_success').'<br> '.t('common_ui.epay_url_label').$allmoney.' '.t('order_ui.total').' <br> '.t('common_ui.balance_field').$remainderBalance.' '.t('order_ui.total') , true);
            } catch (\Exception $e) {
                $content = [
                    'Title' => '添加认证',
                    '操作' => '添加认证',
                    '花费' => '- '.$allmoney.' 元',
                    '剩余余额' => $remainderBalance.' 元',
                    'Result' => '[errorCode:AddPaymentError]'
                ];
                event('ActionLog', $content);
                return message(t('user.add_failed').'[errorCode:AddPaymentError]', false);
            }
        }
    }

    public function unbind($id){
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            try{
                $userInfo = parent::getUserInfo();
                if(!$userInfo){
                    return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
                }
            }catch (\Exception $e){
                throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
            }
            $row = self::where(['id' => $id, 'bindingid' => $userInfo['id']])->find();
            if(!$row){
                throw new Exception(t('payment.not_exist'));
            }
            self::where('id', $id)
                ->data([
                    'bindingid' => 0
                ])
                ->update();
            $content = [
                'Title' => '取绑认证',
                '操作' => '取绑认证',
                '认证ID' => $id,
                'Result' => 'success'
            ];
            event('ActionLog', $content);
            return true;
        } catch (\Exception $e) {
            $content = [
                'Title' => '取绑认证',
                '操作' => '取绑认证',
                '认证ID' => $id,
                'Result' => '[errorCode:UnbindPaymentError]'
            ];
            event('ActionLog', $content);
            throw new Exception(t('user.delete_failed').'[errorCode:UnbindPaymentError]');
        }
    }

    public function drop($id)
    {
        try{
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('payment.not_exist'));
            }
            try {
                self::where('id', $id)->delete();
                $content = [
                    'Title' => '删除认证',
                    '操作' => '删除认证',
                    '认证ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return true;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '删除认证',
                    '操作' => '删除认证',
                    '认证ID' => $id,
                    'Result' => '[errorCode:DeletePaymentError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.delete_failed').'[errorCode:DeletePaymentError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.delete_failed').'[errorCode:DeletePaymentError]');
        }
    }

    public function setStatus()
    {
        try{
            $post = request()->post();
            $id = !empty($post['id']) ? intval($post['id']) : null;
            $status = !empty($post['status']) ? 1 : 0;

            if (empty($id)) {
                throw new Exception(t('validation.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('payment.not_exist'));
            }
            try {
                self::where('id', $id)
                    ->data(['status' => $status])
                    ->update();
                $content = [
                    'Title' => '编辑认证',
                    '操作' => '修改认证状态',
                    '认证ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return true;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '编辑认证',
                    '操作' => '修改认证状态',
                    '认证ID' => $id,
                    'Result' => '[errorCode:EditPaymentStatusError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.status_change_success').'[errorCode:EditPaymentStatusError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.status_change_success').'[errorCode:EditPaymentStatusError]');
        }
    }

    public function myList(){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return message(t('user.info_error').'[errorCode:UserInfoError]' ,false);
            }
        }catch (\Exception $e){
            throw new Exception(t('user.info_error').'[errorCode:UserInfoError]');
        }
        try{
            $post = request()->post();
            $limit = !empty($post['limit'])?$post['limit']:10;
            $current_page = !empty($post['current_page'])?$post['current_page']:1;
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']):null;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $data = [];
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }else{
                throw new Exception(t('user.info_error').'[errorCode:UserAppIdEmpty]');
            }
            if($text !== null){
                $data[] = ['id|url|qq', 'like', '%'.$text.'%'];
            }
            if($status !== null){
                $data[] = ['status', '=', $status];
            }
            $data[] = ['bindingid', '=', $userInfo['id']];
            try{
                $list = self::order('id' ,'desc')->where($data)->paginate([
                    'list_rows'=> $limit,
                    'page' => $current_page,
                ]);
                $content = [
                    'Title' => '我的认证',
                    '操作' => '获取用户绑定的认证列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '我的认证',
                    '操作' => '获取用户绑定的认证列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetMyPaymentListError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.list_failed').'[errorCode:GetMyPaymentListError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.list_failed').'[errorCode:GetMyPaymentListError]');
        }
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
            $limit = !empty($post['limit']) ? $post['limit'] : 10;
            $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
            $text = isset($post['text']) ? $post['text'] : null;
            $status = isset($post['status']) ? intval($post['status']) : null;
            $appid = !empty($userInfo['appid'])?intval($userInfo['appid']):null;
            $data = [];
            if(!empty($appid)){
                $data[] = ['appid', '=', $appid];
            }else{
                throw new Exception(t('user.info_error').'[errorCode:UserAppIdEmpty]');
            }

            if($text !== null){
                $data[] = ['id|url|qq', 'like', '%'.$text.'%'];
            }
            if($status !== null){
                $data[] = ['status', '=', $status];
            }
            $data[] = ['userid', '=', $userInfo['id']];
            try {
                $list = self::order('id', 'desc')->where($data)->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page,
                ]);
                $content = [
                    'Title' => '认证列表',
                    '操作' => '获取认证列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '认证列表',
                    '操作' => '获取认证列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetPaymentListError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.list_failed').'[errorCode:GetPaymentListError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.list_failed').'[errorCode:GetPaymentListError]');
        }
    }
}
