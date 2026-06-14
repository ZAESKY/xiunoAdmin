-- 插件表补充字段迁移
-- 执行时间: 2026-05-03
-- 说明: 该脚本需要同时兼容旧库升级和新库初始化后的重复执行。

DROP PROCEDURE IF EXISTS SF_ADD_COLUMN_IF_MISSING;
DELIMITER $$
CREATE PROCEDURE SF_ADD_COLUMN_IF_MISSING(
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
    SET @sf_sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_definition);
    PREPARE sf_stmt FROM @sf_sql;
    EXECUTE sf_stmt;
    DEALLOCATE PREPARE sf_stmt;
  END IF;
END$$
DELIMITER ;

CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin', 'user_id', '`user_id` int(11) unsigned NOT NULL DEFAULT ''0'' COMMENT ''发布者用户ID'' AFTER `id`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin', 'category', '`category` varchar(30) DEFAULT '''' COMMENT ''分类'' AFTER `slug`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin', 'origin_type', '`origin_type` tinyint(1) unsigned NOT NULL DEFAULT ''1'' COMMENT ''来源:1=原创,2=转载'' AFTER `images`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin', 'origin_url', '`origin_url` varchar(255) DEFAULT '''' COMMENT ''转载来源地址'' AFTER `origin_type`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin', 'origin_author', '`origin_author` varchar(100) DEFAULT '''' COMMENT ''转载原作者'' AFTER `origin_url`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin', 'origin_note', '`origin_note` varchar(500) DEFAULT '''' COMMENT ''转载声明/备注'' AFTER `origin_author`');

CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin_comment', 'reply_content', '`reply_content` text COMMENT ''开发者回复内容'' AFTER `status`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin_comment', 'reply_at', '`reply_at` datetime DEFAULT NULL COMMENT ''开发者回复时间'' AFTER `reply_content`');

CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin_order', 'commission_rate', '`commission_rate` decimal(5,2) unsigned NOT NULL DEFAULT ''0.00'' COMMENT ''平台抽成比例(%)'' AFTER `price`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin_order', 'commission_amount', '`commission_amount` decimal(10,2) unsigned NOT NULL DEFAULT ''0.00'' COMMENT ''平台抽成金额'' AFTER `commission_rate`');
CALL SF_ADD_COLUMN_IF_MISSING('SF_plugin_order', 'developer_income', '`developer_income` decimal(10,2) unsigned NOT NULL DEFAULT ''0.00'' COMMENT ''开发者收入'' AFTER `commission_amount`');

CALL SF_ADD_COLUMN_IF_MISSING('SF_notification', 'link', '`link` varchar(255) DEFAULT '''' COMMENT ''跳转链接'' AFTER `type`');

DROP PROCEDURE IF EXISTS SF_ADD_COLUMN_IF_MISSING;
