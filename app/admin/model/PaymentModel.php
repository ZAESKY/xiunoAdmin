<?php
namespace app\admin\model;

use app\admin\validate\Payment;
use app\common\model\BaseModel;
use think\exception\ValidateException;

/**
 * 易支付认证-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class PaymentModel
 * @package app\admin\model
 */
class PaymentModel extends BaseModel
{
    // 设置数据表名
    protected $name = "payment";

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
        $id = !empty($post['id']) ? intval($post['id']) : null;
        $appid = !empty($post['appid']) ? intval($post['appid']) : null;
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
                        return message(t('payment.get_price_failed'), false);
                    }
                    if ($authPriceInfo['diy_switch'] == 1) {
                        if (empty($endtime)) {
                            return message(t('payment.enter_expire_time'), false);
                        }
                    } else {
                        if ($authPriceInfo['permanent_switch'] == 1) {
                            $endtime = $row['endtime'];
                            $permanent_switch = 1;
                        } else {
                            $endtime = date("Y-m-d H:i:s", strtotime($row['endtime'] . ' +' . $authPriceInfo['day'] . ' day'));
                        }
                    }
                } catch (\Exception $e) {
                    return message(t('payment.get_price_failed') . $e->getMessage(), false);
                }
            }else{
                $endtime = $row['endtime'];
                $permanent_switch = $row['permanent_switch'];
            }

            $data = [
                "url" => $url,
                "qq" => $qq,
                "endtime" => $endtime,
                "permanent_switch" => $permanent_switch,
                "status" => $status,
                "appid" => $appid,
            ];
            try {
                self::where('id', $id)
                    ->data($data)
                    ->update();
                return message(t('user.edit_success'), true);
            } catch (\Exception $e) {
                return message(t('user.edit_failed') . $e->getMessage(), false);
            }
        } else {
            $row = self::where(['url' => $url, 'appid' => $appid])->find();
            if ($row) {
                return message(t('payment.already_exist'), false);
            }

            try{
                $authPriceInfo = parent::getAuthPriceInfo($type);
                if($authPriceInfo == false){
                    return message(t('payment.get_price_failed'),false);
                }
                if($authPriceInfo['diy_switch'] == 1){
                    if(empty($endtime)){
                        return message(t('payment.enter_expire_time') ,false);
                    }
                }else{
                    if($authPriceInfo['permanent_switch'] == 1){
                        $endtime = datetime();
                        $permanent_switch = 1;
                    } else {
                        $endtime = date("Y-m-d H:i:s",strtotime(' +'.$authPriceInfo['day'].' day'));
                    }
                }
            } catch (\Exception $e) {
                return message(t('payment.get_price_failed').$e->getMessage() ,false);
            }

            $data = [
                "url" => $url,
                "qq" => $qq,
                "replace_number" => 0,
                "addtime" => datetime(),
                "endtime" => $endtime,
                "permanent_switch" => $permanent_switch,
                "status" => $status,
                "appid" => $appid,
                "userid" => 0,
            ];
            try {
                self::insert($data);
                return message(t('user.add_success'), true);
            } catch (\Exception $e) {
                return message(t('user.add_failed') . $e->getMessage(), false);
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
                throw new Exception(t('payment.not_exist'));
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
                throw new Exception(t('payment.not_exist'));
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
                return message(t('validation.missing_id') ,false);
            }

            $row = $this->getInfo($id);
            if(!$row){
                return message(t('payment.not_exist') ,false);
            }

            self::where('id', $id)
                ->data(['permanent_switch' => $permanent_switch])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = !empty($post['limit']) ? $post['limit'] : 10;
            $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
            $text = isset($post['text'])?$post['text']:null;
            $status = isset($post['status'])?intval($post['status']):null;

            $data = [];

            if($text !== null) {
                $data[] = ['id|url|qq', 'like', '%' . $text . '%'];
            }

            if($status !== null){
                $data[] = ['status', '=', $status];
            }

            $list = self::order('id', 'desc')->where($data)->paginate([
                'list_rows' => $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
