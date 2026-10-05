<?php
namespace app\admin\model;

use app\admin\validate\AuthPrice;
use app\common\model\BaseModel;
use think\Exception;
use think\exception\ValidateException;
/**
 * 价格-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class AuthPriceModel
 * @package app\admin\model
 */
class AuthPriceModel extends BaseModel
{
    // 设置数据表名
    protected $name = "auth_price";

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

    public function getAuthPriceList($tid){
        try{
            $list = self::order('sort' ,'asc')->where(['tid' => $tid, 'status' => 1])->select();
            return $list;
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
        $all_money = !empty($post['all_money'])?$post['all_money']:'0.00';
        $day = !empty($post['day'])?intval($post['day']):'0';
        $permanent_switch = !empty($post['permanent_switch'])?1:0;
        if($permanent_switch == 1){
            $diy_switch = 0;
        } else {
            $diy_switch = !empty($post['diy_switch'])?1:0;
        }
        $status = !empty($post['status'])?1:0;

        try {
            validate(AuthPrice::class)->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return message($e->getError() ,false);
        }
        try {
            $money = qh_money_format($money);
            $all_money = qh_money_format($all_money);
        } catch (\InvalidArgumentException $e) {
            return message('validation.amount_format', false);
        }
        if($permanent_switch != 1){
            if($day == '0'){
                return message(t('auth.correct_expire_time') ,false);
            }
        }
        if(!empty($id)){
            $data = [
                "tid" => $tid,
                "name" => $name,
                "money" => $money,
                "all_money" => $all_money,
                "day" => $day,
                "diy_switch" => $diy_switch,
                "permanent_switch" => $permanent_switch,
                "status" => $status,
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
            $result = self::order('sort' ,'desc')
                ->where('tid', $tid)
                ->find();
            $data = [
                "tid" => $tid,
                "name" => $name,
                "money" => $money,
                "all_money" => $all_money,
                "sort" => ($result['sort'] ?? 0) + 1,
                "day" => $day,
                "diy_switch" => $diy_switch,
                "permanent_switch" => $permanent_switch,
                "status" => $status,
                "addtime" => datetime(),
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
                throw new Exception(t('common.no_data'));
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
                throw new Exception(t('common.no_data'));
            }

            self::where('id', $id)
                ->data(['status' => $status])
                ->update();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function setSort(){
        try{
            $post = request()->post();
            $tid = !empty($post['tid'])?intval($post['tid']):null;
            $id = !empty($post['id'])?intval($post['id']):null;
            $type = !empty($post['type'])?intval($post['type']):null;
            if(empty($tid)){
                throw new Exception(t('validation.params_missing'));
            }
            if(empty($id)){
                throw new Exception(t('validation.missing_id'));
            }
            if(empty($type)){
                throw new Exception(t('validation.missing_type'));
            }
            $row = self::where('id', $id)->find();
            $sort = $row['sort'];
            switch ($type){
                case 1:
                    $result = self::order('sort' ,'desc')
                        ->where('tid', $tid)
                        ->where('sort', '<', $sort)
                        ->find();
                    self::where('id', $id)
                        ->data(['sort' => $result['sort']])
                        ->update();
                    self::where('id', $result['id'])
                        ->data(['sort' => $sort])
                        ->update();
                    return true;
                case 2:
                    $result = self::order('sort' ,'asc')
                        ->where('tid', $tid)
                        ->where('sort', '>', $sort)
                        ->find();
                    self::where('id', $id)
                        ->data(['sort' => $result['sort']])
                        ->update();
                    self::where('id', $result['id'])
                        ->data(['sort' => $sort])
                        ->update();
                    return true;
                case 3:
                    $result = self::order('sort' ,'desc')
                        ->find();
                    self::where('sort', '>', $sort)
                        ->where('tid', $tid)
                        ->dec('sort')
                        ->update();
                    self::where('id', $id)
                        ->data(['sort' => $result['sort']])
                        ->update();
                    return true;
                case 4:
                    $result = self::order('sort' ,'asc')
                        ->find();
                    self::where('sort', '<', $sort)
                        ->where('tid', $tid)
                        ->inc('sort')
                        ->update();
                    self::where('id', $id)
                        ->data(['sort' => $result['sort']])
                        ->update();
                    return true;
                default:
                    throw new Exception(t('common_ui.type_error'));
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $tid = !empty($post['tid'])?intval($post['tid']):null;
            $data = $this->buildSearchWhere('id|name');

            if(!empty($tid)){
                $data[] = ['tid', '=', $tid];
            }

            $list = self::order('sort' ,'asc')->where($data)->paginate([
                'list_rows'=> $limit,
                'page' => $current_page,
            ]);
            return $list;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
