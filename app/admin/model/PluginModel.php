<?php

namespace app\admin\model;

use app\common\model\BaseModel;
use app\common\model\NotificationModel;
use app\common\service\PluginPackageUploadService;
use app\common\service\PluginPackageIdentityService;
use app\common\service\PluginRewardService;
use app\common\service\PluginStorageService;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件-模型
 * @author QH授权系统
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
                $this->sanitizePluginForDisplay($result);
                return $result;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getInfoBySlug($slug, int $appId = 0)
    {
        try {
            $query = self::where('slug', $slug);
            if ($appId > 0) {
                $query->where('app_id', $appId);
            }
            $result = $query->find();
            if ($result) {
                $result = $result->toArray();
                if (!empty($result['images'])) {
                    $result['images'] = json_decode($result['images'], true);
                } else {
                    $result['images'] = [];
                }
                $this->attachRelatedPlugin($result);
                $this->attachPluginResources($result);
                $this->sanitizePluginForDisplay($result);
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
        try {
            $price = qh_money_format(!empty($post['price']) ? $post['price'] : '0.00');
        } catch (\InvalidArgumentException $e) {
            return message('plugin_action.price_format_error', false);
        }
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
            return message('plugin_admin.publish_type_required', false);
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
        $upload_token = !empty($post['upload_token']) ? trim((string)$post['upload_token']) : '';
        $icon_object_key = !empty($post['icon_object_key']) ? trim($post['icon_object_key']) : '';
        $cover_object_key = !empty($post['cover_object_key']) ? trim($post['cover_object_key']) : '';
        $update_description = isset($post['update_description']) ? trim((string)$post['update_description']) : '';
        $appId = intval($post['app_id'] ?? 0);
        if (!empty($id)) {
            $existingAppId = intval(self::where('id', $id)->value('app_id'));
            if ($existingAppId > 0) {
                $appId = $existingAppId;
            }
        }
        if ($appId <= 0 || !Db::name('app')->where('id', $appId)->find()) {
            return message('plugin_action.app_context_invalid', false);
        }

        if (empty($name)) {
            return message('plugin_action.name_required', false);
        }
        if (empty($slug)) {
            return message('plugin_action.slug_required', false);
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
            return message('plugin_action.slug_format_error', false);
        }
        if (empty($version)) {
            return message('plugin_action.version_required', false);
        }
        if (empty($category)) {
            return message('plugin_admin.category_required', false);
        }
        if (empty($description)) {
            return message('plugin_action.summary_required', false);
        }
        if (empty($icon)) {
            return message('plugin_admin.icon_required', false);
        }
        if ($origin_type == 2) {
            if (qh_money_to_cents($price) > 0) {
                return message('plugin_action.repost_paid_forbidden', false);
            }
            $price = '0.00';
        }
        if ($publish_type == 1) {
            if (empty($publish_time)) {
                return message('plugin_admin.publish_time_required', false);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $publish_time) || strtotime($publish_time) === false) {
                return message('plugin_admin.publish_time_invalid', false);
            }
            $publishTimestamp = strtotime($publish_time);
            if ($publishTimestamp < time() + 10 * 60) {
                return message('plugin_admin.publish_time_too_soon', false);
            }
            if ($publishTimestamp > strtotime('+6 months')) {
                return message('plugin_admin.publish_time_too_late', false);
            }
        } else {
            $publish_time = null;
        }
        if ($related_plugin_id > 0) {
            $relatedPlugin = self::where('id', $related_plugin_id)
                ->where('app_id', $appId)
                ->where('status', 1)
                ->find();
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

        $hasNewPackage = $upload_token !== '';
        $package = null;
        $plugin_dir = '';
        if ($hasNewPackage) {
            try {
                $package = PluginPackageUploadService::claim(
                    $upload_token,
                    'admin',
                    intval(session('adminId'))
                );
            } catch (\Throwable $e) {
                return message($e->getMessage(), false);
            }
            $file_path = $package['file_path'];
            $file_hash = $package['file_hash'];
            $file_size = $package['file_size'];
            $storage_driver = $package['storage_driver'];
            $package_object_key = $package['package_object_key'];
            $package_file_name = $package['package_file_name'];
            $package_mime_type = $package['package_mime_type'];
            $plugin_dir = (string)$package['plugin_dir'];
        }

        if (!empty($id)) {
            $row = $this->getInfo($id);
            if (!$row) {
                return message('plugin_action.not_found', false);
            }
            if (!$hasNewPackage) {
                $storage_driver = (string)($row['storage_driver'] ?? 'local');
            }
            $currentPluginDir = PluginPackageIdentityService::resolveRecord($row);
            if ($hasNewPackage && $currentPluginDir !== '' && !hash_equals($currentPluginDir, $plugin_dir)) {
                return message('新版本安装目录与现有插件不一致，请保持 ZIP 顶层目录为 ' . $currentPluginDir, false);
            }
            if (!$hasNewPackage) {
                $plugin_dir = $currentPluginDir;
            }
            if ($plugin_dir !== '') {
                $dirExists = self::where('app_id', $appId)->where('plugin_dir', $plugin_dir)->where('id', '<>', $id)->find();
                if ($dirExists) {
                    return message('插件安装目录已被其他市场插件使用：' . $plugin_dir, false);
                }
            }

            $exists = self::where('app_id', $appId)->where('slug', $slug)->where('id', '<>', $id)->find();
            if ($exists) {
                return message(t('plugin_action.slug_exists', ['slug' => $slug]), false);
            }
            $nameExists = self::where('app_id', $appId)->where('name', $name)->where('id', '<>', $id)->find();
            if ($nameExists) {
                return message(t('plugin_action.name_exists', ['name' => $name]), false);
            }

            $data = [
                'name' => $name, 'slug' => $slug, 'category' => $category,
                'plugin_dir' => $plugin_dir,
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

            if ($hasNewPackage) {
                $versionExists = Db::name('plugin_versions')
                    ->where('plugin_id', intval($id))
                    ->where('version', $version)
                    ->find();
                if ($versionExists) {
                    return message(t('plugin_action.version_exists_retry', ['version' => $version]), false);
                }
                $data['file_path'] = $file_path;
                $data['file_hash'] = $file_hash;
                $data['file_size'] = $file_size;
                $data['package_object_key'] = $package_object_key;
                $data['package_file_name'] = $package_file_name;
                $data['package_mime_type'] = $package_mime_type;
            } elseif ($version !== ($row['version'] ?? '')) {
                return message('plugin_action.version_package_required', false);
            }

            try {
                Db::startTrans();
                $lockedRow = Db::name('plugin')->where('id', intval($id))->lock(true)->find();
                if (!$lockedRow) {
                    throw new Exception(t('plugin_action.not_found'));
                }
                $isFirstApproval = $status === 1
                    && intval($lockedRow['status'] ?? 0) !== 1
                    && empty($lockedRow['published_at']);
                if ($status === 1 && intval($lockedRow['status'] ?? 0) !== 1) {
                    $data['published_at'] = ($publish_type == 1 && !empty($publish_time)) ? $publish_time : datetime();
                }
                self::where('id', $id)->data($data)->update();
                $versionId = $this->latestVersionId(intval($id));
                if ($hasNewPackage) {
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
                    PluginPackageUploadService::consume(intval($package['upload_id']), intval($id));
                }
                $this->syncPluginResources(intval($id), $versionId, 0, $icon, $cover, $storage_driver, $post);
                $reward = null;
                if ($isFirstApproval) {
                    $rewardPlugin = array_merge($lockedRow, $data, ['id' => intval($id)]);
                    $reward = PluginRewardService::issueFirstApproval(
                        $rewardPlugin,
                        intval(session('adminId')),
                        true
                    );
                }
                Db::commit();
                Cache::tag('QH_Plugin')->clear();
                $message = t('user.edit_success');
                $rewardText = $reward ? PluginRewardService::rewardText($reward) : '';
                if ($rewardText !== '') {
                    $message .= t('plugin_admin.reward_granted', ['reward' => $rewardText]);
                }
                return message($message, true, ['reward' => $reward]);
            } catch (\Exception $e) {
                Db::rollback();
                return message(t('user.edit_failed') . $e->getMessage(), false);
            }
        } else {
            if (!$hasNewPackage) {
                return message('plugin_admin.file_required', false);
            }

            $exists = self::where('app_id', $appId)->where('slug', $slug)->find();
            if ($exists) {
                return message(t('plugin_action.slug_exists', ['slug' => $slug]), false);
            }
            $nameExists = self::where('app_id', $appId)->where('name', $name)->find();
            if ($nameExists) {
                return message(t('plugin_action.name_exists', ['name' => $name]), false);
            }
            $dirExists = self::where('app_id', $appId)->where('plugin_dir', $plugin_dir)->find();
            if ($dirExists) {
                return message('插件安装目录已被其他市场插件使用：' . $plugin_dir, false);
            }

            $data = [
                'app_id' => $appId,
                'name' => $name, 'slug' => $slug, 'category' => $category,
                'plugin_dir' => $plugin_dir,
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
                PluginPackageUploadService::consume(intval($package['upload_id']), intval($pluginId));
                $this->syncPluginResources($pluginId, $versionId, 0, $icon, $cover, $storage_driver, $post);
                Db::commit();
                Cache::tag('QH_Plugin')->clear();
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
                throw new Exception(t('plugin_action.id_required'));
            }
            $row = $this->getInfo($id);
            if (!$row) {
                throw new Exception(t('plugin_action.not_found'));
            }

            $storage = new PluginStorageService();
            $storage->deletePackage($row);
            $versions = Db::name('plugin_versions')->where('plugin_id', $id)->select()->toArray();
            foreach ($versions as $version) {
                $storage->deletePackage($version);
            }
            $resources = Db::name('plugin_resources')->where('plugin_id', $id)->select()->toArray();
            foreach ($resources as $resource) {
                if (($resource['storage_driver'] ?? 'local') === 'oss' && !empty($resource['object_key'])) {
                    $storage->deleteObject((string)$resource['object_key']);
                }
            }

            // 删除插件
            self::where('id', $id)->delete();

            // 删除相关数据
            Db::name('plugin_comment')->where('plugin_id', $id)->delete();
            Db::name('plugin_rating')->where('plugin_id', $id)->delete();
            Db::name('plugin_download')->where('plugin_id', $id)->delete();
            Db::name('plugin_resources')->where('plugin_id', $id)->delete();
            Db::name('plugin_versions')->where('plugin_id', $id)->delete();

            Cache::tag('QH_Plugin')->clear();
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
            throw new Exception(t('plugin_action.version_exists', ['version' => $version]));
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
            $allowReward = !isset($post['issue_reward']) || intval($post['issue_reward']) === 1;

            if (empty($id)) {
                throw new Exception(t('plugin_action.id_required'));
            }
            if (!in_array($status, [0, 1, 2, 3], true)) {
                throw new Exception(t('plugin_admin.status_invalid'));
            }

            Db::startTrans();
            try {
                $row = Db::name('plugin')->where('id', $id)->lock(true)->find();
                if (!$row) {
                    throw new Exception(t('plugin_action.not_found'));
                }

                $data = ['status' => $status, 'audit_note' => $audit_note, 'updated_at' => datetime()];
                $isApprovalTransition = $status === 1 && intval($row['status'] ?? 0) !== 1;
                $isFirstApproval = $isApprovalTransition && empty($row['published_at']);

                // 如果状态改为已上架，记录上架时间（定时发布使用预选时间）
                if ($isApprovalTransition) {
                    if (intval($row['publish_type'] ?? 0) === 1 && !empty($row['publish_time'])) {
                        $data['published_at'] = $row['publish_time'];
                    } else {
                        $data['published_at'] = datetime();
                    }
                }

                self::where('id', $id)->data($data)->update();
                $reward = $isFirstApproval
                    ? PluginRewardService::issueFirstApproval(
                        array_merge($row, $data),
                        intval(session('adminId')),
                        $allowReward
                    )
                    : null;
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }

            Cache::tag('QH_Plugin')->clear();

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
                    $statusMap = [
                        0 => t('plugin_admin.status_pending'),
                        1 => t('plugin_admin.status_approved'),
                        2 => t('plugin_admin.status_offline'),
                        3 => t('plugin_admin.status_rejected'),
                    ];
                    $statusLabel = $statusMap[$status] ?? t('plugin_admin.status_unknown');
                    $noteText = !empty($audit_note)
                        ? t('plugin_admin.audit_note', ['note' => $audit_note])
                        : '';
                    if (is_array($reward)) {
                        $rewardText = PluginRewardService::rewardText($reward);
                        if ($rewardText !== '') {
                            $noteText .= t('plugin_admin.audit_reward', ['reward' => $rewardText]);
                        } elseif (!empty($reward['reason'])) {
                            $noteText .= t('plugin_admin.audit_reward_skipped', ['reason' => $reward['reason']]);
                        }
                    }
                    NotificationModel::add([
                        'user_id'    => intval($row['user_id']),
                        'title'      => t('plugin_admin.audit_notice_title'),
                        'content'    => t('plugin_admin.audit_notice_content', [
                            'plugin' => $row['name'],
                            'status' => $statusLabel,
                            'details' => $noteText,
                        ]),
                        'type'       => 'plugin_audit',
                        'link'       => '/UserPlugin/list.html',
                        'variables'  => [
                            'plugin_name' => $row['name'],
                            'review_status' => $statusLabel,
                            'audit_note' => $audit_note,
                        ],
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return ['reward' => $reward];
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function list()
    {
        try {
            $post = request()->post();
            $limit = qh_page_limit($post['limit'] ?? null, 10);
            $current_page = qh_page_number($post['current_page'] ?? null);
            $data = $this->buildSearchWhere('id|name|slug|author');
            if (isset($post['app_id']) && $post['app_id'] !== '') {
                $data[] = ['app_id', '=', intval($post['app_id'])];
            }

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
            $rewardMap = $this->rewardMap($pluginIds);
            foreach ($list as $item) {
                $pluginId = intval($item['id'] ?? 0);
                $item['purchase_count'] = $purchaseCounts[$pluginId] ?? 0;
                $item['download_record_count'] = $downloadCounts[$pluginId] ?? 0;
                $reward = $rewardMap[$pluginId] ?? [];
                $item['reward_status'] = $reward['status'] ?? '';
                $item['reward_points'] = intval($reward['points'] ?? 0);
                $item['reward_balance'] = qh_money_format($reward['balance'] ?? 0);
                $item['reward_reason'] = $reward['reason'] ?? '';
                $item['app_name'] = (string)(Db::name('app')->where('id', intval($item['app_id'] ?? 0))->value('name') ?? '');
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

    private function rewardMap(array $pluginIds): array
    {
        $pluginIds = array_values(array_unique(array_filter(array_map('intval', $pluginIds))));
        if (empty($pluginIds)) {
            return [];
        }
        $rows = Db::name('plugin_reward')
            ->whereIn('plugin_id', $pluginIds)
            ->where('scene', PluginRewardService::SCENE_FIRST_APPROVAL)
            ->field('plugin_id,status,points,balance,reason')
            ->select()
            ->toArray();
        $map = [];
        foreach ($rows as $row) {
            $map[intval($row['plugin_id'])] = $row;
        }
        return $map;
    }

    public function searchRelatedOptions($keyword = '', $excludeId = 0, $limit = 20, $appId = 0)
    {
        $appId = intval($appId);
        if ($appId <= 0) {
            return [];
        }
        $query = self::where('status', 1)
            ->where('app_id', $appId)
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
            ->where('app_id', intval($plugin['app_id'] ?? 0))
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
            $url = '';
            if (($resource['storage_driver'] ?? 'local') === 'oss' && !empty($resource['object_key'])) {
                try {
                    $url = (new PluginStorageService())->getMediaUrl((string)$resource['object_key']);
                } catch (\Throwable $e) {
                    $url = '';
                }
            } else {
                $url = qh_safe_url($resource['url'] ?? '', true);
            }
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

    private function sanitizePluginForDisplay(array &$plugin): void
    {
        foreach (['name' => 100, 'version' => 50, 'author' => 100, 'description' => 1000, 'update_description' => 2000, 'origin_author' => 100, 'origin_note' => 1000] as $field => $limit) {
            if (isset($plugin[$field])) {
                $plugin[$field] = qh_plain_text($plugin[$field], $limit);
            }
        }
        foreach (['icon', 'cover', 'iconUrl', 'coverUrl'] as $field) {
            if (isset($plugin[$field])) {
                $plugin[$field] = qh_safe_url($plugin[$field], true);
            }
        }
        foreach (['author_url', 'origin_url'] as $field) {
            if (isset($plugin[$field])) {
                $plugin[$field] = qh_safe_url($plugin[$field], false);
            }
        }
        if (isset($plugin['content'])) {
            $plugin['content'] = clean_rich_text($plugin['content']);
        }
        if (isset($plugin['images']) && is_array($plugin['images'])) {
            $plugin['images'] = array_values(array_filter(array_map(static function ($url) {
                return qh_safe_url($url, true);
            }, $plugin['images'])));
        }
        if (!empty($plugin['related_plugin']) && is_array($plugin['related_plugin'])) {
            $plugin['related_plugin']['name'] = qh_plain_text($plugin['related_plugin']['name'] ?? '', 100);
            $plugin['related_plugin']['icon'] = qh_safe_url($plugin['related_plugin']['icon'] ?? '', true);
        }
    }

    /**
     * 增加下载次数
     */
    public function incrementDownload($id)
    {
        try {
            self::where('id', $id)->inc('download_count')->update();
            Cache::tag('QH_Plugin')->clear();
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
            Cache::tag('QH_Plugin')->clear();
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
            Cache::tag('QH_Plugin')->clear();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
