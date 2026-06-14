<?php

namespace app\user\service;

use app\admin\model\PluginModel;
use app\common\model\NotificationModel;
use app\common\model\PointLogModel;
use app\common\service\BaseService;
use app\common\service\PluginStorageService;
use think\Exception;

/**
 * 用户插件服务
 * @author SF授权系统
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
    public function marketList()
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
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
            ->field('id,user_id,name,slug,category,version,author,icon,price,pay_type,description,download_count,rating_count,rating_avg,comment_count,is_hot,is_recommend,published_at,publish_type,publish_time,updated_at');

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
            $this->decorateAuthor($item);
            return $item;
        });
        return $list;
    }

    /**
     * 我的插件列表（只看自己发布的）
     */
    public function myList($userId)
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
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

        return $list;
    }

    /**
     * 用户发布/编辑插件
     */
    public function createOrEdit($userId)
    {
        $post = request()->post();
        $id = !empty($post['id']) ? intval($post['id']) : null;
        $name = !empty($post['name']) ? trim($post['name']) : null;
        $slug = !empty($post['slug']) ? trim($post['slug']) : null;
        $category = !empty($post['category']) ? trim($post['category']) : '';
        $version = !empty($post['version']) ? trim($post['version']) : '1.0.0';
        // 作者默认取当前用户名
        $userInfo = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        $author = !empty($post['author']) ? trim($post['author']) : ($userInfo['username'] ?? '');
        $author_url = !empty($post['author_url']) ? trim($post['author_url']) : '';
        $description = !empty($post['description']) ? trim($post['description']) : '';
        $content = !empty($post['content']) ? clean_rich_text($post['content']) : '';
        $icon = !empty($post['icon']) ? $post['icon'] : '';
        $cover = !empty($post['cover']) ? $post['cover'] : '';
        $images = !empty($post['images']) ? $post['images'] : [];
        $updateDescription = isset($post['update_description']) ? trim((string)$post['update_description']) : trim((string)($post['updateDescription'] ?? ''));
        $price = !empty($post['price']) ? floatval($post['price']) : 0.00;
        $pay_type = !empty($post['pay_type']) ? trim($post['pay_type']) : 'balance';

        // 原创/转载
        $origin_type = !empty($post['origin_type']) ? intval($post['origin_type']) : 1;
        $origin_url = !empty($post['origin_url']) ? trim($post['origin_url']) : '';
        $origin_author = !empty($post['origin_author']) ? trim($post['origin_author']) : '';
        $origin_note = !empty($post['origin_note']) ? trim($post['origin_note']) : '';
        $related_plugin_id = !empty($post['related_plugin_id']) ? intval($post['related_plugin_id']) : 0;

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

        // 原创/转载校验
        if (!in_array($origin_type, [1, 2])) {
            return message('请选择插件来源类型', false);
        }
        if ($origin_type == 2) {
            // 转载插件不能设置价格
            if ($price > 0) {
                return message('转载插件不能设置为付费，如需售卖请发布原创插件', false);
            }
            $price = 0.00;
            if (empty($origin_url)) {
                return message('转载插件必须填写来源地址', false);
            }
            if (!filter_var($origin_url, FILTER_VALIDATE_URL)) {
                return message('转载来源地址格式不正确', false);
            }
            if (empty($origin_author)) {
                return message('转载插件必须填写原作者', false);
            }
        }

        // 发布类型：必填；立即发布不保存时间；定时发布必须提交完整时间。
        if (!isset($post['publish_type']) || !in_array((string)$post['publish_type'], ['0', '1'], true)) {
            return message('请选择发布方式', false);
        }
        $publish_type = intval($post['publish_type']);
        $publish_time = !empty($post['publish_time']) ? trim($post['publish_time']) : null;
        if ($publish_type === 0) {
            $publish_time = null;
        } else {
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
        }

        // XSS过滤转载声明
        if ($related_plugin_id > 0) {
            $relatedPlugin = \think\facade\Db::name('plugin')->where('id', $related_plugin_id)->where('status', 1)->find();
            if (!$relatedPlugin) {
                return message('关联插件不存在或未上架', false);
            }
            if (!empty($id) && $related_plugin_id == intval($id)) {
                return message('关联插件不能选择当前插件', false);
            }
        }

        $origin_note = htmlspecialchars($origin_note, ENT_QUOTES, 'UTF-8');

        if (is_array($images)) {
            $images = json_encode($images, JSON_UNESCAPED_UNICODE);
        }

        // 文件信息
        $file_path = !empty($post['file_path']) ? $post['file_path'] : '';
        $file_hash = !empty($post['file_hash']) ? $post['file_hash'] : '';
        $file_size = !empty($post['file_size']) ? intval($post['file_size']) : 0;
        $storageDriver = !empty($post['storage_driver']) ? trim($post['storage_driver']) : 'local';
        $packageObjectKey = !empty($post['package_object_key']) ? trim($post['package_object_key']) : '';
        $packageFileName = !empty($post['package_file_name']) ? trim($post['package_file_name']) : '';
        $packageMimeType = !empty($post['package_mime_type']) ? trim($post['package_mime_type']) : '';
        $iconObjectKey = !empty($post['icon_object_key']) ? trim($post['icon_object_key']) : '';
        $coverObjectKey = !empty($post['cover_object_key']) ? trim($post['cover_object_key']) : '';

        if (!empty($id)) {
            // 编辑 - 只能编辑自己的插件
            $row = $this->model->getInfo($id);
            if (!$row) {
                return message('插件不存在', false);
            }
            if ($row['user_id'] != $userId) {
                return message('无权编辑此插件', false);
            }
            if ($slug !== $row['slug']) {
                return message('插件发布后标识不能修改', false);
            }
            if ($publish_type == 1) {
                return message('插件发布后再次编辑不能设置定时发布', false);
            }
            // 已上架的插件编辑后需要重新审核
            $newStatus = ($row['status'] == 1) ? 0 : $row['status'];

            $exists = \think\facade\Db::name('plugin')->where('slug', $slug)->where('id', '<>', $id)->find();
            if ($exists) {
                return message('插件标识「' . $slug . '」已存在', false);
            }
            $nameExists = \think\facade\Db::name('plugin')->where('name', $name)->where('id', '<>', $id)->find();
            if ($nameExists) {
                return message('插件名称「' . $name . '」已存在', false);
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
                'audit_note' => $newStatus == 0 ? '用户修改后等待重新审核' : $row['audit_note'],
                'updated_at' => datetime(),
            ];

            if (!empty($file_path)) {
                $versionExists = \think\facade\Db::name('plugin_versions')
                    ->where('plugin_id', intval($id))
                    ->where('version', $version)
                    ->find();
                if ($versionExists && $file_path !== ($row['file_path'] ?? '')) {
                    return message('当前插件下版本号「' . $version . '」已存在，请修改版本号后再发布新版本', false);
                }
                $data['file_path'] = $file_path;
                $data['file_hash'] = $file_hash;
                $data['file_size'] = $file_size;
                $data['package_object_key'] = $packageObjectKey;
                $data['package_file_name'] = $packageFileName;
                $data['package_mime_type'] = $packageMimeType;
            } elseif ($version !== ($row['version'] ?? '')) {
                return message('发布新版本请先上传对应插件包，避免新版本覆盖旧版本文件', false);
            }

            try {
                \think\facade\Db::startTrans();
                \think\facade\Db::name('plugin')->where('id', $id)->update($data);
                if (!empty($file_path) && $file_path !== ($row['file_path'] ?? '')) {
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
                \think\facade\Cache::tag('SF_Plugin')->clear();
                $msg = ($newStatus == 0 && $row['status'] == 1) ? '修改成功，插件已重新提交审核' : '修改成功';
                return message($msg, true);
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                return message('修改失败: ' . $e->getMessage(), false);
            }
        } else {
            // 新增 - 必须上传文件
            if (empty($file_path)) {
                return message('请先上传插件文件', false);
            }
            $exists = \think\facade\Db::name('plugin')->where('slug', $slug)->find();
            if ($exists) {
                return message('插件标识「' . $slug . '」已存在', false);
            }
            $nameExists = \think\facade\Db::name('plugin')->where('name', $name)->find();
            if ($nameExists) {
                return message('插件名称「' . $name . '」已存在', false);
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
                $this->syncPluginResources($pluginId, $versionId, $userId, $icon, $cover, $storageDriver, $post);
                \think\facade\Db::commit();
                \think\facade\Cache::tag('SF_Plugin')->clear();

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
                    $username = $user ? $user['username'] : '未知用户';
                    NotificationModel::add([
                        'user_id'    => 0,
                        'title'      => '新插件待审核',
                        'content'    => '用户「' . $username . '」发布了新插件「' . $name . '」，请前往插件管理审核',
                        'type'       => 'plugin_new',
                        'link'       => '/Plugin/list.html',
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                } catch (\Throwable $e) {}

                return message('发布成功，等待管理员审核', true);
            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                return message('发布失败: ' . $e->getMessage(), false);
            }
        }
    }

    /**
     * 上传插件文件
     */
    public function uploadFile()
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
                return message('文件验证失败: ' . $e->getMessage(), false, ['status' => 0]);
            }

            // 检查原始文件名是否含中文
            $originalName = $file->getOriginalName();
            if (preg_match('/[\x{4e00}-\x{9fff}]/u', $originalName)) {
                return message('压缩包名称不能包含中文，请重命名后再上传', false, ['status' => 0]);
            }

            // 尝试解析压缩包内的 conf.json 和 icon.png（支持根目录和单层子目录）
            $autoData = [];
            try {
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
                            $confContent = $zip->getFromName($f);
                            break;
                        }
                    }

                    // 匹配 icon.png（大小写不敏感）
                    foreach ($entries as $f) {
                        $base = basename($f);
                        if (strcasecmp($base, 'icon.png') === 0) {
                            $iconContent = $zip->getFromName($f);
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

            return message('上传成功', true, [
                'status' => 1,
                'file_path' => $stored['path'],
                'file_hash' => $stored['file_hash'],
                'file_size' => $stored['file_size'],
                'original_name' => $originalName,
                'storage_driver' => $stored['storage_driver'],
                'package_object_key' => $stored['object_key'],
                'package_file_name' => $stored['file_name'],
                'package_mime_type' => $stored['mime_type'],
                'auto' => $autoData,
            ]);
        } catch (\Exception $e) {
            return message('上传失败: ' . $e->getMessage(), false, ['status' => 0]);
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
            return message('评论ID不能为空', false);
        }
        if (empty($reply_content)) {
            return message('回复内容不能为空', false);
        }
        if (mb_strlen($reply_content) > 500) {
            return message('回复内容不能超过500字', false);
        }

        // XSS过滤
        $reply_content = htmlspecialchars($reply_content, ENT_QUOTES, 'UTF-8');

        // 查询评论
        $comment = \think\facade\Db::name('plugin_comment')->where('id', $comment_id)->find();
        if (!$comment) {
            return message('评论不存在', false);
        }

        // 查询插件，确认是自己的插件
        $plugin = \think\facade\Db::name('plugin')->where('id', $comment['plugin_id'])->find();
        if (!$plugin) {
            return message('插件不存在', false);
        }
        if ($plugin['user_id'] != $userId) {
            return message('只能回复自己插件的评论', false);
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
                        'title'      => '评论回复通知',
                        'content'    => '您对插件「' . $plugin['name'] . '」的评论收到了开发者回复',
                        'type'       => 'comment_reply',
                        'link'       => '/UserPlugin/detail.html?id=' . $comment['plugin_id'],
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return message('回复成功', true);
        } catch (\Exception $e) {
            return message('回复失败: ' . $e->getMessage(), false);
        }
    }

    /**
     * 获取我发表的所有评论（跨所有插件）
     */
    public function getMyAllComments($userId)
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
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
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
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
            return message('插件ID不能为空', false);
        }
        if (empty($content)) {
            return message('评论内容不能为空', false);
        }
        if (mb_strlen($content) < 10) {
            return message('评论内容至少10个字', false);
        }
        if (mb_strlen($content) > 500) {
            return message('评论内容不能超过500字', false);
        }
        if ($rating < 1 || $rating > 5) {
            return message('评分必须在1-5星之间', false);
        }

        // XSS过滤
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // 查询插件
        $plugin = \think\facade\Db::name('plugin')->where('id', $plugin_id)->find();
        if (!$plugin) {
            return message('插件不存在', false);
        }
        if ($plugin['status'] != 1) {
            return message('该插件未上架，暂不支持评论', false);
        }
        // 开发者不能评论自己的插件
        if (!empty($plugin['user_id']) && intval($plugin['user_id']) == intval($userId)) {
            return message('不能评论自己发布的插件', false);
        }

        // 获取用户的app_id
        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            return message('用户信息错误', false);
        }
        $app_id = intval($user['appid']);
        if (!$this->hasPurchasedOrDownloaded($plugin_id, intval($userId), $app_id)) {
            return message('购买或下载插件后才能评论', false);
        }

        // 检查是否已评论
        $existing = \think\facade\Db::name('plugin_comment')
            ->where('plugin_id', $plugin_id)
            ->where('user_id', intval($userId))
            ->where('app_id', $app_id)
            ->find();
        if ($existing) {
            return message('您已经评论过此插件', false);
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
                        'title'      => '插件新评论',
                        'content'    => '您的插件「' . $plugin['name'] . '」收到了新评论（' . $rating . '星），评论待审核通过后将公开显示',
                        'type'       => 'plugin_comment',
                        'link'       => '/UserPlugin/comments.html',
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return message('评论提交成功，等待管理员审核', true);
        } catch (\Exception $e) {
            return message('评论提交失败: ' . $e->getMessage(), false);
        }
    }

    /**
     * 获取插件的评论列表（仅已审核通过的）
     */
    public function getComments($pluginId)
    {
        $post = request()->post();
        $limit = !empty($post['limit']) ? intval($post['limit']) : 10;
        $current_page = !empty($post['current_page']) ? intval($post['current_page']) : 1;

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
            return message('插件ID不能为空', false);
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $plugin_id)->where('status', 1)->find();
        if (!$plugin) {
            return message('插件不存在或未上架', false);
        }
        if ($plugin['price'] <= 0) {
            return message('免费插件无需购买', false);
        }

        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            return message('用户信息错误', false);
        }
        $app_id = intval($user['appid']);

        // 检查是否已购买
        $purchase = \think\facade\Db::name('plugin_purchase')
            ->where('plugin_id', $plugin_id)
            ->where('user_id', intval($userId))
            ->where('app_id', $app_id)
            ->find();
        if ($purchase) {
            return message('您已购买此插件', false);
        }

        // 检查支付方式
        $price = floatval($plugin['price']);
        $payType = !empty($plugin['pay_type']) ? $plugin['pay_type'] : 'balance';

        if ($payType === 'points') {
            $userPoints = intval($user['integral']);
            $pointsNeeded = intval($price);
            if ($userPoints < $pointsNeeded) {
                return message('积分不足，当前积分 ' . $userPoints . '，需要 ' . $pointsNeeded, false);
            }
        } else {
            $balance = floatval($user['balance']);
            if ($balance < $price) {
                return message('余额不足，当前余额 ¥' . $balance . '，需要 ¥' . $price, false);
            }
        }

        // 事务处理
        \think\facade\Db::startTrans();
        try {
            // 计算平台抽成（仅余额支付收取佣金）
            $commissionRate = 0;
            $commissionAmount = 0;
            $developerIncome = $price;
            if ($payType !== 'points') {
                $commissionRate = floatval(conf('plugin_commission_rate') ?? 10);
                $commissionRate = max(0, min(100, $commissionRate));
                $commissionAmount = round($price * $commissionRate / 100, 2);
                $developerIncome = round($price - $commissionAmount, 2);
            }

            // 扣减买家余额或积分
            if ($payType === 'points') {
                \think\facade\Db::name('user')->where('id', intval($userId))->dec('integral', intval($price))->update();
            } else {
                \think\facade\Db::name('user')->where('id', intval($userId))->dec('balance', $price)->update();
            }

            // 创建订单
            $orderNo = 'PL' . date('YmdHis') . mt_rand(1000, 9999);
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
                    '积分购买插件：' . $plugin['name'],
                    'plugin_order_buy',
                    $orderId
                );
            } else {
                \app\common\model\BalanceLogModel::add(
                    intval($userId),
                    'deduct_plugin',
                    -$price,
                    '购买插件：' . $plugin['name'],
                    $orderId
                );
                \app\common\model\PointLogModel::grantConsumptionPoints(
                    intval($userId),
                    $price,
                    'plugin_order_consume',
                    $orderId,
                    '消费购买插件获得积分 +' . intval(floor($price)) . '：' . $plugin['name'],
                    $orderId
                );
            }

            // 给插件开发者打款
            if ($developerIncome > 0 && !empty($plugin['user_id'])) {
                $developerId = intval($plugin['user_id']);
                if ($developerId != intval($userId)) {
                    if ($payType === 'points') {
                        \think\facade\Db::name('user')->where('id', $developerId)->inc('integral', intval($developerIncome))->update();
                        \app\common\model\PointLogModel::add(
                            $developerId,
                            'plugin_income',
                            intval($developerIncome),
                            '插件积分收入：' . $plugin['name'],
                            'plugin_order_income',
                            $orderId
                        );
                    } else {
                        \think\facade\Db::name('user')->where('id', $developerId)->inc('balance', $developerIncome)->update();
                        \app\common\model\BalanceLogModel::add(
                            $developerId,
                            'plugin_income',
                            $developerIncome,
                            '插件销售收入：' . $plugin['name'] . '（佣金' . $commissionRate . '%）',
                            $orderId
                        );
                    }
                }
            }

            \think\facade\Db::commit();

            // 通知插件开发者有新购买
            try {
                if (!empty($plugin['user_id']) && intval($plugin['user_id']) != intval($userId)) {
                    $buyerInfo = \think\facade\Db::name('user')->where('id', intval($userId))->find();
                    $buyerName = $buyerInfo ? $buyerInfo['username'] : '未知用户';
                    $incomeText = $payType === 'points' ? '+'.intval($developerIncome).' 积分（免佣金）' : '¥'.$developerIncome.'（佣金'.$commissionRate.'%）';
                    NotificationModel::add([
                        'user_id'    => intval($plugin['user_id']),
                        'title'      => '插件销售通知',
                        'content'    => '用户「' . $buyerName . '」购买了您的插件「' . $plugin['name'] . '」，您获得收入 ' . $incomeText,
                        'type'       => 'plugin_purchase',
                        'link'       => '/UserPlugin/list.html',
                        'is_read'    => 0,
                        'created_at' => datetime(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return message('购买成功', true);
        } catch (\Exception $e) {
            \think\facade\Db::rollback();
            return message('购买失败: ' . $e->getMessage(), false);
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
            return message('插件ID不能为空', false);
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $plugin_id)->where('status', 1)->find();
        if (!$plugin) {
            return message('插件不存在或未上架', false);
        }

        if (($plugin['storage_driver'] ?? 'local') !== 'oss' && (empty($plugin['file_path']) || !file_exists($plugin['file_path']))) {
            return message('插件文件不存在', false);
        }

        $user = \think\facade\Db::name('user')->where('id', intval($userId))->find();
        if (!$user) {
            return message('用户信息错误', false);
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
                return message('关联插件不存在或已下架，暂时无法下载', false);
            }

            $relatedNotice = '该插件关联「' . $relatedPlugin['name'] . '」插件';
            $isRelatedOwner = !empty($relatedPlugin['user_id']) && intval($relatedPlugin['user_id']) == intval($userId);
            if (floatval($relatedPlugin['price']) > 0 && !$isRelatedOwner) {
                $relatedPurchase = \think\facade\Db::name('plugin_purchase')
                    ->where('plugin_id', intval($relatedPlugin['id']))
                    ->where('user_id', intval($userId))
                    ->where('app_id', $app_id)
                    ->find();
                if (!$relatedPurchase) {
                    return message('你需要先购买关联的「' . $relatedPlugin['name'] . '」插件，才能使用此插件', false, [
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
                return message('您尚未购买此插件', false);
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

        return message('获取成功', true, ['token' => $token, 'related_notice' => $relatedNotice]);
    }

    /**
     * 获取指定插件的购买记录（开发者查看）
     */
    public function getPluginPurchases($userId)
    {
        $post = request()->post();
        $plugin_id = !empty($post['plugin_id']) ? intval($post['plugin_id']) : 0;
        $limit = !empty($post['limit']) ? $post['limit'] : 20;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;

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
        $limit = !empty($post['limit']) ? $post['limit'] : 10;
        $current_page = !empty($post['current_page']) ? $post['current_page'] : 1;
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
    public function download()
    {
        $token = input('get.token', '');
        if (empty($token)) {
            throw new Exception('下载凭证不能为空');
        }

        $tokenRecord = \think\facade\Db::name('plugin_download_token')->where('token', $token)->find();
        if (!$tokenRecord) {
            throw new Exception('下载凭证无效');
        }
        if ($tokenRecord['used'] == 1) {
            throw new Exception('下载凭证已使用');
        }
        if (strtotime($tokenRecord['expires_at']) < time()) {
            throw new Exception('下载凭证已过期');
        }
        if ($tokenRecord['ip'] !== get_client_ip()) {
            throw new Exception('请求IP不匹配');
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $tokenRecord['plugin_id'])->where('status', 1)->find();
        if (!$plugin) {
            throw new Exception('插件不存在或已下架');
        }
        if (($plugin['storage_driver'] ?? 'local') !== 'oss' && (empty($plugin['file_path']) || !file_exists($plugin['file_path']))) {
            throw new Exception('插件文件不存在');
        }

        // 标记凭证已使用
        \think\facade\Db::name('plugin_download_token')->where('id', $tokenRecord['id'])->update([
            'used' => 1,
            'used_at' => datetime(),
        ]);

        // 记录下载
        \think\facade\Db::name('plugin_download')->insert([
            'plugin_id' => $plugin['id'],
            'plugin_version' => $plugin['version'],
            'user_id' => $tokenRecord['user_id'],
            'app_id' => $tokenRecord['app_id'],
            'order_id' => $tokenRecord['order_id'],
            'ip' => get_client_ip(),
            'created_at' => datetime(),
        ]);

        // 增加下载次数
        \think\facade\Db::name('plugin')->where('id', $plugin['id'])->inc('download_count')->update();

        // 返回文件
        $filePath = (new PluginStorageService())->getDownloadUrl($plugin);
        $fileName = $plugin['slug'] . '_v' . $plugin['version'] . '.zip';

        if (preg_match('#^https?://#i', $filePath)) {
            header('Location: ' . $filePath);
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }

    public function uploadResource(string $type)
    {
        $type = in_array($type, ['icon', 'cover'], true) ? $type : 'icon';
        try {
            $file = request()->file('file');
            $stored = (new PluginStorageService())->storeUploadedFile($file, $type, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            return message('上传成功', true, [
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
            return message('上传失败: ' . $e->getMessage(), false, ['status' => 0]);
        }
    }

    public function getVersions(int $pluginId, int $userId = 0): array
    {
        if ($pluginId <= 0) {
            throw new Exception('插件ID不能为空');
        }
        $plugin = \think\facade\Db::name('plugin')->where('id', $pluginId)->find();
        if (!$plugin) {
            throw new Exception('插件不存在');
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
            $row['update_description'] = !empty($row['update_description']) ? $row['update_description'] : '暂无更新说明';
        }
        unset($row);

        return $rows;
    }

    public function downloadVersion(int $userId)
    {
        $pluginId = input('get.plugin_id', 0, 'intval');
        $versionId = input('get.version_id', 0, 'intval');
        [$plugin, $version] = $this->getDownloadableVersion($pluginId, $versionId, $userId);

        $downloadPath = (new PluginStorageService())->getDownloadUrl($version);
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
            header('Location: ' . $downloadPath);
            exit;
        }

        $fileName = $plugin['slug'] . '_v' . $version['version'] . '.zip';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($downloadPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($downloadPath);
        exit;
    }

    public function checkVersionDownload(int $userId): array
    {
        $pluginId = input('get.plugin_id', input('post.plugin_id', 0, 'intval'), 'intval');
        $versionId = input('get.version_id', input('post.version_id', 0, 'intval'), 'intval');
        $this->getDownloadableVersion($pluginId, $versionId, $userId);
        return message('可以下载', true);
    }

    private function getDownloadableVersion(int $pluginId, int $versionId, int $userId): array
    {
        if ($pluginId <= 0 || $versionId <= 0) {
            throw new Exception('插件版本参数错误');
        }

        $plugin = \think\facade\Db::name('plugin')->where('id', $pluginId)->where('status', 1)->find();
        if (!$plugin) {
            throw new Exception('插件不存在或未上架');
        }
        $this->assertCanDownload($plugin, $userId);

        $version = \think\facade\Db::name('plugin_versions')
            ->where('id', $versionId)
            ->where('plugin_id', $pluginId)
            ->find();
        if (!$version) {
            throw new Exception('版本记录不存在');
        }

        (new PluginStorageService())->getDownloadUrl($version);
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
        $plugin['latestUpdateDescription'] = !empty($plugin['update_description']) ? $plugin['update_description'] : '暂无更新说明';
        $resources = \think\facade\Db::name('plugin_resources')
            ->where('plugin_id', intval($plugin['id']))
            ->whereIn('resource_type', ['icon', 'cover'])
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        foreach ($resources as $item) {
            $url = $item['url'] ?: ($item['object_key'] ?? '');
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
    }

    private function writeVersionRecord(int $pluginId, string $version, array $data): int
    {
        $exists = \think\facade\Db::name('plugin_versions')
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
            $plugin['authorDisplayName'] = '官方';
            $plugin['isOfficialAuthor'] = true;
            return;
        }
        $plugin['authorDisplayName'] = $this->getUserDisplayName($userId, $plugin['author'] ?: '未知用户');
        $plugin['isOfficialAuthor'] = false;
    }

    private function decorateVersionAuthor(array &$version): void
    {
        $createdBy = intval($version['created_by'] ?? 0);
        if ($createdBy <= 0) {
            $version['authorDisplayName'] = '官方';
            $version['isOfficialAuthor'] = true;
            return;
        }
        $version['authorDisplayName'] = $this->getUserDisplayName($createdBy, '未知用户');
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
            throw new Exception('用户信息错误');
        }
        $purchase = \think\facade\Db::name('plugin_purchase')
            ->where('plugin_id', intval($plugin['id']))
            ->where('user_id', intval($userId))
            ->where('app_id', intval($user['appid']))
            ->find();
        if (!$purchase) {
            throw new Exception('您尚未购买此插件');
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
