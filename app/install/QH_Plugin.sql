-- 插件中心数据库表结构
-- 创建时间: 2026-05-03

-- 插件表
CREATE TABLE IF NOT EXISTS `QH_plugin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '发布者用户ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '插件名称',
  `slug` varchar(100) NOT NULL DEFAULT '' COMMENT '插件标识(唯一)',
  `plugin_dir` varchar(64) NOT NULL DEFAULT '' COMMENT 'Xiuno插件安装目录',
  `category` varchar(30) DEFAULT '' COMMENT '分类',
  `version` varchar(50) NOT NULL DEFAULT '1.0.0' COMMENT '插件版本',
  `author` varchar(100) NOT NULL DEFAULT '' COMMENT '作者',
  `author_url` varchar(255) DEFAULT '' COMMENT '作者网址',
  `description` text COMMENT '插件简介',
  `content` longtext COMMENT '插件详细介绍(富文本)',
  `icon` varchar(255) DEFAULT '' COMMENT '插件图标URL',
  `images` text COMMENT '插件图片(JSON数组,兼容旧数据)',
  `cover` varchar(255) DEFAULT '' COMMENT '插件封面图URL',
  `origin_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '来源:1=原创,2=转载',
  `origin_url` varchar(255) DEFAULT '' COMMENT '转载来源地址',
  `origin_author` varchar(100) DEFAULT '' COMMENT '转载原作者',
  `origin_note` varchar(500) DEFAULT '' COMMENT '转载声明/备注',
  `related_plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关联插件ID，可选',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `file_path` varchar(255) NOT NULL DEFAULT '' COMMENT '插件文件路径(私有存储)',
  `package_object_key` varchar(500) DEFAULT '' COMMENT '插件包OSS对象Key',
  `package_file_name` varchar(255) DEFAULT '' COMMENT '插件包原始文件名',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '文件大小(字节)',
  `package_mime_type` varchar(100) DEFAULT '' COMMENT '插件包MIME类型',
  `file_hash` varchar(64) DEFAULT '' COMMENT '文件SHA-256哈希',
  `icon_object_key` varchar(500) DEFAULT '' COMMENT '图标OSS对象Key',
  `cover_object_key` varchar(500) DEFAULT '' COMMENT '封面OSS对象Key',
  `update_description` text COMMENT '最新版本更新说明',
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
  `publish_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '发布类型:0=立即发布,1=定时发布',
  `publish_time` datetime DEFAULT NULL COMMENT '定时发布时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_plugin_dir` (`plugin_dir`),
  KEY `status` (`status`),
  KEY `price` (`price`),
  KEY `download_count` (`download_count`),
  KEY `rating_avg` (`rating_avg`),
  KEY `sort` (`sort`),
  KEY `related_plugin_id` (`related_plugin_id`),
  KEY `is_hot` (`is_hot`),
  KEY `is_recommend` (`is_recommend`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件表';

-- 插件版本历史表
CREATE TABLE IF NOT EXISTS `QH_plugin_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '版本记录ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `version` varchar(50) NOT NULL DEFAULT '' COMMENT '版本号',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `package_path` varchar(500) DEFAULT '' COMMENT '本地插件包路径或URL',
  `package_object_key` varchar(500) DEFAULT '' COMMENT '插件包OSS对象Key',
  `package_file_name` varchar(255) DEFAULT '' COMMENT '插件包原始文件名',
  `package_file_size` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '插件包大小',
  `package_mime_type` varchar(100) DEFAULT '' COMMENT '插件包MIME类型',
  `package_hash` varchar(64) DEFAULT '' COMMENT '插件包SHA-256哈希',
  `update_description` text COMMENT '更新说明',
  `created_by` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建人用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_version` (`plugin_id`,`version`),
  KEY `idx_plugin_id` (`plugin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件版本历史表';

-- 插件包上传后、提交发布前的私有暂存记录
CREATE TABLE IF NOT EXISTS `QH_plugin_package_upload` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '上传记录ID',
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '一次性上传凭证SHA-256',
  `actor_type` varchar(10) NOT NULL COMMENT 'user/admin',
  `actor_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上传者ID',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT 'local/oss',
  `file_path` varchar(500) NOT NULL DEFAULT '' COMMENT '本地私有文件路径',
  `package_object_key` varchar(500) NOT NULL DEFAULT '' COMMENT 'OSS对象Key',
  `package_file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `plugin_dir` varchar(64) NOT NULL DEFAULT '' COMMENT 'ZIP唯一顶层插件目录',
  `package_file_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '文件字节数',
  `package_mime_type` varchar(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `package_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT 'SHA-256',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/cleaning/cleaned/consumed',
  `consumed_plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '最终关联插件ID',
  `expires_at` datetime NOT NULL COMMENT '凭证过期时间',
  `consumed_at` datetime DEFAULT NULL COMMENT '提交发布时间',
  `cleaned_at` datetime DEFAULT NULL COMMENT '孤儿文件清理时间',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_actor_status` (`actor_type`,`actor_id`,`status`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_consumed_plugin` (`consumed_plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件包待提交上传记录';

-- 插件资源表
CREATE TABLE IF NOT EXISTS `QH_plugin_resources` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '资源ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `version_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件版本ID',
  `resource_type` varchar(50) NOT NULL DEFAULT '' COMMENT '资源类型:icon/cover/package/attachment',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `url` varchar(500) DEFAULT '' COMMENT '资源访问URL或本地路径',
  `object_key` varchar(500) DEFAULT '' COMMENT 'OSS对象Key',
  `file_name` varchar(255) DEFAULT '' COMMENT '原始文件名',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '文件大小',
  `mime_type` varchar(100) DEFAULT '' COMMENT 'MIME类型',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_by` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建人用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_plugin_id` (`plugin_id`),
  KEY `idx_version_id` (`version_id`),
  KEY `idx_resource_type` (`resource_type`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件资源表';

-- 插件订单表
CREATE TABLE IF NOT EXISTS `QH_plugin_order` (
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
CREATE TABLE IF NOT EXISTS `QH_plugin_comment` (
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
CREATE TABLE IF NOT EXISTS `QH_plugin_rating` (
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
CREATE TABLE IF NOT EXISTS `QH_plugin_download` (
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
CREATE TABLE IF NOT EXISTS `QH_plugin_download_token` (
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
CREATE TABLE IF NOT EXISTS `QH_plugin_purchase` (
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

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_commission_enabled','plugin_market','启用插件销售平台抽成','开启后，余额及在线支付的插件订单按设置比例抽成；关闭后发布者获得全部销售收入。积分支付始终免抽成。','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name`='plugin_commission_enabled');

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_commission_rate','plugin_market','插件销售平台抽成比例（%）','仅在抽成开关开启时生效，范围 0～100，最多保留两位小数；新比例仅影响后续支付成功的订单。','number','10.00','','required','min="0" max="100" step="0.01"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name`='plugin_commission_rate');
