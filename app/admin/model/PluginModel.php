<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use app\common\model\NotificationModel;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件-模型
 * @author SF授权系统
 * @since 2026-05-03
 */
class PluginModel extends BaseModel
{
    protected $name = 'plugin';

    public function getInfo($id)
    {
        try {
            $result = self::where('id', $id)->find();
            if ($result) {
                $result = $result->toArray();
                // 解析JSON字段
                if (!empty($result['images'])) {
                    $result['images'] = json_decode($result['images'], true);
                } else {
                    $result['images'] = [];
                }
                $this->attachRelatedPlugin($result);
                $this->attachPluginResources($result);
                return $result;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getInfoBySlug($slug)
    {
        try {
            $result = self::where('slug', $slug)->find();
            if ($result) {
                $result = $result->toArray();
                if (!empty($result['images'])) {
                    $result['images'] = json_decode($result['images'], true);
                } else {
                    $result['images'] = [];
                }
                $this->attachRelatedPlugin($result);
                $this->attachPluginResources($result);
                return $result;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function edit()
    {
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : null;
        $name = !empty($post['name']) ? trim($post['name']) : null;
        $slug = !empty($post['slug']) ? trim($post['slug']) : null;
        $category = !empty($post['category']) ? trim($post['category']) : '';
        $version = !empty($post['version']) ? trim($post['version']) : '1.0.0';
        $author = !empty($post['author']) ? trim($post['author']) : '';
        $author_url = !empty($post['author_url']) ? trim($post['author_url']) : '';
        $description = !empty($post['description']) ? trim($post['description']) : '';
        $content = !empty($post['content']) ? clean_rich_text($post['content']) : '';
        $icon = !empty($post['icon']) ? $post['icon'] : '';
        $cover = !empty($post['cover']) ? $post['cover'] : '';
        $images = !empty($post['images']) ? $post['images'] : [];
        $price = !empty($post['price']) ? floatval($post['price']) : 0.00;
        $pay_type = !empty($post['pay_type']) ? trim($post['pay_type']) : 'balance';
        $origin_type = !empty($post['origin_type']) ? intval($post['origin_type']) : 1;
        $origin_url = !empty($post['origin_url']) ? trim($post['origin_url']) : '';
        $origin_author = !empty($post['origin_author']) ? trim($post['origin_author']) : '';
        $origin_note = !empty($post['origin_note']) ? trim($post['origin_note']) : '';
        $related_plugin_id = !empty($post['related_plugin_id']) ? intval($post['related_plugin_id']) : 0;
        $sort = !empty($post['sort']) ? intval($post['sort']) : 0;
        $is_hot = !empty($post['is_hot']) ? 1 : 0;
        $is_recommend = !empty($post['is_recommend']) ? 1 : 0;
        if (!isset($post['publish_type']) || !in_array((string)$post['publish_type'], ['0', '1'], true)) {
            return message('请选择发布方式', false);
        }
        $publish_type = intval($post['publish_type']);
        $publish_time = !empty($post['publish_time']) ? trim($post['publish_time']) : null;
        $status = isset($post['status']) ? intval($post['status']) : 0;
        $audit_note = !empty($post['audit_note']) ? trim($post['audit_note']) : '';
        $file_path = !empty($post['file_path']) ? $post['file_path'] : '';
        $file_hash = !empty($post['file_hash']) ? $post['file_hash'] : '';
        $file_size = !empty($post['file_size']) ? intval($post['file_size']) : 0;
        $storage_driver = !empty($post['storage_driver']) ? trim($post['storage_driver']) : 'local';
        $package_object_key = !empty($post['package_object_key']) ? trim($post['package_object_key']) : '';
        $package_file_name = !empty($post['package_file_name']) ? trim($post['package_file_name']) : '';
        $package_mime_type = !empty($post['package_mime_type']) ? trim($post['package_mime_type']) : '';
        $icon_object_key = !empty($post['icon_object_key']) ? trim($post['icon_object_key']) : '';
        $cover_object_key = !empty($post['cover_object_key']) ? trim($post['cover_object_key']) : '';
        $update_description = isset($post['update_description']) ? trim((string)$post['update_description']) : '';

        if (empty($name)) {
            return message('插件名称不能为空', false);
        }
        if (empty($slug)) {
            return message('插件标识不能为空', false);
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
            return message('插件标识只能包含小写字母、数字、下划线和连字符', false);
        }
        if (empty($version)) {
            return message('版本号不能为空', false);
        }
        if (empty($category)) {
            return message('请选择插件分类', false);
        }
        if (empty($description)) {
            return message('插件简介不能为空', false);
        }
        if (empty($icon)) {
            return message('请上传插件图标', false);
        }
        if ($origin_type == 2) {
            if ($price > 0) {
                return message('转载插件不能设置为付费', false);
            }
            $price = 0.00;
        }
        if ($publish_type == 1) {
            if (empty($publish_time)) {
                return message('请选择定时发布时间', false);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $publish_time) || strtotime($publish_time) === false) {
                return message('发布时间格式不正确，请使用时间选择器选择', false);
            }
            $publishTimestamp = strtotime($publish_time);
            if ($publishTimestamp < time() + 10 * 60) {
                return message('发布时间必须选择当前时间的10分钟后', false);
            }
            if ($publishTimestamp > strtotime('+6 months')) {
                return message('发布时间不得大于6个月', false);
            }
        } else {
            $publish_time = null;
        }
        if ($related_plugin_id > 0) {
            $relatedPlugin = self::where('id', $related_plugin_id)->where('status', 1)->find();
            if (!$relatedPlugin) {
                $related_plugin_id = 0;
            }
            if (!empty($id) && $related_plugin_id == intval($id)) {
                $related_plugin_id = 0;
            }
        }

        if (is_array($images)) {
            $images = json_encode($images, JSON_UNESCAPED_UNICODE);
        }

        if (!empty($id)) {
            $row = $this->getInfo($id);
            if (!$row) {
                return message('插件不存在', false);
            }

            $exists = self::where('slug', $slug)->where('id', '<>', $id)->find();
            if ($exists) {
                return message('插件标识「' . $slug . '」已存在', false);
            }
            $nameExists = self::where('name', $name)->where('id', '<>', $id)->find();
            if ($nameExists) {
                return message('插件名称「' . $name . '」已存在', false);
            }

            $data = [
                'name' => $name, 'slug' => $slug, 'category' => $category,
                'version' => $version, 'author' => $author, 'author_url' => $author_url,
                'description' => $description, 'content' => $content,
                'icon' => $icon, 'images' => $images, 'cover' => $cover, 'price' => $price, 'pay_type' => $pay_type,
                'storage_driver' => $storage_driver, 'update_description' => $update_description,
                'icon_object_key' => $icon_object_key, 'cover_object_key' => $cover_object_key,
                'origin_type' => $origin_type, 'origin_url' => $origin_url,
                'origin_author' => $origin_author, 'origin_note' => $origin_note,
                'related_plugin_id' => $related_plugin_id,
                'sort' => $sort, 'is_hot' => $is_hot, 'is_recommend' => $is_recommend,
                'publish_type' => $publish_type, 'publish_time' => ($publish_type == 1 ? $publish_time : null),
                'status' => $status, 'audit_note' => $audit_note,
                'updated_at' => datetime(),
            ];

            if (!empty($file_path)) {
                $data['file_path'] = $file_path;
                $data['file_hash'] = $file_hash;
                $data['file_size'] = $file_size;
                $data['package_object_key'] = $package_object_key;
                $data['package_file_name'] = $package_file_name;
                $data['package_mime_type'] = $package_mime_type;
            } elseif ($version !== ($row['version'] ?? '')) {
                return message('发布新版本请先上传对应插件包', false);
            }

            if ($status == 1 && $row['status'] != 1) {
                $data['published_at'] = ($publish_type == 1 && !empty($publish_time)) ? $publish_time : datetime();
            }

            try {
                Db::startTrans();
                self::where('id', $id)->data($data)->update();
                $versionId = $this->latestVersionId(intval($id));
                if (!empty($file_path) && $file_path !== ($row['file_path'] ?? '')) {
                    $versionId = $this->insertVersionRecord(intval($id), $version, [
                        'storage_driver' => $storage_driver,
                        'package_path' => $file_path,
                        'package_object_key' => $package_object_key,
                        'package_file_name' => $package_file_name,
                        'package_file_size' => $file_size,
                        'package_mime_type' => $package_mime_type,
                        'package_hash' => $file_hash,
                        'update_description' => $update_description,
                        'created_by' => 0,
                    ]);
                }
                $this->syncPluginResources(intval($id), $versionId, 0, $icon, $cover, $storage_driver, $post);
                Db::commit();
                Cache::tag('SF_Plugin')->clear();
                return message(t('user.edit_success'), true);
            } catch (\Exception $e) {
                Db::rollback();
                return message(t('user.edit_failed') . $e->getMessage(), false);
            }
        } else {
            if (empty($file_path)) {
                return message('请先上传插件文件', false);
            }

            $exists = self::where('slug', $slug)->find();
            if ($exists) {
                return message('插件标识「' . $slug . '」已存在', false);
            }
            $nameExists = self::where('name', $name)->find();
            if ($nameExists) {
                return message('插件名称「' . $name . '」已存在', false);
            }

            $data = [
                'name' => $name, 'slug' => $slug, 'category' => $category,
                'version' => $version, 'author' => $author, 'author_url' => $author_url,
                'description' => $description, 'content' => $content,
                'icon' => $icon, 'images' => $images, 'cover' => $cover, 'price' => $price, 'pay_type' => $pay_type,
                'origin_type' => $origin_type, 'origin_url' => $origin_url,
                'origin_author' => $origin_author, 'origin_note' => $origin_note,
                'related_plugin_id' => $related_plugin_id,
                'file_path' => $file_path, 'file_hash' => $file_hash, 'file_size' => $file_size,
                'storage_driver' => $storage_driver,
                'package_object_key' => $package_object_key,
                'package_file_name' => $package_file_name,
                'package_mime_type' => $package_mime_type,
                'icon_object_key' => $icon_object_key,
                'cover_object_key' => $cover_object_key,
                'update_description' => $update_description,
                'sort' => $sort, 'is_hot' => $is_hot, 'is_recommend' => $is_recommend,
                'publish_type' => $publish_type, 'publish_time' => ($publish_type == 1 ? $publish_time : null),
                'status' => $status, 'audit_note' => $audit_note,
                'created_at' => datetime(), 'updated_at' => datetime(),
            ];

            if ($status == 1) {
                $data['published_at'] = ($publish_type == 1 && !empty($publish_time)) ? $publish_time : datetime();
            }

            try {
                Db::startTrans();
                $pluginId = self::insertGetId($data);
                $versionId = $this->insertVersionRecord($pluginId, $version, [
                    'storage_driver' => $storage_driver,
                    'package_path' => $file_path,
                    'package_object_key' => $package_object_key,
                    'package_file_name' => $package_file_name,
                    'package_file_size' => $file_size,
                    'package_mime_type' => $package_mime_type,
                    'package_hash' => $file_hash,
                    'update_description' => $update_description,
                    'created_by' => 0,
                ]);
                $this->syncPluginResources($pluginId, $versionId, 0, $icon, $cover, $storage_driver, $post);
                Db::commit();
                Cache::tag('SF_Plugin')->clear();
                return message(t('user.add_success'), true);
            } catch (\Exception $e) {
                Db::rollback();
                return message(t('user.add_failed') . $e->getMessage(), false);
            }
        }
    }

    public function drop($id)
    {
        try {
            if (empty($id)) {
                throw new Exception('插件ID不能为空');
            }
            $row = $this->getInfo($id);
            if (!$row) {
                throw new Exception('插件不存在');
            }

            // 删除插件文件
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                @unlink($row['file_path']);
            }

            // 删除插件
            self::where('id', $id)->delete();

            // 删除相关数据
            Db::name('plugin_comment')->where('plugin_id', $id)->delete();
            Db::name('plugin_rating')->where('plugin_id', $id)->delete();
            Db::name('plugin_download')->where('plugin_id', $id)->delete();

            Cache::tag('SF_Plugin')->clear();
            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function insertVersionRecord(int $pluginId, string $version, array $data): int
    {
        $exists = Db::name('plugin_versions')
            ->where('plugin_id', $pluginId)
            ->where('version', $version)
            ->find();
        if ($exists) {
            throw new Exception('当前插件下版本号「' . $version . '」已存在');
        }
        $data['plugin_id'] = $pluginId;
        $data['version'] = $version;
        $data['created_at'] = datetime();
        $data['updated_at'] = datetime();
        return Db::name('plugin_versions')->insertGetId($data);
    }

    private function latestVersionId(int $pluginId): int
    {
        $row = Db::name('plugin_versions')
            ->where('plugin_id', $pluginId)
            ->order('created_at', 'desc')
            ->order('id', 'desc')
            ->field('id')
            ->find();
        return $row ? intval($row['id']) : 0;
    }

    private function syncPluginResources(int $pluginId, int $versionId, int $userId, string $icon, string $cover, string $storageDriver, array $post): void
    {
        Db::name('plugin_resources')
            ->where('plugin_id', $pluginId)
            ->whereIn('resource_type', ['icon', 'cover'])
            ->delete();

        $now = datetime();
        $rows = [];
        if ($icon !== '') {
            $rows[] = [
                'plugin_id' => $pluginId,
                'version_id' => $versionId,
                'resource_type' => 'icon',
                'storage_driver' => $post['icon_storage_driver'] ?? $storageDriver,
                'url' => $icon,
                'object_key' => $post['icon_object_key'] ?? '',
                'file_name' => $post['icon_file_name'] ?? '',
                'file_size' => intval($post['icon_file_size'] ?? 0),
                'mime_type' => $post['icon_mime_type'] ?? '',
                'sort_order' => 0,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($cover !== '') {
            $rows[] = [
                'plugin_id' => $pluginId,
                'version_id' => $versionId,
                'resource_type' => 'cover',
                'storage_driver' => $post['cover_storage_driver'] ?? $storageDriver,
                'url' => $cover,
                'object_key' => $post['cover_object_key'] ?? '',
                'file_name' => $post['cover_file_name'] ?? '',
                'file_size' => intval($post['cover_file_size'] ?? 0),
                'mime_type' => $post['cover_mime_type'] ?? '',
                'sort_order' => 0,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($rows)) {
            Db::name('plugin_resources')->insertAll($rows);
        }
    }

    public function setStatus()
    {
        try {
            $post = request()->post();
            $id = !empty($post['id']) ? intval($post['id']) : null;
            $status = isset($post['status']) ? intval($post['status']) : 0;
            $audit_note = !empty($post['audit_note']) ? trim($post['audit_note']) : '';

            if (empty($id)) {
                throw new Exception('插件ID不能为空');
            }
            $row = $this->getInfo($id);
            if (!$row) {
                throw new Exception('插件不存在');
            }

            $data = ['status' => $status, 'audit_note' => $audit_note];

            // 如果状态改为已上架，记录上架时间（定时发布使用预选时间）
            if ($status == 1 && $row['status'] != 1) {
                if (($row['publish_type'] ?? 0) == 1 && !empty($row['publish_time'])) {
                    $data['published_at'] = $row['publish_time'];
                } else {
                    $data['published_at'] = datetime();
                }
            }

            self::where('id', $id)->data($data)->update();
            Cache::tag('SF_Plugin')->clear();

            // 审核通过时将临时图片移动到正式目录
            if ($status == 1 && !empty($row['content'])) {
                $movedContent = move_temp_images_in_content($row['content']);
                if ($movedContent !== $row['content']) {
                    self::where('id', $id)->update(['content' => $movedContent]);
                }
            }

            // 通知插件开发者审核结果
            try {
                if (!empty($row['user_id'])) {
                    $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已下架', 3 => '已拒绝'];
                    $statusLabel = $statusMap[$status] ?? '未知';
                    $noteText = !empty($audit_note) ? '，备注：' . $audit_note : '';
                    NotificationModel::add([
                        'user_id'    => intval($row['user_id']),
                        'title'      => '插件审核通知',
                        'content'    => '您的插件「' . $row['name'] . '」审核状态已更新为：' . $statusLabel . $noteText,
                        'type'       => 'plugin_audit',
                        'link'       => '/UserPlugin/list.html',
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function list()
    {
        try {
            $post = request()->post();
            $limit = !empty($post['limit']) ? $post['limit'] : 10;
            $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
            $data = $this->buildSearchWhere('id|name|slug|author');

            $list = self::order('sort', 'desc')
                ->order('id', 'desc')
                ->where($data)
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page,
                ]);
            $pluginIds = [];
            foreach ($list as $item) {
                $pluginId = intval($item['id'] ?? 0);
                if ($pluginId > 0) {
                    $pluginIds[] = $pluginId;
                }
            }
            $purchaseCounts = $this->countByPlugin('plugin_purchase', $pluginIds);
            $downloadCounts = $this->countByPlugin('plugin_download', $pluginIds);
            foreach ($list as $item) {
                $pluginId = intval($item['id'] ?? 0);
                $item['purchase_count'] = $purchaseCounts[$pluginId] ?? 0;
                $item['download_record_count'] = $downloadCounts[$pluginId] ?? 0;
            }
            return $list;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function countByPlugin(string $table, array $pluginIds): array
    {
        $pluginIds = array_values(array_unique(array_filter(array_map('intval', $pluginIds))));
        if (empty($pluginIds)) {
            return [];
        }
        $rows = Db::name($table)
            ->whereIn('plugin_id', $pluginIds)
            ->field('plugin_id, COUNT(*) AS total')
            ->group('plugin_id')
            ->select()
            ->toArray();
        $map = [];
        foreach ($rows as $row) {
            $map[intval($row['plugin_id'])] = intval($row['total']);
        }
        return $map;
    }

    public function searchRelatedOptions($keyword = '', $excludeId = 0, $limit = 20)
    {
        $query = self::where('status', 1)
            ->field('id,name,slug,version,icon,price,pay_type');
        if ($excludeId > 0) {
            $query->where('id', '<>', intval($excludeId));
        }
        $keyword = trim((string)$keyword);
        if ($keyword !== '') {
            $keyword = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
            $query->where('name|slug|author', 'like', '%' . $keyword . '%');
        }
        return $query->order('sort', 'desc')->order('id', 'desc')->limit($limit)->select()->toArray();
    }

    private function attachRelatedPlugin(&$plugin)
    {
        $relatedId = !empty($plugin['related_plugin_id']) ? intval($plugin['related_plugin_id']) : 0;
        if ($relatedId <= 0) {
            $plugin['related_plugin'] = null;
            return;
        }
        $related = self::where('id', $relatedId)
            ->field('id,name,slug,version,icon,price,pay_type,status')
            ->find();
        $plugin['related_plugin'] = $related ? $related->toArray() : null;
    }

    private function attachPluginResources(&$plugin): void
    {
        $pluginId = intval($plugin['id'] ?? 0);
        $plugin['iconUrl'] = $plugin['icon'] ?? '';
        $plugin['coverUrl'] = $plugin['cover'] ?? '';
        if ($pluginId <= 0) {
            return;
        }

        $resources = Db::name('plugin_resources')
            ->where('plugin_id', $pluginId)
            ->whereIn('resource_type', ['icon', 'cover'])
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        foreach ($resources as $resource) {
            $url = $resource['url'] ?: ($resource['object_key'] ?? '');
            if ($resource['resource_type'] === 'icon' && $url !== '') {
                $plugin['icon'] = $url;
                $plugin['iconUrl'] = $url;
                $plugin['icon_object_key'] = $resource['object_key'] ?? ($plugin['icon_object_key'] ?? '');
            } elseif ($resource['resource_type'] === 'cover' && $url !== '') {
                $plugin['cover'] = $url;
                $plugin['coverUrl'] = $url;
                $plugin['cover_object_key'] = $resource['object_key'] ?? ($plugin['cover_object_key'] ?? '');
            }
        }
    }

    /**
     * 增加下载次数
     */
    public function incrementDownload($id)
    {
        try {
            self::where('id', $id)->inc('download_count')->update();
            Cache::tag('SF_Plugin')->clear();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 更新评分统计
     */
    public function updateRatingStats($pluginId)
    {
        try {
            // 从 plugin_rating 表统计
            $ratingStats = Db::name('plugin_rating')
                ->where('plugin_id', $pluginId)
                ->field('COUNT(*) as count, SUM(rating) as total')
                ->find();

            // 从 plugin_comment 表统计（已审核通过的评论中的评分）
            $commentStats = Db::name('plugin_comment')
                ->where('plugin_id', $pluginId)
                ->where('status', 1)
                ->where('rating', '>', 0)
                ->field('COUNT(*) as count, SUM(rating) as total')
                ->find();

            $totalCount = intval($ratingStats['count']) + intval($commentStats['count']);
            $totalSum = floatval($ratingStats['total']) + floatval($commentStats['total']);
            $avg = $totalCount > 0 ? round($totalSum / $totalCount, 2) : 0;

            $data = [
                'rating_count' => $totalCount,
                'rating_avg' => $avg,
            ];

            self::where('id', $pluginId)->data($data)->update();
            Cache::tag('SF_Plugin')->clear();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 更新评论数量
     */
    public function updateCommentCount($pluginId)
    {
        try {
            $count = Db::name('plugin_comment')
                ->where('plugin_id', $pluginId)
                ->where('status', 1)
                ->count();

            self::where('id', $pluginId)->data(['comment_count' => $count])->update();
            Cache::tag('SF_Plugin')->clear();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
