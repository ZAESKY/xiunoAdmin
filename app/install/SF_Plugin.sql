-- 插件中心数据库表结构
-- 创建时间: 2026-05-03

-- 插件表
CREATE TABLE IF NOT EXISTS `SF_plugin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '发布者用户ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '插件名称',
  `slug` varchar(100) NOT NULL DEFAULT '' COMMENT '插件标识(唯一)',
  `category` varchar(30) DEFAULT '' COMMENT '分类',
  `version` varchar(50) NOT NULL DEFAULT '1.0.0' COMMENT '插件版本',
  `author` varchar(100) NOT NULL DEFAULT '' COMMENT '作者',
  `author_url` varchar(255) DEFAULT '' COMMENT '作者网址',
  `description` text COMMENT '插件简介',
  `content` longtext COMMENT '插件详细介绍(富文本)',
  `icon` varchar(255) DEFAULT '' COMMENT '插件图标URL',
  `images` text COMMENT '插件截图(JSON数组)',
  `origin_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '来源:1=原创,2=转载',
  `origin_url` varchar(255) DEFAULT '' COMMENT '转载来源地址',
  `origin_author` varchar(100) DEFAULT '' COMMENT '转载原作者',
  `origin_note` varchar(500) DEFAULT '' COMMENT '转载声明/备注',
  `file_path` varchar(255) NOT NULL DEFAULT '' COMMENT '插件文件路径(私有存储)',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '文件大小(字节)',
  `file_hash` varchar(64) DEFAULT '' COMMENT '文件MD5哈希',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '插件价格(0为免费)',
  `pay_type` varchar(10) DEFAULT 'balance' COMMENT '支付方式:balance=余额,points=积分',
  `download_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '下载次数',
  `rating_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '评分人数',
  `rating_avg` decimal(3,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平均评分(0-5)',
  `comment_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '评论数量',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态:0=待审核,1=已上架,2=已下架,3=审核拒绝',
  `audit_note` varchar(500) DEFAULT '' COMMENT '审核备注',
  `sort` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '排序(数字越大越靠前)',
  `is_hot` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否热门:0=否,1=是',
  `is_recommend` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否推荐:0=否,1=是',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `published_at` datetime DEFAULT NULL COMMENT '上架时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `status` (`status`),
  KEY `price` (`price`),
  KEY `download_count` (`download_count`),
  KEY `rating_avg` (`rating_avg`),
  KEY `sort` (`sort`),
  KEY `is_hot` (`is_hot`),
  KEY `is_recommend` (`is_recommend`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件表';

-- 插件订单表
CREATE TABLE IF NOT EXISTS `SF_plugin_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_no` varchar(64) NOT NULL DEFAULT '' COMMENT '订单号',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `plugin_name` varchar(255) NOT NULL DEFAULT '' COMMENT '插件名称',
  `plugin_version` varchar(50) NOT NULL DEFAULT '' COMMENT '插件版本',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '应用ID',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `commission_rate` decimal(5,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平台抽成比例(%)',
  `commission_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平台抽成金额',
  `developer_income` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '开发者收入',
  `pay_type` varchar(20) DEFAULT '' COMMENT '支付方式:alipay,wxpay,qqpay,balance',
  `pay_trade_no` varchar(100) DEFAULT '' COMMENT '支付平台订单号',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态:0=待支付,1=已支付,2=已取消,3=已退款',
  `paid_at` datetime DEFAULT NULL COMMENT '支付时间',
  `ip` varchar(50) DEFAULT '' COMMENT '下单IP',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件订单表';

-- 插件评论表
CREATE TABLE IF NOT EXISTS `SF_plugin_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '评论ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '应用ID',
  `content` text NOT NULL COMMENT '评论内容',
  `rating` tinyint(1) unsigned NOT NULL DEFAULT '5' COMMENT '评分:1-5星',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态:0=待审核,1=已通过,2=已拒绝',
  `reply_content` text COMMENT '开发者回复内容',
  `reply_at` datetime DEFAULT NULL COMMENT '开发者回复时间',
  `ip` varchar(50) DEFAULT '' COMMENT '评论IP',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件评论表';

-- 插件评分表
CREATE TABLE IF NOT EXISTS `SF_plugin_rating` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '评分ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '应用ID',
  `rating` tinyint(1) unsigned NOT NULL DEFAULT '5' COMMENT '评分:1-5星',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `plugin_user_app` (`plugin_id`,`user_id`,`app_id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件评分表';

-- 插件下载记录表
CREATE TABLE IF NOT EXISTS `SF_plugin_download` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '下载记录ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `plugin_version` varchar(50) NOT NULL DEFAULT '' COMMENT '插件版本',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '应用ID',
  `order_id` int(11) unsigned DEFAULT '0' COMMENT '订单ID(付费插件)',
  `ip` varchar(50) DEFAULT '' COMMENT '下载IP',
  `created_at` datetime DEFAULT NULL COMMENT '下载时间',
  PRIMARY KEY (`id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `order_id` (`order_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件下载记录表';

-- 临时下载凭证表
CREATE TABLE IF NOT EXISTS `SF_plugin_download_token` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Token ID',
  `token` varchar(64) NOT NULL DEFAULT '' COMMENT '下载凭证',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '应用ID',
  `order_id` int(11) unsigned DEFAULT '0' COMMENT '订单ID(付费插件)',
  `ip` varchar(50) DEFAULT '' COMMENT '请求IP',
  `used` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否已使用:0=未使用,1=已使用',
  `used_at` datetime DEFAULT NULL COMMENT '使用时间',
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `used` (`used`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='临时下载凭证表';

-- 插件购买记录表(用于快速查询用户是否已购买)
CREATE TABLE IF NOT EXISTS `SF_plugin_purchase` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '购买记录ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '应用ID',
  `order_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '订单ID',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `created_at` datetime DEFAULT NULL COMMENT '购买时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `plugin_user_app` (`plugin_id`,`user_id`,`app_id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件购买记录表';
