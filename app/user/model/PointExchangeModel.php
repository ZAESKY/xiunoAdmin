<?php

namespace app\user\model;

use app\common\model\BaseModel;
use app\common\model\PointLogModel;
use think\Exception;
use think\facade\Db;

class PointExchangeModel extends BaseModel
{
    protected $name = 'point_product';

    public function list()
    {
        $post = request()->post();
        $limit = sf_page_limit($post['limit'] ?? null, 12);
        $currentPage = sf_page_number($post['current_page'] ?? null);
        $data = [['status', '=', 1]];
        $text = trim((string)($post['text'] ?? ''));
        if ($text !== '') {
            $data[] = ['name', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%'];
        }

        $list = self::where($data)->order('id', 'desc')->paginate([
            'list_rows' => $limit,
            'page' => $currentPage,
        ]);
        $list->each(static function ($item) {
            $item['description'] = clean_rich_text($item['description'] ?? '');
            return $item;
        });
        return $list;
    }

    public function exchange(int $userId, int $appid)
    {
        $productId = intval(request()->post('id', 0));
        if ($productId <= 0) {
            throw new Exception(t('validation.missing_id'));
        }

        Db::startTrans();
        try {
            $user = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$user) {
                throw new Exception(t('user.info_error'));
            }
            if ((int)$user['integral'] < 0) {
                throw new Exception(t('point_exchange.user_points_invalid'));
            }

            $product = Db::name('point_product')->where('id', $productId)->lock(true)->find();
            if (!$product || (int)$product['status'] !== 1) {
                throw new Exception(t('point_exchange.product_unavailable'));
            }
            if ((int)$product['stock'] <= 0) {
                throw new Exception(t('point_exchange.insufficient_stock'));
            }

            $costPoints = (int)$product['required_points'];
            if ($costPoints <= 0) {
                throw new Exception(t('point_exchange.points_config_invalid'));
            }
            if ((int)$user['integral'] < $costPoints) {
                throw new Exception(t('point_exchange.insufficient_points'));
            }

            $limit = (int)$product['exchange_limit'];
            if ($limit > 0) {
                $usedCount = Db::name('point_exchange_record')
                    ->where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->where('status', 'success')
                    ->count();
                if ($usedCount >= $limit) {
                    throw new Exception(t('point_exchange.limit_reached'));
                }
            }

            $reward = Db::name('point_product_reward')
                ->where('product_id', $productId)
                ->where('status', 'pending')
                ->orderRaw('RAND()')
                ->lock(true)
                ->find();
            if (!$reward) {
                throw new Exception(t('point_exchange.insufficient_stock'));
            }

            $recordId = Db::name('point_exchange_record')->insertGetId([
                'user_id' => $userId,
                'product_id' => $productId,
                'product_name' => $product['name'],
                'cost_points' => $costPoints,
                'reward_info' => $reward['reward_content'],
                'status' => 'success',
                'created_at' => datetime(),
            ]);

            $deducted = Db::name('user')
                ->where('id', $userId)
                ->where('integral', '>=', $costPoints)
                ->dec('integral', $costPoints)
                ->update();
            if (!$deducted) {
                throw new Exception(t('point_exchange.insufficient_points'));
            }

            $issued = Db::name('point_product_reward')
                ->where('id', $reward['id'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'issued',
                    'user_id' => $userId,
                    'record_id' => $recordId,
                    'issued_at' => datetime(),
                    'updated_at' => datetime(),
                ]);
            if (!$issued) {
                throw new Exception(t('point_exchange.reward_already_issued'));
            }

            Db::name('point_product')
                ->where('id', $productId)
                ->where('stock', '>', 0)
                ->dec('stock', 1)
                ->update();

            PointLogModel::add(
                $userId,
                'exchange',
                -$costPoints,
                t('point_exchange.log_description', ['product' => $product['name']]),
                'point_exchange',
                (string)$recordId,
                $recordId
            );

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function myRecords(int $userId)
    {
        $post = request()->post();
        $limit = sf_page_limit($post['limit'] ?? null, 10);
        $currentPage = sf_page_number($post['current_page'] ?? null);

        $list = Db::name('point_exchange_record')->alias('r')
            ->join('point_product p', 'r.product_id = p.id', 'LEFT')
            ->field('r.*, p.image, p.description')
            ->where('r.user_id', $userId)
            ->order('r.id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $currentPage,
            ]);
        $list->each(static function ($item) {
            $item['description'] = clean_rich_text($item['description'] ?? '');
            return $item;
        });
        return $list;
    }
}
