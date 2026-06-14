<?php

namespace app\user\controller;

use app\common\controller\UserBackend;
use app\user\service\UserPluginService;
use app\admin\model\PluginModel;
use think\facade\View;

/**
 * 用户插件管理控制器
 * @author SF授权系统
 * @since 2026-05-03
 */
class UserPlugin extends UserBackend
{
    public function initialize()
    {
        parent::initialize();
        $this->denyIfClosed();
        $this->service = new UserPluginService();
    }

    private function denyIfClosed()
    {
        if (!feature_enabled('feature_user_plugin_enabled')) {
            if (IS_POST) {
                exit(json_encode(message('插件中心功能已关闭', false), JSON_UNESCAPED_UNICODE));
            }
            exit($this->render('/public/error', ['msg' => '插件中心功能已关闭']));
        }
    }

    /**
     * 插件市场（浏览所有已上架的插件）
     */
    public function market()
    {
        if (IS_POST) {
            try {
                $result = $this->service->marketList();
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }
        return $this->render();
    }

    /**
     * 我的插件列表（只看自己发布的）
     */
    public function list()
    {
        if (IS_POST) {
            try {
                $result = $this->service->myList($this->userId);
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }
        return $this->render();
    }

    /**
     * 发布/编辑插件
     */
    public function create()
    {
        if (IS_POST) {
            try {
                return json($this->service->createOrEdit($this->userId));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }

        $id = input('get.id', 0, 'intval');
        $plugin = [];
        if ($id > 0) {
            $pluginModel = new PluginModel();
            $plugin = $pluginModel->getInfo($id);
            if (!$plugin) {
                return $this->render('public/error', ['msg' => '插件不存在']);
            }
            // 只能编辑自己的插件
            if ($plugin['user_id'] != $this->userId) {
                return $this->render('public/error', ['msg' => '无权编辑此插件']);
            }
            $this->service->enrichPluginDetail($plugin);
        }
        View::assign('plugin', $plugin);
        View::assign('commissionRate', conf('plugin_commission_rate') ?? 10);
        return $this->render();
    }

    /**
     * 上传插件图标/富文本图片
     */
    public function uploadImage()
    {
        try {
            $file = request()->file('file');
            if (!$file) {
                return json(['code' => 1, 'msg' => '请选择图片']);
            }
            $allowedExt = 'jpg,jpeg,png,gif,bmp,webp';
            $maxSize = 5 * 1024 * 1024;
            $ext = strtolower($file->getOriginalExtension());
            if (!in_array($ext, explode(',', $allowedExt))) {
                return json(['code' => 1, 'msg' => '仅支持 jpg/jpeg/png/gif/bmp/webp 图片']);
            }
            if ($file->getSize() > $maxSize) {
                return json(['code' => 1, 'msg' => '图片不能超过5MB']);
            }
            $uploadDir = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . date('Ymd');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = md5(uniqid(mt_rand(), true)) . '.' . $ext;
            $file->move($uploadDir, $fileName);
            $path = '/upload/temp/' . date('Ymd') . '/' . $fileName;
            return json(message('success', true, ['path' => $path]));
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '上传失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 上传插件文件
     */
    public function uploadFile()
    {
        if (IS_POST) {
            return json($this->service->uploadFile());
        }
    }

    public function uploadResource()
    {
        if (IS_POST) {
            $type = input('get.type', input('post.type', 'icon', 'trim'), 'trim');
            return json($this->service->uploadResource($type));
        }
    }

    public function searchRelatedPlugins()
    {
        if (IS_POST) {
            try {
                $keyword = input('post.keyword', '', 'trim');
                $excludeId = input('post.exclude_id', 0, 'intval');
                $pluginModel = new PluginModel();
                return json(message('ok', true, ['list' => $pluginModel->searchRelatedOptions($keyword, $excludeId)]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['list' => []]));
            }
        }
    }

    /**
     * 删除自己的插件
     */
    public function drop()
    {
        if (IS_POST) {
            $id = input('post.id');
            try {
                $pluginModel = new PluginModel();
                $info = $pluginModel->getInfo($id);
                if (!$info) {
                    return json(message('插件不存在', false));
                }
                // 只能删除自己的插件
                if ($info['user_id'] != $this->userId) {
                    return json(message('无权删除此插件', false));
                }
                // 已上架的插件不能删除
                if ($info['status'] == 1) {
                    return json(message('已上架的插件不能删除，请联系管理员', false));
                }
                $pluginModel->drop($id);
                return json(message('删除成功', true));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }
    }

    /**
     * 查看插件详情
     */
    public function detail()
    {
        $id = input('get.id', 0, 'intval');
        if ($id <= 0) {
            return $this->render('public/error', ['msg' => '插件ID错误']);
        }

        $pluginModel = new PluginModel();
        $plugin = $pluginModel->getInfo($id);
        if (!$plugin) {
            return $this->render('public/error', ['msg' => '插件不存在']);
        }
        $this->service->enrichPluginDetail($plugin);

        // 检查用户是否已评论
        $hasCommented = false;
        $myComment = null;
        // 检查是否已购买
        $isPurchased = false;
        $isOwner = false;
        $canComment = false;
        $userBalance = 0;

        if (!empty($this->userId)) {
            $userInfo = \think\facade\Db::name('user')->where('id', intval($this->userId))->find();
            if ($userInfo) {
                $app_id = intval($userInfo['appid']);
                $userBalance = floatval($userInfo['balance']);

                $myComment = \think\facade\Db::name('plugin_comment')
                    ->where('plugin_id', $id)
                    ->where('user_id', intval($this->userId))
                    ->where('app_id', $app_id)
                    ->find();
                $hasCommented = !empty($myComment);

                // 自己发布的插件
                $isOwner = isset($plugin['user_id']) && $plugin['user_id'] == intval($this->userId);
                $purchase = null;

                // 免费或自己的插件视为已获取，可直接下载。
                if ($plugin['price'] == 0 || $isOwner) {
                    $isPurchased = true;
                } else {
                    $purchase = \think\facade\Db::name('plugin_purchase')
                        ->where('plugin_id', $id)
                        ->where('user_id', intval($this->userId))
                        ->where('app_id', $app_id)
                        ->find();
                    $isPurchased = !empty($purchase);
                }
                if (!$isOwner) {
                    $hasDownloaded = \think\facade\Db::name('plugin_download')
                        ->where('plugin_id', $id)
                        ->where('user_id', intval($this->userId))
                        ->where('app_id', $app_id)
                        ->find();
                    $canComment = !empty($purchase) || !empty($hasDownloaded);
                }
            }
        }

        // 该作者的其他插件（最新3个）
        $authorPlugins = [];
        if (!empty($plugin['user_id'])) {
            $authorPlugins = \think\facade\Db::name('plugin')
                ->where('user_id', intval($plugin['user_id']))
                ->where('id', '<>', $id)
                ->where('status', 1)
                ->order('id', 'desc')
                ->limit(3)
                ->field('id, name, icon, price')
                ->select()
                ->toArray();
        }

        // 哪些插件关联了当前插件
        $referencingPlugins = \think\facade\Db::name('plugin')
            ->where('related_plugin_id', $id)
            ->where('status', 1)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->limit(6)
            ->field('id, name, icon, price, pay_type')
            ->select()
            ->toArray();

        View::assign('plugin', $plugin);
        View::assign('hasCommented', $hasCommented);
        View::assign('myComment', $myComment);
        View::assign('isPurchased', $isPurchased);
        View::assign('isOwner', $isOwner);
        View::assign('canComment', $canComment);
        View::assign('userBalance', $userBalance);
        View::assign('authorPlugins', $authorPlugins);
        View::assign('referencingPlugins', $referencingPlugins);
        return $this->render();
    }

    /**
     * 购买插件
     */
    public function purchase()
    {
        if (IS_POST) {
            try {
                return json($this->service->purchase($this->userId));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }
    }

    /**
     * 获取下载凭证
     */
    public function getDownloadToken()
    {
        if (IS_POST) {
            try {
                return json($this->service->getDownloadToken($this->userId));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }
    }

    /**
     * 下载插件文件
     */
    public function download()
    {
        try {
            $this->service->download();
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function versions()
    {
        try {
            $pluginId = input('get.plugin_id', input('post.plugin_id', 0, 'intval'), 'intval');
            return json(message('获取成功', true, ['list' => $this->service->getVersions($pluginId, $this->userId)]));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false, ['list' => []]));
        }
    }

    public function downloadVersion()
    {
        try {
            $this->service->downloadVersion($this->userId);
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    public function checkVersionDownload()
    {
        try {
            return json($this->service->checkVersionDownload($this->userId));
        } catch (\Exception $e) {
            return json(message($e->getMessage(), false));
        }
    }

    /**
     * 提交评论
     */
    public function submitComment()
    {
        if (IS_POST) {
            try {
                return json($this->service->submitComment($this->userId));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }
    }

    /**
     * 获取评论列表
     */
    public function getComments()
    {
        if (IS_POST) {
            try {
                $plugin_id = input('post.plugin_id', 0, 'intval');
                $result = $this->service->getComments($plugin_id);
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }
    }

    /**
     * 回复评论（仅开发者）
     */
    public function replyComment()
    {
        if (IS_POST) {
            try {
                return json($this->service->replyComment($this->userId));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false));
            }
        }
    }

    /**
     * 插件购买记录（开发者查看）
     */
    public function pluginPurchases()
    {
        if (IS_POST) {
            try {
                $result = $this->service->getPluginPurchases($this->userId);
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }
    }

    /**
     * 我的购买记录
     */
    public function purchases()
    {
        if (IS_POST) {
            try {
                $result = $this->service->getMyPurchases($this->userId);
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }

        return $this->render();
    }

    /**
     * 评论管理（我的评论 / 我的应用评论）
     */
    public function comments()
    {
        if (IS_POST) {
            try {
                $type = input('post.type', 'my_comments');
                if ($type === 'my_app_comments') {
                    $result = $this->service->getMyPluginComments($this->userId);
                } else {
                    $result = $this->service->getMyAllComments($this->userId);
                }
                return json(message(t('common.list_success'), true, ['data' => $result]));
            } catch (\Exception $e) {
                return json(message($e->getMessage(), false, ['data' => []]));
            }
        }

        return $this->render();
    }
}
