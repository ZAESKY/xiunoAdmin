<?php
namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;

/**
 * 日志-模型
 * @author 陌上花开
 * @since 2022/1/30
 * Class LogModel
 * @package app\admin\model
 */
class LogModel extends BaseModel
{
    // 设置数据表名
    protected $name = "log";

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

    public function drop($id){
        try{
            if(empty($id)){
                throw new Exception(t('log.missing_id'));
            }
            $row = $this->getInfo($id);
            if(!$row){
                throw new Exception(t('log.not_exist'));
            }
            self::where('id', $id)->delete();
            return true;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $power = !empty($post['power'])?intval($post['power']):'';

            $data = $this->buildSearchWhere('id|title|ip', 'text', '');

            if(!empty($power)){
                if($power == 1){
                    $data[] = ['is_admin', '=', 1];
                }else if($power == 2){
                    $data[] = ['is_admin', '=', 0];
                    $data[] = ['username', '<>', 0];
                }else{
                    $data[] = ['is_admin', '=', 0];
                    $data[] = ['username', '=', 0];
                }
            }

            $data[] = ['mark', '=', 1];
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
