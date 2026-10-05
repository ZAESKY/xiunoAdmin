<?php

namespace app\user\service;

use app\admin\model\PluginModel;
use app\common\model\NotificationModel;
use app\common\model\PointLogModel;
use app\common\service\BaseService;
use app\common\service\PluginCommissionService;
use app\common\service\PluginPackageUploadService;
use app\common\service\PluginStorageService;
use app\common\service\RebateRiskService;
use app\common\service\WithdrawableBalanceService;
use think\Exception;

/**
 * 用户插件服务
 * @author QH授权系统
 * @since 2026-05-03
 */
class UserPluginService extends BaseService
{
    public function __construct()
    {
        $this->model = new PluginModel();
    }

    /**
     * 插件市场列表（所有已上架的插件）
     */
    public function marketList(?array $params = null)
    {
        // v2 主题市场接口与授权中心页面共用同一套查询，避免筛选和字段口径分叉。
        $post = $params ?? request()->post();
        $limit = qh_page_limit($post['limit'] ?? null, 10);
        $current_page = qh_page_number($post['current_page'] ?? null);
        $keyword = !empty($post['text']) ? trim($post['text']) : '';
        $price_type = !empty($post['price_type']) ? $post['price_type'] : '';
        $category = !empty($post['category']) ? $post['category'] : '';
        $sort = !empty($post['sort']) ? $post['sort'] : 'default';

        $where = [['status', '=', 1]];

        if (!empty($keyword)) {
            $keyword = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
            $where[] = ['name|description|author', 'like', '%' . $keyword . '%'];
        }

        if ($price_type === 'free') {
            $where[] = ['price', '=', 0];
        } elseif ($price_type === 'paid') {
            $where[] = ['price', '>', 0];
        }

        if (!empty($category)) {
            $where[] = ['category', '=', $category];
        }

        $query = \think\facade\Db::name('plugin')
            ->where($where)
            ->field('id,user_id,name,slug,category,version,author,icon,cover,price,pay_type,description,download_count,rating_count,rating_avg,comment_count,is_hot,is_recommend,published_at,publish_type,publish_time,updated_at');

        if ($sort === 'downloads') {
            $query->order('download_count', 'desc');
        } elseif ($sort === 'comments') {
            $query->order('comment_count', 'desc');
        } elseif ($sort === 'rating') {
            $query->order('rating_avg', 'desc');
        } elseif ($sort === 'newest') {
            $query->order('published_at', 'desc');
        } else {
            $query->order('sort', 'desc')->order('id', 'desc');
        }

        $list = $query->paginate([
            'list_rows' => $limit,
            'page' => $current_page,
        ]);
        $list->each(function ($item) {
            $this->sanitizePublicPlugin($item);
            $this->decorateAuthor($item);
            return $item;
        });
        return $list;
    }

    /**
     * 已上架插件的公共详情。只返回市场展示需要的字段，不包含私有存储路径。
     */
    public function publicMarketDetail(int $pluginId): ?array
    {
        if ($pluginId <= 0) {
            return null;
        }

        $plugin = \think\facade\Db::name('plugin')
            ->where('id', $pluginId)
            ->where('status', 1)
            ->field('id,user_id,name,slug,category,version,author,author_url,description,content,icon,cover,images,origin_type,origin_url,origin_author,origin_note,related_plugin_id,package_file_name,file_size,file_hash,update_description,price,pay_type,download_count,rating_count,rating_avg,comment_count,is_hot,is_recommend,published_at,created_at,updated_at')
            ->find();
        if (!$plugin) {
            return null;
        }

        if (!empty($plugin['images'])) {
            $images = json_decode((string)$plugin['images'], true);
            $plugin['images'] = is_array($images)
                ? array_values(array_filter(array_map(static function ($url) {
                    return qh_safe_url($url, true);
                }, $images)))
                : [];
        } else {
            $plugin['images'] = [];
        }

        $this->enrichPluginDetail($plugin);

        $versions = \think\facade\Db::name('plugin_versions')
            ->where('plugin_id', $pluginId)
            ->field('id,plugin_id,version,package_file_name,package_file_size,package_hash,update_description,created_by,created_at,updated_at,storage_driver,package_path,package_object_key')
            ->order('created_at', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();
        $storage = new PluginStorageService();
        foreach ($versions as &$version) {
            $this->decorateVersionAuthor($version);
            $version['is_latest'] = (string)$version['version'] === (string)$plugin['version'];
            try {
                $storage->assertValidPackageRecord($version);
                $version['is_available'] = true;
            } catch (\Throwable $e) {
                $version['is_available'] = false;
            }
            $version['update_description'] = qh_plain_text(
                $version['update_description'] ?? '',
                2000
            );
            unset(
                $version['plugin_id'],
                $version['created_by'],
                $version['storage_driver'],
                $version['package_path'],
                $version['package_object_key']
            );
        }
        unset($version);
        $plugin['versions'] = $versions;

        $plugin['related_plugin'] = null;
        if (!empty($plugin['related_plugin_id'])) {
            $related = \think\facade\Db::name('plugin')
                ->where('id', intval($plugin['related_plugin_id']))
                ->where('status', 1)
                ->field('id,user_id,name,slug,version,author,icon,cover,price,pay_type,description,updated_at')
                ->find();
            if ($related) {
                $this->sanitizePublicPlugin($related);
                $this->decorateAuthor($related);
                unset($related['user_id']);
                $plugin['related_plugin'] = $related;
            }
        }

        $authorPlugins = [];
        if (!empty($plugin['user_id'])) {
            $authorPlugins = \think\facade\Db::name('plugin')
                ->where('user_id', intval($plugin['user_id']))
                ->where('id', '<>', $pluginId)
                ->where('status', 1)
                ->field('id,user_id,name,slug,version,author,icon,cover,price,pay_type,description,updated_at')
                ->order('published_at', 'desc')
                ->order('id', 'desc')
                ->limit(3)
                ->select()
                ->toArray();
        }
        foreach ($authorPlugins as &$item) {
            $this->sanitizePublicPlugin($item);
            $this->decorateAuthor($item);
            unset($item['user_id']);
        }
        unset($item);
        $plugin['author_plugins'] = $authorPlugins;

        $referencing = \think\facade\Db::name('plugin')
            ->where('related_plugin_id', $pluginId)
            ->where('status', 1)
            ->field('id,user_id,name,slug,version,author,icon,cover,price,pay_type,description,updated_at')
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit(6)
            ->select()
            ->toArray();
        foreach ($referencing as &$item) {
            $this->sanitizePublicPlugin($item);
            $this->decorateAuthor($item);
            unset($item['user_id']);
        }
        unset($item);
        $plugin['referencing_plugins'] = $referencing;

        // 主题端详情页只展示已经审核通过的最新评论。这里直接复用现有评论表，
        // 不暴露用户 ID、审核状态等内部字段，也避免主题端另起一套评论接口。
        $comments = \think\facade\Db::name('plugin_comment')
            ->alias('c')
            ->leftJoin('user u', 'c.user_id = u.id')
            ->where('c.plugin_id', $pluginId)
            ->where('c.status', 1)
            ->field('c.id,c.content,c.rating,c.reply_content,c.reply_at,c.created_at,u.username')
            ->order('c.id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();
        foreach ($comments as &$comment) {
            $comment['id'] = (int)($comment['id'] ?? 0);
            $comment['rating'] = max(1, min(5, (int)($comment['rating'] ?? 0)));
            $comment['username'] = qh_plain_text($comment['username'] ?? '匿名用户', 80);
            $comment['content'] = qh_plain_text($comment['content'] ?? '', 500);
            $comment['reply_content'] = qh_plain_text($comment['reply_content'] ?? '', 500);
            $comment['reply_at'] = (string)($comment['reply_at'] ?? '');
            $comment['created_at'] = (string)($comment['created_at'] ?? '');
        }
        unset($comment);
        $plugin['comments'] = $comments;

        unset($plugin['user_id']);
        return $plugin;
    }

    /**
     * 插件市场首页聚合数据：推荐、新发布、下载最多。
     */
    public function marketShowcase(): array
    {
        $fields = 'id,user_id,name,slug,category,version,author,icon,cover,price,pay_type,description,'
            . 'download_count,rating_count,rating_avg,comment_count,is_hot,is_recommend,sort,'
            . 'published_at,publish_type,publish_time,updated_at';

        $featured = \think\facade\Db::name('plugin')
            ->where('status', 1)
            ->where('is_recommend', 1)
            ->field($fields)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit(3)
            ->select()
            ->toArray();

        $newest = \think\facade\Db::name('plugin')
            ->where('status', 1)
            ->field($fields)
            ->order('published_at', 'desc')
            ->order('id', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        $downloads = \think\facade\Db::name('plugin')
            ->where('status', 1)
            ->field($fields)
            ->order('download_count', 'desc')
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        $groups = [&$featured, &$newest, &$downloads];
        foreach ($groups as &$group) {
            foreach ($group as &$item) {
                $this->sanitizePublicPlugin($item);
                $this->decorateAuthor($item);
            }
            unset($item);
        }
        unset($group);

        return [
            'featured' => $featured,
            'newest' => $newest,
            'downloads' => $downloads,
        ];
    }

    /**
     * 我的插件列表（只看自己发布的）
     */
    public function myList($userId)
    {
        $post = request()->post();
        $limit = qh_page_limit($post['limit'] ?? null, 10);
        $current_page = qh_page_number($post['current_page'] ?? null);
        $keyword = !empty($post['text']) ? trim($post['text']) : '';
        $status = isset($post['status']) && $post['status'] !== '' ? intval($post['status']) : null;

        $where = [['user_id', '=', intval($userId)]];

        if (!empty($keyword)) {
            $keyword = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
            $where[] = ['name|slug', 'like', '%' . $keyword . '%'];
        }

        if ($status !== null) {
            $where[] = ['status', '=', $status];
        }

        $list = \think\facade\Db::name('plugin')
            ->where($where)
            ->field('id,name,slug,version,author,icon,price,download_count,rating_count,rating_avg,comment_count,status,audit_note,created_at,updated_at')
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page,
            ]);

        $pluginIds = [];
        foreach ($list as $item) {
            $pluginIds[] = intval($item['id'] ?? 0);
        }
        $rewardMap = [];
        if (!empty($pluginIds)) {
            $rewardRows = \think\facade\Db::name('plugin_reward')
                ->whereIn('plugin_id', array_values(array_filter($pluginIds)))
                ->where('scene', 'first_approval')
                ->field('plugin_id,status,points,balance,reason')
                ->select()
                ->toArray();
            foreach ($rewardRows as $rewardRow) {
                $rewardMap[intval($rewardRow['plugin_id'])] = $rewardRow;
            }
        }
        foreach ($list as $item) {
            $reward = $rewardMap[intval($item['id'] ?? 0)] ?? [];
            $item['reward_status'] = $reward['status'] ?? '';
            $item['reward_points'] = intval($reward['points'] ?? 0);
            $item['reward_balance'] = qh_money_format($reward['balance'] ?? 0);
            $item['reward_reason'] = $reward['reason'] ?? '';
        }

        return $list;
    }

    /**
     * 用户发布/编辑插件
     */
    public function createOrEdit($userId)
    {
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : null;
        $name = !empty($post['name']) ? qh_plain_text($post['name'], 100) : null;
        $slug = !empty($post['slug']) ? trim($post['slug']) : null;
        $category = !empty($post['category']) ? trim((string)$post['category']) : '';
        $version = !empty($post['version']) ? qh_plain_text($post['version'], 50) : '1.0.0';
        // 作者默认取当前用户名
        $userInfo = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        $author = !empty($post['author']) ? qh_plain_text($post['author'], 100) : qh_plain_text($userInfo['username'] ?? '', 100);
        $author_url = !empty($post['author_url']) ? qh_safe_url($post['author_url'], false) : '';
        $description = !empty($post['description']) ? qh_plain_text($post['description'], 1000) : '';
        $content = !empty($post['content']) ? clean_rich_text($post['content']) : '';
        $icon = !empty($post['icon']) ? qh_safe_url($post['icon'], true) : '';
        $cover = !empty($post['cover']) ? qh_safe_url($post['cover'], true) : '';
        $images = !empty($post['images']) ? $post['images'] : [];
        $updateDescription = isset($post['update_description']) ? qh_plain_text($post['update_description'], 2000) : qh_plain_text($post['updateDescription'] ?? '', 2000);
        try {
            $price = qh_money_format(!empty($post['price']) ? $post['price'] : '0.00');
        } catch (\InvalidArgumentException $e) {
            return message('plugin_action.price_format_error', false);
        }
        $pay_type = !empty($post['pay_type']) ? trim((string)$post['pay_type']) : 'balance';

        // 原创/转载
        $origin_type = !empty($post['origin_type']) ? intval($post['origin_type']) : 1;
        $origin_url = !empty($post['origin_url']) ? qh_safe_url($post['origin_url'], false) : '';
        $origin_author = !empty($post['origin_author']) ? qh_plain_text($post['origin_author'], 100) : '';
        $origin_note = !empty($post['origin_note']) ? qh_plain_text($post['origin_note'], 1000) : '';
        $related_plugin_id = !empty($post['related_plugin_id']) ? intval($post['related_plugin_id']) : 0;

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
            return message('plugin_ui.category_required', false);
        }
        if (!in_array($category, ['feature', 'security', 'content', 'ui', 'payment', 'dev', 'analytics', 'social', 'other'], true)) {
            return message('plugin_action.category_invalid', false);
        }
        if (empty($description)) {
            return message('plugin_action.summary_required', false);
        }
        if (empty($icon)) {
            return message('plugin_ui.icon_upload_required', false);
        }
        if (!in_array($pay_type, ['balance', 'points'], true)) {
            return message('plugin_action.payment_method_invalid', false);
        }
        if (qh_money_to_cents($price) < 0 || qh_money_to_cents($price) > 1000000000) {
            return message('plugin_action.price_out_of_range', false);
        }
        if ($pay_type === 'points' && qh_money_to_cents($price) % 100 !== 0) {
            return message('plugin_action.points_integer', false);
        }

        // 原创/转载校验
        if (!in_array($origin_type, [1, 2])) {
            return message('plugin_action.origin_required', false);
        }
        if ($origin_type == 2) {
            // 转载插件不能设置价格
            if ($price > 0) {
                return message('plugin_action.repost_paid_forbidden', false);
            }
            $price = 0.00;
            if (empty($origin_url)) {
                return message('plugin_action.repost_source_required', false);
            }
            if ($origin_url === '') {
                return message('plugin_action.repost_source_invalid', false);
            }
            if (empty($origin_author)) {
                return message('plugin_action.original_author_required', false);
            }
        }

        // 发布类型：必填；立即发布不保存时间；定时发布必须提交完整时间。
        if (!isset($post['publish_type']) || !in_array((string)$post['publish_type'], ['0', '1'], true)) {
            return message('plugin_ui.publish_method_required', false);
        }
        $publish_type = intval($post['publish_type']);
        $publish_time = !empty($post['publish_time']) ? trim($post['publish_time']) : null;
        if ($publish_type === 0) {
            $publish_time = null;
        } else {
            if (empty($publish_time)) {
                return message('plugin_ui.publish_time_required', false);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $publish_time) || strtotime($publish_time) === false) {
                return message('plugin_ui.invalid_time', false);
            }
            $publishTimestamp = strtotime($publish_time);
            if ($publishTimestamp < time() + 10 * 60) {
                return message('plugin_ui.time_too_early', false);
            }
            if ($publishTimestamp > strtotime('+6 months')) {
                return message('plugin_ui.time_too_late', false);
            }
        }

        // XSS过滤转载声明
        if ($related_plugin_id > 0) {
            $relatedPlugin = \think\facade\Db::name('plugin')->where('id', $related_plugin_id)->where('status', 1)->find();
            if (!$relatedPlugin) {
                return message('plugin_action.related_unavailable', false);
            }
            if (!empty($id) && $related_plugin_id == intval($id)) {
                return message('plugin_action.related_self_forbidden', false);
            }
        }

        if (is_array($images)) {
            $images = array_values(array_filter(array_map(static function ($url) {
                return qh_safe_url($url, true);
            }, array_slice($images, 0, 20))));
            $images = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $images = '[]';
        }

        // 文件信息
        $file_path = !empty($post['file_path']) ? $post['file_path'] : '';
        $file_hash = !empty($post['file_hash']) ? $post['file_hash'] : '';
        $file_size = !empty($post['file_size']) ? intval($post['file_size']) : 0;
        $storageDriver = !empty($post['storage_driver']) ? trim($post['storage_driver']) : 'local';
        $packageObjectKey = !empty($post['package_object_key']) ? trim($post['package_object_key']) : '';
        $packageFileName = !empty($post['package_file_name']) ? trim($post['package_file_name']) : '';
        $packageMimeType = !empty($post['package_mime_type']) ? trim($post['package_mime_type']) : '';
        $uploadToken = !empty($post['upload_token']) ? trim((string)$post['upload_token']) : '';
        $iconObjectKey = !empty($post['icon_object_key']) ? trim($post['icon_object_key']) : '';
        $coverObjectKey = !empty($post['cover_object_key']) ? trim($post['cover_object_key']) : '';

        if (!empty($id)) {
            // 编辑 - 只能编辑自己的插件
            $row = $this->model->getInfo($id);
            if (!$row) {
                return message('plugin_action.not_found', false);
            }
            if ($row['user_id'] != $userId) {
                return message('plugin_action.edit_forbidden', false);
            }
            if ($slug !== $row['slug']) {
                return message('plugin_action.slug_immutable', false);
            }
            if ($publish_type == 1) {
                return message('plugin_action.schedule_edit_forbidden', false);
            }
            // 已上架的插件编辑后需要重新审核
            $newStatus = ($row['status'] == 1) ? 0 : $row['status'];

            // Private OSS packages intentionally have no local file_path.
            // Only the actor-bound, server-side token proves a new upload.
            $hasNewPackage = $uploadToken !== '';
            if ($hasNewPackage) {
                try {
                    $package = PluginPackageUploadService::claim($uploadToken, 'user', intval($userId));
                } catch (\Throwable $e) {
                    return message($e->getMessage(), false);
                }
                $file_path = $package['file_path'];
                $file_hash = $package['file_hash'];
                $file_size = $package['file_size'];
                $storageDriver = $package['storage_driver'];
                $packageObjectKey = $package['package_object_key'];
                $packageFileName = $package['package_file_name'];
                $packageMimeType = $package['package_mime_type'];
            } else {
                // Never accept forged hashes or object keys for an existing package.
                $file_path = '';
                $file_hash = '';
                $file_size = 0;
                $storageDriver = (string)($row['storage_driver'] ?? 'local');
                $packageObjectKey = '';
                $packageFileName = '';
                $packageMimeType = '';
            }

            $exists = \think\facade\Db::name('plugin')->where('slug', $slug)->where('id', '<>', $id)->find();
            if ($exists) {
                return message(t('plugin_action.slug_exists', ['slug' => $slug]), false);
            }
            $nameExists = \think\facade\Db::name('plugin')->where('name', $name)->where('id', '<>', $id)->find();
            if ($nameExists) {
                return message(t('plugin_action.name_exists', ['name' => $name]), false);
            }

            $data = [
                'name' => $name,
                'slug' => $slug,
                'category' => $category,
                'version' => $version,
                'author' => $author,
                'author_url' => $author_url,
                'description' => $description,
                'content' => $content,
                'icon' => $icon,
                'cover' => $cover,
                'images' => $images,
                'price' => $price,
                'pay_type' => $pay_type,
                'storage_driver' => $storageDriver,
                'icon_object_key' => $iconObjectKey,
                'cover_object_key' => $coverObjectKey,
                'update_description' => $updateDescription,
                'origin_type' => $origin_type,
                'origin_url' => $origin_url,
                'origin_author' => $origin_author,
                'origin_note' => $origin_note,
                'related_plugin_id' => $related_plugin_id,
                'publish_type' => $publish_type,
                'publish_time' => ($publish_type == 1 ? $publish_time : null),
                'status' => $newStatus,
                'audit_note' => $newStatus == 0 ? t('plugin_action.reaudit_note') : $row['audit_note'],
                'updated_at' => datetime(),
            ];

            if ($hasNewPackage) {
                $versionExists = \think\facade\Db::name('plugin_versions')
                    ->where('plugin_id', intval($id))
                    ->where('version', $version)
                    ->find();
                if ($versionExists) {
                    return message(t('plugin_action.version_exists_retry', ['version' => $version]), false);
                }
                $data['file_path'] = $file_path;
                $data['file_hash'] = $file_hash;
                $data['file_size'] = $file_size;
                $data['package_object_key'] = $packageObjectKey;
                $data['package_file_name'] = $packageFileName;
                $data['package_mime_type'] = $packageMimeType;
            } elseif ($version !== ($row['version'] ?? '')) {
                return message('plugin_action.version_package_required', false);
            }

            try {
                \think\facade\Db::startTrans();
                \think\facade\Db::name('plugin')->where('id', $id)->update($data);
                if ($hasNewPackage) {
                    $versionId = $this->writeVersionRecord(intval($id), $version, [
                        'storage_driver' => $storageDriver,
                        'package_path' => $file_path,
                        'package_object_key' => $packageObjectKey,
                        'package_file_name' => $packageFileName,
                        'package_file_size' => $file_size,
                        'package_mime_type' => $packageMimeType,
                        'package_hash' => $file_hash,
                        'update_description' => $updateDescription,
                        'created_by' => intval($userId),
                    ]);
                    PluginPackageUploadService::consume(intval($package['upload_id']), intval($id));
                    $this->syncPluginResources(intval($id), $versionId, $userId, $icon, $cover, $storageDriver, $post);
                } else {
                    $latestVersion = \think\facade\Db::name('plugin_versions')
                        ->where('plugin_id', intval($id))
                        ->where('version', $version)
                        ->order('id', 'desc')
                        ->find();
                    $this->syncPluginResources(intval($id), $latestVersion ? intval($latestVersion['id']) : 0, $userId, $icon, $cover, $storageDriver, $post);
                }
                \think\facade\Db::commit();
                \think\facade\Cache::tag('QH_Plugin')->clear();
                if ($newStatus == 0 && $row['status'] == 1) {
                    try {
                        $editor = \think\facade\Db::name('user')->where('id', intval($userId))->field('username')->find();
                        $editorName = $editor ? (string)$editor['username'] : t('plugin_action.unknown_user');
                        NotificationModel::add([
                            'user_id' => 0,
                            'title' => t('plugin_action.update_pending_title'),
                            'content' => t('plugin_action.update_pending_content', ['username' => $editorName, 'plugin' => $name]),
                            'type' => 'plugin_new',
                            'link' => '/Plugin/list.html',
                            'variables' => ['username' => $editorName, 'plugin_name' => $name],
                            'is_read' => 0,
                            'created_at' => datetime(),
                        ]);
                    } catch (\Throwable $e) {
                    }
                }
                $msg = ($newStatus == 0 && $row['status'] == 1)
                    ? t('plugin_action.edit_success_reaudit')
                    : t('operation.update_success');
                return message($msg, true);
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                return message(t('plugin_action.edit_failed', ['error' => $e->getMessage()]), false);
            }
        } else {
            // 新增 - 必须上传文件
            if ($uploadToken === '') {
                return message('plugin_ui.upload_package_first', false);
            }
            try {
                $package = PluginPackageUploadService::claim($uploadToken, 'user', intval($userId));
            } catch (\Throwable $e) {
                return message($e->getMessage(), false);
            }
            $file_path = $package['file_path'];
            $file_hash = $package['file_hash'];
            $file_size = $package['file_size'];
            $storageDriver = $package['storage_driver'];
            $packageObjectKey = $package['package_object_key'];
            $packageFileName = $package['package_file_name'];
            $packageMimeType = $package['package_mime_type'];
            $exists = \think\facade\Db::name('plugin')->where('slug', $slug)->find();
            if ($exists) {
                return message(t('plugin_action.slug_exists', ['slug' => $slug]), false);
            }
            $nameExists = \think\facade\Db::name('plugin')->where('name', $name)->find();
            if ($nameExists) {
                return message(t('plugin_action.name_exists', ['name' => $name]), false);
            }

            $data = [
                'user_id' => intval($userId),
                'origin_type' => $origin_type,
                'origin_url' => $origin_url,
                'origin_author' => $origin_author,
                'origin_note' => $origin_note,
                'related_plugin_id' => $related_plugin_id,
                'name' => $name,
                'slug' => $slug,
                'category' => $category,
                'version' => $version,
                'author' => $author,
                'author_url' => $author_url,
                'description' => $description,
                'content' => $content,
                'icon' => $icon,
                'cover' => $cover,
                'images' => $images,
                'price' => $price,
                'pay_type' => $pay_type,
                'file_path' => $file_path,
                'file_hash' => $file_hash,
                'file_size' => $file_size,
                'storage_driver' => $storageDriver,
                'package_object_key' => $packageObjectKey,
                'package_file_name' => $packageFileName,
                'package_mime_type' => $packageMimeType,
                'icon_object_key' => $iconObjectKey,
                'cover_object_key' => $coverObjectKey,
                'update_description' => $updateDescription,
                'publish_type' => $publish_type,
                'publish_time' => ($publish_type == 1 ? $publish_time : null),
                'status' => 0,
                'audit_note' => '',
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ];

            try {
                \think\facade\Db::startTrans();
                $pluginId = \think\facade\Db::name('plugin')->insertGetId($data);
                $versionId = $this->writeVersionRecord($pluginId, $version, [
                    'storage_driver' => $storageDriver,
                    'package_path' => $file_path,
                    'package_object_key' => $packageObjectKey,
                    'package_file_name' => $packageFileName,
                    'package_file_size' => $file_size,
                    'package_mime_type' => $packageMimeType,
                    'package_hash' => $file_hash,
                    'update_description' => $updateDescription,
                    'created_by' => intval($userId),
                ]);
                PluginPackageUploadService::consume(intval($package['upload_id']), intval($pluginId));
                $this->syncPluginResources($pluginId, $versionId, $userId, $icon, $cover, $storageDriver, $post);
                \think\facade\Db::commit();
                \think\facade\Cache::tag('QH_Plugin')->clear();

                // 将临时图片移动到正式目录
                if (!empty($content)) {
                    $movedContent = move_temp_images_in_content($content);
                    if ($movedContent !== $content) {
                        \think\facade\Db::name('plugin')->where('id', $pluginId)->update(['content' => $movedContent]);
                    }
                }

                // 通知管理员有新插件待审核
                try {
                    $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
                    $username = $user ? $user['username'] : t('plugin_action.unknown_user');
                    NotificationModel::add([
                        'user_id'    => 0,
                        'title'      => t('plugin_action.new_pending_title'),
                        'content'    => t('plugin_action.new_pending_content', ['username' => $username, 'plugin' => $name]),
                        'type'       => 'plugin_new',
                        'link'       => '/Plugin/list.html',
                        'variables'  => ['username' => $username, 'plugin_name' => $name],
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                } catch (\Throwable $e) {}

                return message('plugin_action.publish_success_pending', true);
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                return message(t('plugin_action.publish_failed', ['error' => $e->getMessage()]), false);
            }
        }
    }

    /**
     * 上传插件文件
     */
    public function uploadFile($userId)
    {
        try {
            $file = request()->file('file');

            try {
                validate([
                    'File' => [
                        'fileSize' => 200 * 1024 * 1024,
                        'fileExt' => 'zip',
                        'fileMime' => 'application/zip,application/x-zip-compressed,application/octet-stream',
                    ]
                ])->check(['File' => $file]);
            } catch (\Exception $e) {
                return message(t('plugin_action.file_validation_failed', ['error' => $e->getMessage()]), false, ['status' => 0]);
            }

            // 检查原始文件名是否含中文
            $originalName = $file->getOriginalName();
            if (preg_match('/[\x{4e00}-\x{9fff}]/u', $originalName)) {
                return message('plugin_action.archive_name_ascii', false, ['status' => 0]);
            }

            // 尝试解析压缩包内的 conf.json 和 icon.png（支持根目录和单层子目录）
            $autoData = [];
            try {
                $zipProblem = \app\common\service\SafeZipService::validate($file->getPathname(), [], 10000, 1073741824);
                if ($zipProblem !== null) {
                    throw new Exception($zipProblem);
                }
                $zip = new \ZipArchive();
                if ($zip->open($file->getPathname()) === true) {
                    $confContent = false;
                    $iconContent = false;

                    // 收集所有文件名，找 conf.json 和 icon.png
                    $entries = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        if ($name === false) continue;
                        $name = rtrim($name, '/');
                        $entries[] = $name;
                    }

                    // 匹配 conf.json（大小写不敏感，支持根目录和子目录）
                    foreach ($entries as $f) {
                        $base = basename($f);
                        if (strcasecmp($base, 'conf.json') === 0) {
                            $stat = $zip->statName($f);
                            if ((int)($stat['size'] ?? 0) <= 1024 * 1024) {
                                $confContent = $zip->getFromName($f);
                            }
                            break;
                        }
                    }

                    // 匹配 icon.png（大小写不敏感）
                    foreach ($entries as $f) {
                        $base = basename($f);
                        if (strcasecmp($base, 'icon.png') === 0) {
                            $stat = $zip->statName($f);
                            if ((int)($stat['size'] ?? 0) <= 5 * 1024 * 1024) {
                                $iconContent = $zip->getFromName($f);
                            }
                            break;
                        }
                    }

                    // 解析 conf.json
                    if ($confContent !== false) {
                        $conf = json_decode($confContent, true);
                        if (is_array($conf)) {
                            if (!empty($conf['name']))    $autoData['name'] = trim($conf['name']);
                            if (!empty($conf['brief']))   $autoData['description'] = trim($conf['brief']);
                            if (!empty($conf['version'])) $autoData['version'] = trim($conf['version']);
                        }
                    }
                    // 提取 icon.png
                    if ($iconContent !== false) {
                        $iconMeta = (new PluginStorageService())->storeBytes($iconContent, 'icon', 'icon.png', 'image/png');
                        $autoData['icon'] = $iconMeta['url'];
                        $autoData['icon_object_key'] = $iconMeta['object_key'];
                        $autoData['icon_file_name'] = $iconMeta['file_name'];
                        $autoData['icon_file_size'] = $iconMeta['file_size'];
                        $autoData['icon_mime_type'] = $iconMeta['mime_type'];
                        $autoData['icon_storage_driver'] = $iconMeta['storage_driver'];
                    }
                    $zip->close();
                }
            } catch (\Throwable $e) {
                // 解析失败不报错，让用户手动填写
            }

            $stored = (new PluginStorageService())->storeUploadedFile($file, 'package', ['zip'], 200 * 1024 * 1024);
            $uploadToken = PluginPackageUploadService::issue('user', intval($userId), $stored);

            return message('plugin_action.upload_success', true, [
                'status' => 1,
                'file_path' => $stored['path'],
                'file_hash' => $stored['file_hash'],
                'file_size' => $stored['file_size'],
                'original_name' => $originalName,
                'storage_driver' => $stored['storage_driver'],
                'package_object_key' => $stored['object_key'],
                'package_file_name' => $stored['file_name'],
                'package_mime_type' => $stored['mime_type'],
                'upload_token' => $uploadToken,
                'auto' => $autoData,
            ]);
        } catch (\Exception $e) {
            return message(t('plugin_action.upload_failed', ['error' => $e->getMessage()]), false, ['status' => 0]);
        }
    }

    /**
     * 开发者回复评论
     */
    public function replyComment($userId)
    {
        $post = request()->post();
        $comment_id = !empty($post['comment_id']) ? intval($post['comment_id']) : 0;
        $reply_content = !empty($post['reply_content']) ? trim($post['reply_content']) : '';

        if (empty($comment_id)) {
            return message('plugin_action.comment_id_required', false);
        }
        if (empty($reply_content)) {
            return message('plugin_action.reply_required', false);
        }
        if (mb_strlen($reply_content) > 500) {
            return message('plugin_action.reply_too_long', false);
        }

        // XSS过滤
        $reply_content = htmlspecialchars($reply_content, ENT_QUOTES, 'UTF-8');

        // 查询评论
        $comment = \think\facade\Db::name('plugin_comment')->where('id', $comment_id)->find();
        if (!$comment) {
            return message('plugin_action.comment_not_found', false);
        }

        // 查询插件，确认是自己的插件
        $plugin = \think\facade\Db::name('plugin')->where('id', $comment['plugin_id'])->find();
        if (!$plugin) {
            return message('plugin_action.not_found', false);
        }
        if ($plugin['user_id'] != $userId) {
            return message('plugin_action.reply_own_only', false);
        }

        // 更新回复
        try {
            \think\facade\Db::name('plugin_comment')->where('id', $comment_id)->update([
                'reply_content' => $reply_content,
                'reply_at' => datetime(),
            ]);

            // 通知评论者收到开发者回复
            try {
                if (!empty($comment['user_id'])) {
                    NotificationModel::add([
                        'user_id'    => intval($comment['user_id']),
                        'title'      => t('plugin_action.reply_notice_title'),
                        'content'    => t('plugin_action.reply_notice_content', ['plugin' => $plugin['name']]),
                        'type'       => 'comment_reply',
                        'link'       => '/UserPlugin/detail.html?id=' . $comment['plugin_id'],
                        'variables'  => ['plugin_name' => $plugin['name']],
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return message('plugin_action.reply_success', true);
        } catch (\Exception $e) {
            return message(t('plugin_action.reply_failed', ['error' => $e->getMessage()]), false);
        }
    }

    /**
     * 获取我发表的所有评论（跨所有插件）
     */
    public function getMyAllComments($userId)
    {
        $post = request()->post();
        $limit = qh_page_limit($post['limit'] ?? null, 10);
        $current_page = qh_page_number($post['current_page'] ?? null);
        $text = !empty($post['text']) ? trim($post['text']) : '';
        $status = isset($post['status']) && $post['status'] !== '' ? intval($post['status']) : null;

        $where = [['c.user_id', '=', intval($userId)]];

        if (!empty($text)) {
            $text = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $text);
            $where[] = ['c.content|p.name', 'like', '%' . $text . '%'];
        }

        if ($status !== null) {
            $where[] = ['c.status', '=', $status];
        }

        $list = \think\facade\Db::name('plugin_comment')
            ->alias('c')
            ->leftJoin('plugin p', 'c.plugin_id = p.id')
            ->leftJoin('user u', 'c.user_id = u.id')
            ->where($where)
            ->field('c.*, p.name as plugin_name, u.username')
            ->order('c.id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page,
            ]);

        return $list;
    }

    /**
     * 获取我的插件的评论列表（别人对我插件的评论）
     */
    public function getMyPluginComments($userId)
    {
        $post = request()->post();
        $limit = qh_page_limit($post['limit'] ?? null, 10);
        $current_page = qh_page_number($post['current_page'] ?? null);
        $plugin_id = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;
        $text = !empty($post['text']) ? trim($post['text']) : '';
        $status = isset($post['status']) && $post['status'] !== '' ? intval($post['status']) : null;

        // 获取我的所有插件ID
        $myPluginIds = \think\facade\Db::name('plugin')
            ->where('user_id', intval($userId))
            ->column('id');

        if (empty($myPluginIds)) {
            return ['total' => 0, 'data' => []];
        }

        $where = [['c.plugin_id', 'in', $myPluginIds]];

        if ($plugin_id > 0) {
            $where[] = ['c.plugin_id', '=', $plugin_id];
        }

        if (!empty($text)) {
            $text = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $text);
            $where[] = ['c.content|u.username', 'like', '%' . $text . '%'];
        }

        if ($status !== null) {
            $where[] = ['c.status', '=', $status];
        }

        $list = \think\facade\Db::name('plugin_comment')
            ->alias('c')
            ->leftJoin('plugin p', 'c.plugin_id = p.id')
            ->leftJoin('user u', 'c.user_id = u.id')
            ->where($where)
            ->field('c.*, p.name as plugin_name, u.username')
            ->order('c.id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page,
            ]);

        return $list;
    }

    /**
     * 提交评论和评分
     */
    public function submitComment($userId)
    {
        $post = request()->post();
        $plugin_id = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;
        $content = !empty($post['content']) ? trim($post['content']) : '';
        $rating = !empty($post['rating']) ? intval($post['rating']) : 0;

        // 验证
        if (empty($plugin_id)) {
            return message('plugin_action.id_required', false);
        }
        if (empty($content)) {
            return message('plugin_action.comment_required', false);
        }
        if (mb_strlen($content) < 10) {
            return message('plugin_detail.comment_too_short', false);
        }
        if (mb_strlen($content) > 500) {
            return message('plugin_detail.comment_too_long', false);
        }
        if ($rating < 1 || $rating > 5) {
            return message('plugin_action.rating_invalid', false);
        }

        // XSS过滤
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // 查询插件
        $plugin = \think\facade\Db::name('plugin')->where('id', $plugin_id)->find();
        if (!$plugin) {
            return message('plugin_action.not_found', false);
        }
        if ($plugin['status'] != 1) {
            return message('plugin_action.comment_unpublished', false);
        }
        // 开发者不能评论自己的插件
        if (!empty($plugin['user_id']) && intval($plugin['user_id']) == intval($userId)) {
            return message('plugin_action.comment_self_forbidden', false);
        }

        // 获取用户的app_id
        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            return message('plugin_action.user_info_error', false);
        }
        $developerId = intval($plugin['user_id'] ?? 0);
        if ($developerId > 0) {
            $relationReason = RebateRiskService::relatedAccountReason(intval($userId), $developerId);
            if ($relationReason !== '') {
                return message('plugin_action.developer_purchase_forbidden', false);
            }
        }
        $app_id = intval($user['appid']);
        if (!$this->hasPurchasedOrDownloaded($plugin_id, intval($userId), $app_id)) {
            return message('plugin_action.purchase_before_comment', false);
        }

        // 检查是否已评论
        $existing = \think\facade\Db::name('plugin_comment')
            ->where('plugin_id', $plugin_id)
            ->where('user_id', intval($userId))
            ->where('app_id', $app_id)
            ->find();
        if ($existing) {
            return message('plugin_action.already_commented', false);
        }

        // 插入评论
        try {
            \think\facade\Db::name('plugin_comment')->insert([
                'plugin_id' => $plugin_id,
                'user_id' => intval($userId),
                'app_id' => $app_id,
                'content' => $content,
                'rating' => $rating,
                'status' => 0, // 待审核
                'ip' => get_client_ip(),
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ]);

            // 更新插件统计（即使是待审核状态，也先更新统计，审核通过后会重新计算）
            $pluginModel = new \app\admin\model\PluginModel();
            $pluginModel->updateCommentCount($plugin_id);
            $pluginModel->updateRatingStats($plugin_id);

            // 通知插件开发者有新评论
            try {
                if (!empty($plugin['user_id']) && intval($plugin['user_id']) != intval($userId)) {
                    NotificationModel::add([
                        'user_id'    => intval($plugin['user_id']),
                        'title'      => t('plugin_action.new_comment_title'),
                        'content'    => t('plugin_action.new_comment_content', ['plugin' => $plugin['name'], 'rating' => $rating]),
                        'type'       => 'plugin_comment',
                        'link'       => '/UserPlugin/comments.html',
                        'variables'  => [
                            'plugin_name' => $plugin['name'],
                            'rating' => $rating,
                            'comment_content' => $content,
                        ],
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
                $commenter = \think\facade\Db::name('user')->where('id', intval($userId))->field('username')->find();
                $commenterName = $commenter ? (string)$commenter['username'] : t('plugin_action.unknown_user');
                NotificationModel::add([
                    'user_id' => 0,
                    'title' => t('plugin_action.comment_pending_title'),
                    'content' => t('plugin_action.comment_pending_content', [
                        'username' => $commenterName,
                        'plugin' => $plugin['name'],
                        'rating' => $rating,
                    ]),
                    'type' => 'plugin_comment_pending',
                    'link' => '/PluginComment/list.html',
                    'variables' => [
                        'username' => $commenterName,
                        'plugin_name' => $plugin['name'],
                        'rating' => $rating,
                        'comment_content' => $content,
                    ],
                    'is_read' => 0,
                    'created_at' => datetime(),
                ]);
            } catch (\Throwable $e) {}

            return message('plugin_action.comment_submitted', true);
        } catch (\Exception $e) {
            return message(t('plugin_action.comment_submit_failed', ['error' => $e->getMessage()]), false);
        }
    }

    /**
     * 获取插件的评论列表（仅已审核通过的）
     */
    public function getComments($pluginId)
    {
        $post = request()->post();
        $limit = qh_page_limit($post['limit'] ?? null, 10);
        $current_page = qh_page_number($post['current_page'] ?? null);

        if (empty($pluginId)) {
            return ['total' => 0, 'data' => []];
        }

        $list = \think\facade\Db::name('plugin_comment')
            ->alias('c')
            ->leftJoin('user u', 'c.user_id = u.id')
            ->where('c.plugin_id', intval($pluginId))
            ->where('c.status', 1) // 只显示已审核通过的
            ->field('c.*, u.username')
            ->order('c.id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page,
            ]);

        return $list;
    }

    /**
     * 余额购买插件
     */
    public function purchase($userId)
    {
        $post = request()->post();
        $plugin_id = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;

        if (empty($plugin_id)) {
            return message('plugin_action.id_required', false);
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $plugin_id)->where('status', 1)->find();
        if (!$plugin) {
            return message('plugin_action.not_published', false);
        }
        if ($plugin['price'] <= 0) {
            return message('plugin_action.free_no_purchase', false);
        }

        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            return message('plugin_action.user_info_error', false);
        }
        $developerId = intval($plugin['user_id'] ?? 0);
        $app_id = intval($user['appid']);

        // 检查是否已购买
        $purchase = \think\facade\Db::name('plugin_purchase')
            ->where('plugin_id', $plugin_id)
            ->where('user_id', intval($userId))
            ->where('app_id', $app_id)
            ->find();
        if ($purchase) {
            return message('plugin_action.already_purchased', false);
        }

        // 检查支付方式
        $price = qh_money_format($plugin['price']);
        $priceCents = qh_money_to_cents($price);
        $payType = !empty($plugin['pay_type']) ? $plugin['pay_type'] : 'balance';

        if ($payType === 'points') {
            $userPoints = intval($user['integral']);
            $pointsNeeded = intval($price);
            if ($userPoints < $pointsNeeded) {
                return message(t('plugin_action.points_insufficient', ['current' => $userPoints, 'required' => $pointsNeeded]), false);
            }
        } else {
            $balance = qh_money_format($user['balance']);
            if (qh_money_to_cents($balance) < $priceCents) {
                return message(t('plugin_action.balance_insufficient', ['current' => $balance, 'required' => $price]), false);
            }
        }

        // 事务处理
        \think\facade\Db::startTrans();
        try {
            // Serialize purchases for the same buyer so concurrent purchases
            // cannot overdraw balance/points or bypass the duplicate check.
            $lockedUser = \think\facade\Db::name('user')
                ->where('id', intval($userId))
                ->lock(true)
                ->find();
            if (!$lockedUser) {
                throw new \RuntimeException(t('plugin_action.user_info_error'));
            }
            if ($developerId > 0 && RebateRiskService::relatedAccountReason(intval($userId), $developerId) !== '') {
                throw new \RuntimeException(t('plugin_action.developer_purchase_forbidden'));
            }
            $existingPurchase = \think\facade\Db::name('plugin_purchase')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', intval($userId))
                ->where('app_id', $app_id)
                ->lock(true)
                ->find();
            if ($existingPurchase) {
                throw new \RuntimeException(t('plugin_action.already_purchased'));
            }

            // 计算平台抽成；积分支付始终免抽成，订单保存当次配置快照。
            $settlement = PluginCommissionService::settle($price, $payType);
            $commissionRate = $settlement['rate'];
            $commissionAmount = $settlement['amount'];
            $developerIncome = $settlement['developer_income'];

            // 扣减买家余额或积分
            if ($payType === 'points') {
                $deducted = \think\facade\Db::name('user')
                    ->where('id', intval($userId))
                    ->where('integral', '>=', intval($price))
                    ->dec('integral', intval($price))
                    ->update();
            } else {
                $deducted = \think\facade\Db::name('user')
                    ->where('id', intval($userId))
                    ->where('balance', '>=', $price)
                    ->dec('balance', $price)
                    ->update();
            }
            if ($deducted !== 1) {
                throw new \RuntimeException(t($payType === 'points'
                    ? 'plugin_action.points_insufficient_short'
                    : 'plugin_action.balance_insufficient_short'));
            }

            // 创建订单
            $orderNo = 'PL' . date('YmdHis') . strtoupper(bin2hex(random_bytes(6)));
            $orderId = \think\facade\Db::name('plugin_order')->insertGetId([
                'order_no' => $orderNo,
                'plugin_id' => $plugin_id,
                'plugin_name' => $plugin['name'],
                'plugin_version' => $plugin['version'],
                'user_id' => intval($userId),
                'app_id' => $app_id,
                'price' => $price,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'developer_income' => $developerIncome,
                'pay_type' => $payType === 'points' ? 'points' : 'balance',
                'status' => 1,
                'paid_at' => datetime(),
                'ip' => get_client_ip(),
                'created_at' => datetime(),
                'updated_at' => datetime(),
            ]);

            // 创建购买记录
            \think\facade\Db::name('plugin_purchase')->insert([
                'plugin_id' => $plugin_id,
                'user_id' => intval($userId),
                'app_id' => $app_id,
                'order_id' => $orderId,
                'price' => $price,
                'created_at' => datetime(),
            ]);

            // 记录买家消费日志
            if ($payType === 'points') {
                \app\common\model\PointLogModel::add(
                    intval($userId),
                    'deduct_plugin',
                    -intval($price),
                    t('plugin_action.points_purchase_log', ['plugin' => $plugin['name']]),
                    'plugin_order_buy',
                    $orderId
                );
            } else {
                \app\common\model\BalanceLogModel::add(
                    intval($userId),
                    'deduct_plugin',
                    -$price,
                    t('plugin_action.balance_purchase_log', ['plugin' => $plugin['name']]),
                    $orderId
                );
                \app\common\model\PointLogModel::grantConsumptionPoints(
                    intval($userId),
                    $price,
                    'plugin_order_consume',
                    $orderId,
                    t('plugin_action.purchase_points_reward_log', [
                        'points' => intval(floor($price)),
                        'plugin' => $plugin['name'],
                    ]),
                    $orderId
                );
            }

            // 给插件开发者打款
            if ($developerIncome > 0 && !empty($plugin['user_id'])) {
                if ($developerId != intval($userId)) {
                    if ($payType === 'points') {
                        \think\facade\Db::name('user')->where('id', $developerId)->inc('integral', intval($developerIncome))->update();
                        \app\common\model\PointLogModel::add(
                            $developerId,
                            'plugin_income',
                            intval($developerIncome),
                            t('plugin_action.points_income_log', ['plugin' => $plugin['name']]),
                            'plugin_order_income',
                            $orderId
                        );
                    } else {
                        if (!WithdrawableBalanceService::credit(
                            $developerId,
                            $developerIncome,
                            'plugin_income',
                            t('plugin_action.sales_income_log', [
                                'plugin' => $plugin['name'],
                                'summary' => $this->commissionSummary($settlement),
                            ]),
                            'plugin_order_income',
                            $orderId
                        )) {
                            throw new \RuntimeException(t('plugin_action.publisher_income_failed'));
                        }
                    }
                }
            }

            \think\facade\Db::commit();

            // 通知插件开发者有新购买
            try {
                if (!empty($plugin['user_id']) && intval($plugin['user_id']) != intval($userId)) {
                    $buyerInfo = \think\facade\Db::name('user')->where('id', intval($userId))->find();
                    $buyerName = $buyerInfo ? $buyerInfo['username'] : t('plugin_action.unknown_user');
                    $incomeText = $payType === 'points'
                        ? t('plugin_action.points_income', ['amount' => intval($developerIncome)])
                        : t('plugin_action.balance_income', [
                            'amount' => $developerIncome,
                            'summary' => $this->commissionSummary($settlement),
                        ]);
                    NotificationModel::add([
                        'user_id'    => intval($plugin['user_id']),
                        'title'      => t('plugin_action.sale_notice_title'),
                        'content'    => t('plugin_action.sale_notice_content', [
                            'username' => $buyerName,
                            'plugin' => $plugin['name'],
                            'income' => $incomeText,
                        ]),
                        'type'       => 'plugin_purchase',
                        'link'       => '/UserPlugin/list.html',
                        'variables'  => [
                            'buyer_name' => $buyerName,
                            'plugin_name' => $plugin['name'],
                            'income' => $incomeText,
                        ],
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return message('plugin_action.purchase_success', true);
        } catch (\Exception $e) {
            \think\facade\Db::rollback();
            return message(t('plugin_action.purchase_failed', ['error' => $e->getMessage()]), false);
        }
    }

    /**
     * 生成下载凭证
     */
    public function getDownloadToken($userId)
    {
        $post = request()->post();
        $plugin_id = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;

        if (empty($plugin_id)) {
            return message('plugin_action.id_required', false);
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $plugin_id)->where('status', 1)->find();
        if (!$plugin) {
            return message('plugin_action.not_published', false);
        }

        try {
            (new PluginStorageService())->assertValidPackageRecord($plugin);
        } catch (\Throwable $e) {
            return message('plugin_action.file_not_found', false);
        }

        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            return message('plugin_action.user_info_error', false);
        }
        $app_id = intval($user['appid']);
        $orderId = 0;
        $relatedNotice = '';

        if (!empty($plugin['related_plugin_id'])) {
            $relatedPlugin = \think\facade\Db::name('plugin')
                ->where('id', intval($plugin['related_plugin_id']))
                ->where('status', 1)
                ->find();
            if (!$relatedPlugin) {
                return message('plugin_action.related_download_unavailable', false);
            }

            $relatedNotice = t('plugin_action.related_notice', ['plugin' => $relatedPlugin['name']]);
            $isRelatedOwner = !empty($relatedPlugin['user_id']) && intval($relatedPlugin['user_id']) == intval($userId);
            if (floatval($relatedPlugin['price']) > 0 && !$isRelatedOwner) {
                $relatedPurchase = \think\facade\Db::name('plugin_purchase')
                    ->where('plugin_id', intval($relatedPlugin['id']))
                    ->where('user_id', intval($userId))
                    ->where('app_id', $app_id)
                    ->find();
                if (!$relatedPurchase) {
                    return message(t('plugin_action.related_purchase_required', ['name' => $relatedPlugin['name']]), false, [
                        'related_plugin_id' => intval($relatedPlugin['id']),
                        'related_plugin_name' => $relatedPlugin['name'],
                    ]);
                }
            }
        }

        // 付费插件检查购买记录（自己发布的插件可以直接下载）
        if ($plugin['price'] > 0 && (!isset($plugin['user_id']) || $plugin['user_id'] != intval($userId))) {
            $purchase = \think\facade\Db::name('plugin_purchase')
                ->where('plugin_id', $plugin_id)
                ->where('user_id', intval($userId))
                ->where('app_id', $app_id)
                ->find();
            if (!$purchase) {
                return message('plugin_action.not_purchased', false);
            }
            $orderId = $purchase['order_id'];
        }

        // 生成临时下载凭证
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 300);

        \think\facade\Db::name('plugin_download_token')->insert([
            'token' => $token,
            'plugin_id' => $plugin_id,
            'user_id' => intval($userId),
            'app_id' => $app_id,
            'order_id' => $orderId,
            'ip' => get_client_ip(),
            'used' => 0,
            'expires_at' => $expiresAt,
            'created_at' => datetime(),
        ]);

        return message('plugin_action.get_success', true, ['token' => $token, 'related_notice' => $relatedNotice]);
    }

    /**
     * 获取指定插件的购买记录（开发者查看）
     */
    public function getPluginPurchases($userId)
    {
        $post = request()->post();
        $plugin_id = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;
        $limit = qh_page_limit($post['limit'] ?? null, 20);
        $current_page = qh_page_number($post['current_page'] ?? null);

        if (empty($plugin_id)) {
            return ['total' => 0, 'data' => []];
        }

        // 确认是该用户的插件
        $plugin = \think\facade\Db::name('plugin')->where('id', $plugin_id)->where('user_id', intval($userId))->find();
        if (!$plugin) {
            return ['total' => 0, 'data' => []];
        }

        return \think\facade\Db::name('plugin_purchase')
            ->alias('pp')
            ->leftJoin('user u', 'pp.user_id = u.id')
            ->leftJoin('plugin_order o', 'pp.order_id = o.id')
            ->where('pp.plugin_id', $plugin_id)
            ->field('pp.*, u.username, o.order_no, o.price as paid_price, o.pay_type as order_pay_type, o.paid_at')
            ->order('pp.id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $current_page]);
    }

    /**
     * 获取我的购买和下载记录
     */
    public function getMyPurchases($userId)
    {
        $post = request()->post();
        $limit = qh_page_limit($post['limit'] ?? null, 10);
        $current_page = qh_page_number($post['current_page'] ?? null);
        $type = !empty($post['type']) ? $post['type'] : 'purchase';
        $text = !empty($post['text']) ? trim($post['text']) : '';

        if ($type === 'download') {
            $query = \think\facade\Db::name('plugin_download')
                ->alias('d')
                ->leftJoin('plugin p', 'd.plugin_id = p.id')
                ->where('d.user_id', intval($userId))
                ->field('d.*, p.name as plugin_name, p.slug, p.version as plugin_version, p.author, p.icon, p.price, p.pay_type, p.status as plugin_status');
        } else {
            $query = \think\facade\Db::name('plugin_purchase')
                ->alias('pp')
                ->leftJoin('plugin p', 'pp.plugin_id = p.id')
                ->leftJoin('plugin_order o', 'pp.order_id = o.id')
                ->where('pp.user_id', intval($userId))
                ->field('pp.*, o.order_no, o.price as order_price, o.pay_type as order_pay_type, o.paid_at, p.name as plugin_name, p.slug, p.version as plugin_version, p.author, p.icon, p.price as plugin_price, p.pay_type, p.status as plugin_status');
        }

        if (!empty($text)) {
            $text = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $text);
            $query->where('p.name', 'like', '%' . $text . '%');
        }

        return $query->order($type === 'download' ? 'd.id' : 'pp.id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page,
            ]);
    }

    /**
     * 下载插件文件
     */
    public function download($userId)
    {
        $token = input('get.token', '');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new Exception(t('plugin_action.credential_format_error'));
        }

        $tokenRecord = \think\facade\Db::name('plugin_download_token')->where('token', $token)->find();
        if (!$tokenRecord) {
            throw new Exception(t('plugin_action.credential_invalid'));
        }
        if ($tokenRecord['used'] == 1) {
            throw new Exception(t('plugin_action.credential_used'));
        }
        if (strtotime($tokenRecord['expires_at']) < time()) {
            throw new Exception(t('plugin_action.credential_expired'));
        }
        if ($tokenRecord['ip'] !== get_client_ip()) {
            throw new Exception(t('plugin_action.ip_mismatch'));
        }
        if (intval($tokenRecord['user_id']) !== intval($userId)) {
            throw new Exception(t('plugin_action.credential_account_mismatch'));
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $tokenRecord['plugin_id'])->where('status', 1)->find();
        if (!$plugin) {
            throw new Exception(t('plugin_action.unavailable'));
        }
        $storage = new PluginStorageService();
        $filePath = $storage->getDownloadFile($plugin);
        $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$plugin['slug'] . '_v' . (string)$plugin['version']) . '.zip';

        \think\facade\Db::startTrans();
        try {
            $claimed = \think\facade\Db::name('plugin_download_token')
                ->where('id', $tokenRecord['id'])
                ->where('used', 0)
                ->where('expires_at', '>=', datetime())
                ->update(['used' => 1, 'used_at' => datetime()]);
            if ($claimed !== 1) {
                throw new Exception(t('plugin_action.credential_used_or_expired'));
            }
            \think\facade\Db::name('plugin_download')->insert([
                'plugin_id' => $plugin['id'],
                'plugin_version' => $plugin['version'],
                'user_id' => $tokenRecord['user_id'],
                'app_id' => $tokenRecord['app_id'],
                'order_id' => $tokenRecord['order_id'],
                'ip' => get_client_ip(),
                'created_at' => datetime(),
            ]);
            \think\facade\Db::name('plugin')->where('id', $plugin['id'])->inc('download_count')->update();
            \think\facade\Db::commit();
        } catch (\Throwable $e) {
            \think\facade\Db::rollback();
            throw $e;
        }

        if (preg_match('#^https?://#i', $filePath)) {
            $safeUrl = qh_safe_url($filePath, false);
            if ($safeUrl === '' || strtolower((string)parse_url($safeUrl, PHP_URL_SCHEME)) !== 'https') {
                throw new Exception(t('plugin_action.unsafe_download_url'));
            }
            header('Location: ' . $safeUrl, true, 302);
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        $storage->deleteTemporaryDownloadFile($filePath);
        exit;
    }

    public function uploadResource(string $type)
    {
        $type = in_array($type, ['icon', 'cover'], true) ? $type : 'icon';
        try {
            $file = request()->file('file');
            $stored = (new PluginStorageService())->storeUploadedFile($file, $type, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            return message('plugin_action.upload_success', true, [
                'status' => 1,
                'path' => $stored['url'],
                'url' => $stored['url'],
                'src' => $stored['url'],
                'storage_driver' => $stored['storage_driver'],
                'object_key' => $stored['object_key'],
                'file_name' => $stored['file_name'],
                'file_size' => $stored['file_size'],
                'mime_type' => $stored['mime_type'],
            ]);
        } catch (\Throwable $e) {
            return message(t('plugin_action.upload_failed', ['error' => $e->getMessage()]), false, ['status' => 0]);
        }
    }

    public function getVersions(int $pluginId, int $userId = 0): array
    {
        if ($pluginId <= 0) {
            throw new Exception(t('plugin_action.id_required'));
        }
        $plugin = \think\facade\Db::name('plugin')->where('id', $pluginId)->find();
        if (!$plugin) {
            throw new Exception(t('plugin_action.not_found'));
        }
        if (intval($plugin['status'] ?? 0) !== 1 && intval($plugin['user_id'] ?? 0) !== intval($userId)) {
            throw new Exception(t('plugin_action.version_view_forbidden'));
        }

        $rows = \think\facade\Db::name('plugin_versions')
            ->where('plugin_id', $pluginId)
            ->order('created_at', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $this->decorateVersionAuthor($row);
            $row['is_latest'] = isset($plugin['version']) && $row['version'] === $plugin['version'];
            $hasFile = !empty($row['package_object_key']) || (!empty($row['package_path']) && file_exists($row['package_path']));
            $row['can_download'] = $hasFile && $this->canAccessPlugin($plugin, $userId);
            $row['update_description'] = !empty($row['update_description'])
                ? $row['update_description']
                : t('plugin_action.no_update_description');
        }
        unset($row);

        return $rows;
    }

    public function downloadVersion(int $userId)
    {
        $pluginId = input('get.plugin_id', 0, 'intval');
        $versionId = input('get.version_id', 0, 'intval');
        [$plugin, $version] = $this->getDownloadableVersion($pluginId, $versionId, $userId);

        $storage = new PluginStorageService();
        $downloadPath = $storage->getDownloadFile($version);
        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        $purchase = $user ? \think\facade\Db::name('plugin_purchase')
            ->where('plugin_id', $pluginId)
            ->where('user_id', intval($userId))
            ->where('app_id', intval($user['appid']))
            ->find() : null;
        \think\facade\Db::name('plugin_download')->insert([
            'plugin_id' => $pluginId,
            'plugin_version' => $version['version'],
            'user_id' => intval($userId),
            'app_id' => $user ? intval($user['appid']) : 0,
            'order_id' => $purchase['order_id'] ?? 0,
            'ip' => get_client_ip(),
            'created_at' => datetime(),
        ]);
        \think\facade\Db::name('plugin')->where('id', $pluginId)->inc('download_count')->update();

        if (preg_match('#^https?://#i', $downloadPath)) {
            $safeUrl = qh_safe_url($downloadPath, false);
            if ($safeUrl === '' || strtolower((string)parse_url($safeUrl, PHP_URL_SCHEME)) !== 'https') {
                throw new Exception(t('plugin_action.unsafe_download_url'));
            }
            header('Location: ' . $safeUrl, true, 302);
            exit;
        }

        $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$plugin['slug'] . '_v' . (string)$version['version']) . '.zip';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($downloadPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($downloadPath);
        $storage->deleteTemporaryDownloadFile($downloadPath);
        exit;
    }

    public function checkVersionDownload(int $userId): array
    {
        $pluginId = input('get.plugin_id', input('post.plugin_id', 0, 'intval'), 'intval');
        $versionId = input('get.version_id', input('post.version_id', 0, 'intval'), 'intval');
        $this->getDownloadableVersion($pluginId, $versionId, $userId);
        return message('plugin_action.download_allowed', true);
    }

    private function getDownloadableVersion(int $pluginId, int $versionId, int $userId): array
    {
        if ($pluginId <= 0 || $versionId <= 0) {
            throw new Exception(t('plugin_action.version_params_invalid'));
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $pluginId)->where('status', 1)->find();
        if (!$plugin) {
            throw new Exception(t('plugin_action.not_published'));
        }
        $this->assertCanDownload($plugin, $userId);

        $version = \think\facade\Db::name('plugin_versions')
            ->where('id', $versionId)
            ->where('plugin_id', $pluginId)
            ->find();
        if (!$version) {
            throw new Exception(t('plugin_detail.version_missing'));
        }

        (new PluginStorageService())->assertValidPackageRecord($version);
        return [$plugin, $version];
    }

    public function enrichPluginDetail(&$plugin): void
    {
        if (!$plugin) {
            return;
        }
        $this->decorateAuthor($plugin);
        $plugin['iconUrl'] = $plugin['icon'] ?? '';
        $plugin['coverUrl'] = $plugin['cover'] ?? '';
        $plugin['latestUpdateDescription'] = !empty($plugin['update_description'])
            ? $plugin['update_description']
            : t('plugin_action.no_update_description');
        $resources = \think\facade\Db::name('plugin_resources')
            ->where('plugin_id', intval($plugin['id']))
            ->whereIn('resource_type', ['icon', 'cover'])
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        foreach ($resources as $item) {
            $url = '';
            if (($item['storage_driver'] ?? 'local') === 'oss' && !empty($item['object_key'])) {
                try {
                    $url = (new PluginStorageService())->getMediaUrl((string)$item['object_key']);
                } catch (\Throwable $e) {
                    $url = '';
                }
            } else {
                $url = qh_safe_url($item['url'] ?? '', true);
            }
            if ($url === '') {
                continue;
            }
            if ($item['resource_type'] === 'icon') {
                $plugin['icon'] = $url;
                $plugin['iconUrl'] = $url;
                $plugin['icon_object_key'] = $item['object_key'] ?? ($plugin['icon_object_key'] ?? '');
            } elseif ($item['resource_type'] === 'cover') {
                $plugin['cover'] = $url;
                $plugin['coverUrl'] = $url;
                $plugin['cover_object_key'] = $item['object_key'] ?? ($plugin['cover_object_key'] ?? '');
            }
        }
        $this->sanitizePublicPlugin($plugin, true);
    }

    private function sanitizePublicPlugin(&$plugin, bool $includeRichText = false): void
    {
        foreach (['name' => 100, 'version' => 50, 'author' => 100, 'description' => 1000, 'update_description' => 2000] as $field => $limit) {
            if (isset($plugin[$field])) {
                $plugin[$field] = qh_plain_text($plugin[$field], $limit);
            }
        }
        foreach (['icon', 'cover', 'iconUrl', 'coverUrl'] as $field) {
            if (isset($plugin[$field])) {
                $plugin[$field] = qh_safe_url($plugin[$field], true);
            }
        }
        if (isset($plugin['author_url'])) {
            $plugin['author_url'] = qh_safe_url($plugin['author_url'], false);
        }
        if (isset($plugin['origin_url'])) {
            $plugin['origin_url'] = qh_safe_url($plugin['origin_url'], false);
        }
        if ($includeRichText && isset($plugin['content'])) {
            $plugin['content'] = clean_rich_text($plugin['content']);
        }
    }

    private function writeVersionRecord(int $pluginId, string $version, array $data): int
    {
        $exists = \think\facade\Db::name('plugin_versions')
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
        return \think\facade\Db::name('plugin_versions')->insertGetId($data);
    }

    private function syncPluginResources(int $pluginId, int $versionId, int $userId, string $icon, string $cover, string $storageDriver, array $post): void
    {
        \think\facade\Db::name('plugin_resources')
            ->where('plugin_id', $pluginId)
            ->whereIn('resource_type', ['icon', 'cover'])
            ->delete();

        $now = datetime();
        $rows = [];
        if ($icon !== '') {
            $rows[] = [
                'plugin_id' => $pluginId, 'version_id' => $versionId, 'resource_type' => 'icon',
                'storage_driver' => $post['icon_storage_driver'] ?? $storageDriver, 'url' => $icon,
                'object_key' => $post['icon_object_key'] ?? '', 'file_name' => $post['icon_file_name'] ?? '',
                'file_size' => intval($post['icon_file_size'] ?? 0), 'mime_type' => $post['icon_mime_type'] ?? '',
                'sort_order' => 0, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        if ($cover !== '') {
            $rows[] = [
                'plugin_id' => $pluginId, 'version_id' => $versionId, 'resource_type' => 'cover',
                'storage_driver' => $post['cover_storage_driver'] ?? $storageDriver, 'url' => $cover,
                'object_key' => $post['cover_object_key'] ?? '', 'file_name' => $post['cover_file_name'] ?? '',
                'file_size' => intval($post['cover_file_size'] ?? 0), 'mime_type' => $post['cover_mime_type'] ?? '',
                'sort_order' => 0, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        if (!empty($rows)) {
            \think\facade\Db::name('plugin_resources')->insertAll($rows);
        }
    }

    private function decorateAuthor(&$plugin): void
    {
        $userId = intval($plugin['user_id'] ?? 0);
        if ($userId <= 0) {
            $plugin['authorDisplayName'] = t('plugin_action.official');
            $plugin['isOfficialAuthor'] = true;
            return;
        }
        $plugin['authorDisplayName'] = $this->getUserDisplayName(
            $userId,
            $plugin['author'] ?: t('plugin_action.unknown_user')
        );
        $plugin['isOfficialAuthor'] = false;
    }

    private function commissionSummary(array $settlement): string
    {
        if (($settlement['pay_type'] ?? '') === 'points') {
            return t('plugin_commission.points_exempt');
        }
        if (qh_money_to_cents($settlement['amount'] ?? 0) <= 0) {
            return t('plugin_commission.no_commission');
        }
        return t('plugin_commission.summary', [
            'rate' => PluginCommissionService::displayRate($settlement['rate'] ?? 0),
            'amount' => qh_money_format($settlement['amount'] ?? 0),
        ]);
    }

    private function decorateVersionAuthor(array &$version): void
    {
        $createdBy = intval($version['created_by'] ?? 0);
        if ($createdBy <= 0) {
            $version['authorDisplayName'] = t('plugin_action.official');
            $version['isOfficialAuthor'] = true;
            return;
        }
        $version['authorDisplayName'] = $this->getUserDisplayName($createdBy, t('plugin_action.unknown_user'));
        $version['isOfficialAuthor'] = false;
    }

    private function getUserDisplayName(int $userId, string $fallback): string
    {
        $user = \think\facade\Db::name('user')->where('id', $userId)->find();
        if (!$user) {
            return $fallback;
        }
        if (isset($user['nickname']) && $user['nickname'] !== '') {
            return $user['nickname'];
        }
        return !empty($user['username']) ? $user['username'] : $fallback;
    }

    private function assertCanDownload(array $plugin, int $userId): void
    {
        if (floatval($plugin['price']) <= 0 || (!empty($plugin['user_id']) && intval($plugin['user_id']) === intval($userId))) {
            return;
        }
        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            throw new Exception(t('plugin_action.user_info_error'));
        }
        $purchase = \think\facade\Db::name('plugin_purchase')
            ->where('plugin_id', intval($plugin['id']))
            ->where('user_id', intval($userId))
            ->where('app_id', intval($user['appid']))
            ->find();
        if (!$purchase) {
            throw new Exception(t('plugin_action.not_purchased'));
        }
    }

    private function canAccessPlugin(array $plugin, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (floatval($plugin['price']) <= 0 || (!empty($plugin['user_id']) && intval($plugin['user_id']) === intval($userId))) {
            return true;
        }
        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            return false;
        }
        return (bool) \think\facade\Db::name('plugin_purchase')
            ->where('plugin_id', intval($plugin['id']))
            ->where('user_id', intval($userId))
            ->where('app_id', intval($user['appid']))
            ->find();
    }

    private function hasPurchasedOrDownloaded(int $pluginId, int $userId, int $appId): bool
    {
        $purchase = \think\facade\Db::name('plugin_purchase')
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->find();
        if ($purchase) {
            return true;
        }
        return (bool) \think\facade\Db::name('plugin_download')
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->find();
    }
}
