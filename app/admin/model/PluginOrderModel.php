<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;
use think\facade\Db;

/**
 * 插件订单-模型
 * @author SF授权系统
 * @since 2026-05-03
 */
class PluginOrderModel extends BaseModel
{
    protected $name = 'plugin_order';

    public function getInfo($id)
    {
        try {
            $result = self::where('id', $id)->find();
            return $result ?: false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getInfoByOrderNo($orderNo)
    {
        try {
            $result = self::where('order_no', $orderNo)->find();
            return $result ?: false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function drop($id)
    {
        try {
            if (empty($id)) {
                throw new Exception(t('plugin_order.id_required'));
            }
            $row = $this->getInfo($id);
            if (!$row) {
                throw new Exception(t('plugin_order.not_found'));
            }
            self::where('id', $id)->delete();
            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function setStatus()
    {
        try {
            $post = request()->post();
            $id = !empty($post['id']) ? intval($post['id']) : null;
            $status = isset($post['status']) ? intval($post['status']) : 0;

            if (empty($id)) {
                throw new Exception(t('plugin_order.id_required'));
            }
            $row = $this->getInfo($id);
            if (!$row) {
                throw new Exception(t('plugin_order.not_found'));
            }

            $data = ['status' => $status, 'updated_at' => datetime()];
            if ($status == 1) {
                $data['paid_at'] = datetime();
            }

            self::where('id', $id)->data($data)->update();
            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function list()
    {
        try {
            $post = request()->post();
            $limit = sf_page_limit($post['limit'] ?? null, 10);
            $current_page = sf_page_number($post['current_page'] ?? null);
            $data = $this->buildSearchWhere('id|order_no|plugin_name');
            if (!empty($post['plugin_id'])) {
                $data[] = ['plugin_id', '=', intval($post['plugin_id'])];
            }

            $list = self::order('id', 'desc')
                ->where($data)
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page,
                ]);
            return $list;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
