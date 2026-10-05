<?php
namespace app\user\model;

use app\common\model\BaseModel;
use think\Exception;

/**
 * 日志-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class LogModel
 * @package app\user\model
 */
class LogModel extends BaseModel
{
    // 设置数据表名
    protected $name = "log";

    public function getInfo($id){
        try{
            $userInfo = parent::getUserInfo();
            if(!$userInfo){
                return false;
            }
            $result = self::where(['id' => $id, 'username' => $userInfo['id']])->find();
            if($result){
                $content = [
                    'Title' => '查找日志',
                    '操作' => '查找日志',
                    '日志ID' => $id,
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $result;
            }else{
                $content = [
                    'Title' => '查找日志',
                    '操作' => '查找日志',
                    '日志ID' => $id,
                    'Result' => '[errorCode:GetLogInfoError]'
                ];
                event('ActionLog', $content);
                return false;
            }
        }catch (\Exception $e){
            return false;
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
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $data = $this->buildSearchWhere('id|title|ip', 'text', '');
            $data[] = ['is_admin', '=', 0];
            $data[] = ['username', '=', $userInfo['id']];
            $data[] = ['username', '=', $userInfo['id']];
            $data[] = ['mark', '=', 1];
            try{
                $list = self::order('id' ,'desc')->where($data)->paginate([
                    'list_rows'=> $limit,
                    'page' => $current_page,
                ]);
                $content = [
                    'Title' => '日志列表',
                    '操作' => '获取日志列表',
                    '获取条数' => $list->total().' 条',
                    'Result' => 'success'
                ];
                event('ActionLog', $content);
                return $list;
            } catch (\Exception $e) {
                $content = [
                    'Title' => '日志列表',
                    '操作' => '获取日志列表',
                    '获取条数' => '0 条',
                    'Result' => '[errorCode:GetLogListError]'
                ];
                event('ActionLog', $content);
                throw new Exception(t('user.list_failed').'[errorCode:GetLogListError]');
            }
        }catch (\Exception $e){
            throw new Exception(t('user.list_failed').'[errorCode:GetLogListError]');
        }
    }
}
