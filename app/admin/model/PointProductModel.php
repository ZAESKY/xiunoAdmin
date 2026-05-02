<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use think\Exception;
use think\facade\Db;

class PointProductModel extends BaseModel
{
    protected $name = 'point_product';

    public function getInfo($id)
    {
        return self::where('id', intval($id))->find();
    }

    public function list()
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $currentPage = !empty($post['current_page']) ? $post['current_page'] : 1;
        $data = $this->buildSearchWhere('id|name', 'text', 'status');

        return self::order('id', 'desc')->where($data)->paginate([
            'list_rows' => $limit,
            'page' => $currentPage,
        ]);
    }

    public function edit()
    {
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : 0;
        $name = trim((string)($post['name'] ?? ''));
        $requiredPoints = isset($post['required_points']) ? intval($post['required_points']) : 0;
        $exchangeLimit = isset($post['exchange_limit']) ? intval($post['exchange_limit']) : 0;
        $status = !empty($post['status']) ? 1 : 0;
        $rewardItems = $this->parseRewardItems((string)($post['reward_info'] ?? ''));

        if ($name === '') {
            return message('请填写商品名称', false);
        }
        if ($requiredPoints <= 0) {
            return message('兑换所需积分必须大于0', false);
        }
        if ($exchangeLimit < 0) {
            return message('兑换上限不能小于0', false);
        }
        if (empty($rewardItems)) {
            return message('请填写奖品信息，一行一个', false);
        }

        $data = [
            'name' => $name,
            'image' => trim((string)($post['image'] ?? '')),
            'description' => trim((string)($post['description'] ?? '')),
            'type' => 'virtual_goods',
            'stock' => count($rewardItems),
            'required_points' => $requiredPoints,
            'exchange_limit' => $exchangeLimit,
            'reward_info' => implode("\n", $rewardItems),
            'status' => $status,
            'updated_at' => datetime(),
        ];

        Db::startTrans();
        try {
            if ($id > 0) {
                self::where('id', $id)->update($data);
                $productId = $id;
            } else {
                $data['created_at'] = datetime();
                $productId = self::insertGetId($data);
            }

            $pendingItems = $this->syncRewardPool($productId, $rewardItems);
            Db::name('point_product')->where('id', $productId)->update([
                'reward_info' => implode("\n", $pendingItems),
                'stock' => Db::name('point_product_reward')
                    ->where('product_id', $productId)
                    ->where('status', 'pending')
                    ->count(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return message(t('user.edit_success'), true);
    }

    public function drop($id)
    {
        if (empty($id)) {
            throw new Exception(t('validation.missing_id'));
        }
        Db::startTrans();
        try {
            Db::name('point_product_reward')->where('product_id', intval($id))->delete();
            self::where('id', intval($id))->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return true;
    }

    public function setStatus()
    {
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : 0;
        $status = !empty($post['status']) ? 1 : 0;
        if ($id <= 0) {
            throw new Exception(t('validation.missing_id'));
        }
        self::where('id', $id)->update(['status' => $status, 'updated_at' => datetime()]);
        return true;
    }

    private function parseRewardItems(string $rewardInfo): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rewardInfo);
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $items[] = $line;
            }
        }
        return array_values(array_unique($items));
    }

    private function syncRewardPool(int $productId, array $rewardItems): array
    {
        $issuedItems = Db::name('point_product_reward')
            ->where('product_id', $productId)
            ->where('status', 'issued')
            ->column('reward_content');
        $issuedMap = array_flip(array_map('strval', $issuedItems));

        Db::name('point_product_reward')
            ->where('product_id', $productId)
            ->where('status', 'pending')
            ->delete();

        $rows = [];
        $pendingItems = [];
        foreach ($rewardItems as $item) {
            if (isset($issuedMap[$item])) {
                continue;
            }
            $pendingItems[] = $item;
            $rows[] = [
                'product_id' => $productId,
                'reward_content' => $item,
                'status' => 'pending',
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ];
        }
        if (!empty($rows)) {
            Db::name('point_product_reward')->insertAll($rows);
        }
        return $pendingItems;
    }
}
