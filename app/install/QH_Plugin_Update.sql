-- 插件表补充字段迁移
-- 执行时间: 2026-05-03
-- 说明: 该脚本需要同时兼容旧库升级和新库初始化后的重复执行。

DROP PROCEDURE IF EXISTS QH_ADD_COLUMN_IF_MISSING;

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

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_commission_enabled','plugin_market','启用插件销售平台抽成','开启后，余额及在线支付的插件订单按设置比例抽成；关闭后发布者获得全部销售收入。积分支付始终免抽成。','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name`='plugin_commission_enabled');

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_commission_rate','plugin_market','插件销售平台抽成比例（%）','仅在抽成开关开启时生效，范围 0～100，最多保留两位小数；新比例仅影响后续支付成功的订单。','number','10.00','','required','min="0" max="100" step="0.01"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name`='plugin_commission_rate');

UPDATE `QH_config`
SET `group`='plugin_market', `title`='启用插件销售平台抽成',
    `tip`='开启后，余额及在线支付的插件订单按设置比例抽成；关闭后发布者获得全部销售收入。积分支付始终免抽成。', `type`='bool'
WHERE `name`='plugin_commission_enabled';

UPDATE `QH_config`
SET `group`='plugin_market', `title`='插件销售平台抽成比例（%）',
    `tip`='仅在抽成开关开启时生效，范围 0～100，最多保留两位小数；新比例仅影响后续支付成功的订单。',
    `type`='number', `rule`='required', `extend`='min="0" max="100" step="0.01"'
WHERE `name`='plugin_commission_rate';
DELIMITER $$
CREATE PROCEDURE QH_ADD_COLUMN_IF_MISSING(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_column_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @qh_sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_definition);
    PREPARE qh_stmt FROM @qh_sql;
    EXECUTE qh_stmt;
    DEALLOCATE PREPARE qh_stmt;
  END IF;
END$$
DELIMITER ;

CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'app_id', '`app_id` int(11) unsigned NOT NULL DEFAULT ''1'' COMMENT ''所属应用ID'' AFTER `id`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'user_id', '`user_id` int(11) unsigned NOT NULL DEFAULT ''0'' COMMENT ''发布者用户ID'' AFTER `app_id`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'category', '`category` varchar(30) DEFAULT '''' COMMENT ''分类'' AFTER `slug`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'plugin_dir', '`plugin_dir` varchar(64) NOT NULL DEFAULT '''' COMMENT ''Xiuno插件安装目录'' AFTER `slug`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin_package_upload', 'plugin_dir', '`plugin_dir` varchar(64) NOT NULL DEFAULT '''' COMMENT ''ZIP唯一顶层插件目录'' AFTER `package_file_name`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'origin_type', '`origin_type` tinyint(1) unsigned NOT NULL DEFAULT ''1'' COMMENT ''来源:1=原创,2=转载'' AFTER `images`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'origin_url', '`origin_url` varchar(255) DEFAULT '''' COMMENT ''转载来源地址'' AFTER `origin_type`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'origin_author', '`origin_author` varchar(100) DEFAULT '''' COMMENT ''转载原作者'' AFTER `origin_url`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin', 'origin_note', '`origin_note` varchar(500) DEFAULT '''' COMMENT ''转载声明/备注'' AFTER `origin_author`');

CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin_comment', 'reply_content', '`reply_content` text COMMENT ''开发者回复内容'' AFTER `status`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin_comment', 'reply_at', '`reply_at` datetime DEFAULT NULL COMMENT ''开发者回复时间'' AFTER `reply_content`');

CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin_order', 'commission_rate', '`commission_rate` decimal(5,2) unsigned NOT NULL DEFAULT ''0.00'' COMMENT ''平台抽成比例(%)'' AFTER `price`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin_order', 'commission_amount', '`commission_amount` decimal(10,2) unsigned NOT NULL DEFAULT ''0.00'' COMMENT ''平台抽成金额'' AFTER `commission_rate`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_plugin_order', 'developer_income', '`developer_income` decimal(10,2) unsigned NOT NULL DEFAULT ''0.00'' COMMENT ''开发者收入'' AFTER `commission_amount`');

CALL QH_ADD_COLUMN_IF_MISSING('QH_notification', 'link', '`link` varchar(255) DEFAULT '''' COMMENT ''跳转链接'' AFTER `type`');

-- Backfill legacy packages that followed the historical {directory}_v{version}.zip convention.
UPDATE `QH_plugin`
SET `plugin_dir` = LEFT(`package_file_name`, CHAR_LENGTH(`package_file_name`) - CHAR_LENGTH(CONCAT('_v', `version`, '.zip')))
WHERE `plugin_dir` = ''
  AND CHAR_LENGTH(`package_file_name`) > CHAR_LENGTH(CONCAT('_v', `version`, '.zip'))
  AND LOWER(RIGHT(`package_file_name`, CHAR_LENGTH(CONCAT('_v', `version`, '.zip')))) = LOWER(CONCAT('_v', `version`, '.zip'))
  AND LEFT(`package_file_name`, CHAR_LENGTH(`package_file_name`) - CHAR_LENGTH(CONCAT('_v', `version`, '.zip'))) REGEXP '^[A-Za-z0-9_]{1,64}$';

UPDATE `QH_plugin`
SET `plugin_dir` = `slug`
WHERE `plugin_dir` = '' AND `slug` REGEXP '^[A-Za-z0-9_]{1,64}$';

DROP PROCEDURE IF EXISTS QH_ADD_COLUMN_IF_MISSING;
