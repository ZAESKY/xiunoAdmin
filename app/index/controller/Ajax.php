<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\index\service\AjaxService;
use app\index\validate\Register;
use think\exception\ValidateException;
use think\facade\Db;
class Ajax extends Frontend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new AjaxService();
    }

    public function appInfo(){
        if(IS_POST){
            return $this->service->appInfo();
        }
    }

    public function queryAuth(){
        if(IS_POST){
            return $this->service->queryAuth();
        }
    }

    public function pluginMarket(){
        if(IS_POST){
            $post = $this->request->post();
            $limit = !empty($post['limit']) ? intval($post['limit']) : 12;
            $current_page = !empty($post['current_page']) ? intval($post['current_page']) : 1;
            $keyword = !empty($post['text']) ? trim($post['text']) : '';

            $where = [['status', '=', 1]];
            if (!empty($keyword)) {
                $where[] = ['name|description|author', 'like', '%' . $keyword . '%'];
            }

            $total = Db::name('plugin')->where($where)->count();
            $list = Db::name('plugin')
                ->where($where)
                ->field('id,name,slug,category,version,author,icon,description,rating_count,rating_avg,is_hot,is_recommend,published_at')
                ->order('is_recommend', 'desc')
                ->order('download_count', 'desc')
                ->page($current_page, $limit)
                ->select()
                ->toArray();

            return message('success', true, [
                'data' => $list,
                'total' => $total,
                'current_page' => $current_page,
                'limit' => $limit,
            ]);
        }
    }

    public function register(){
        if(IS_POST){
            $post = $this->request->post();
            $appid = !empty($post['appid'])?intval($post['appid']):null;
            $username = !empty($post['username'])?$post['username']:null;
            $qq = !empty($post['qq'])?intval($post['qq']):null;
            $email = !empty($post['email'])?$post['email']:null;
            $password = !empty($post['password'])?$post['password']:null;
            try {
                validate(Register::class)->check($post);
            } catch (ValidateException $e) {
                // 验证失败 输出错误信息
                return message($e->getError() ,false);
            }
            $appInfo = Db::name('app')
                ->where([
                    'id' => $appid,
                ])
                ->find();
            if(empty($appInfo)){
                return message('不存在此应用！', false);
            }
            if($appInfo['status'] != 2){
                return message('该应用以停止运营或维护中！', false);
            }
            if($appInfo['register_switch'] != 1){
                return message('该应用未开放自助注册！', false);
            }
            $row = Db::name('user')
                ->where([
                    'username' => $username
                ])
                ->field('id')
                ->find();
            if($row){
                return message('平台已存在该用户名！', false);
            }
            try{
                $powerPriceModel = new \app\admin\model\PowerPriceModel();
                $power = $powerPriceModel->getDefaultPower(intval($appInfo['power_template']));
            }catch (\Exception $e){
                return message($e->getMessage(), false);
            }
            try{
                $data = [
                    'username' => $username,
                    'password' => get_password($password),
                    'phone' => '',
                    'qq' => $qq,
                    'email' => $email,
                    'appid' => $appid,
                    'status' => 1,
                    'balance' => 0,
                    'integral' => 0,
                    'power' => $power,
                    'addtime' => datetime(),
                    'userid' => 0
                ];
                Db::name('user')
                    ->insert($data);
                return message('注册成功！', true);
            }catch (\Exception $e){
                return message('注册失败，请联系站长处理！', false);
            }
        }
    }
}