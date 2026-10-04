<?php

namespace app\api\controller;

use app\api\service\PluginApiService;
use app\common\controller\Frontend;

/**
 * 插件中心API控制器
 * @author SF授权系统
 * @since 2026-05-03
 */
class PluginApi extends Frontend
{
    public function initialize()
    {
        parent::initialize();
        $this->service = new PluginApiService();
    }

    /**
     * 签发用户令牌（插件中心登录）
     *
     * 配合 A-05 修复：validateUser() 现在强制校验 user_token，
     * 客户端需先调用本接口取得令牌。
     */
    public function authUser()
    {
        return $this->service->authUser();
    }

    /**
     * 获取插件列表
     */
    public function getList()
    {
        return $this->service->getList();
    }

    /**
     * 获取插件详情
     */
    public function getDetail()
    {
        return $this->service->getDetail();
    }

    /**
     * 创建插件订单
     */
    public function createOrder()
    {
        return $this->service->createOrder();
    }

    /**
     * 查询订单状态
     */
    public function queryOrder()
    {
        return $this->service->queryOrder();
    }

    /**
     * 获取下载凭证
     */
    public function getDownloadToken()
    {
        return $this->service->getDownloadToken();
    }

    /**
     * 下载插件
     */
    public function download()
    {
        return $this->service->download();
    }

    /**
     * 提交评论
     */
    public function submitComment()
    {
        return $this->service->submitComment();
    }

    /**
     * 提交评分
     */
    public function submitRating()
    {
        return $this->service->submitRating();
    }

    /**
     * 获取评论列表
     */
    public function getComments()
    {
        return $this->service->getComments();
    }

    /**
     * 检查是否已购买
     */
    public function checkPurchased()
    {
        return $this->service->checkPurchased();
    }
}
