-- 为 SF_notification 表添加 link 字段
-- 执行时间: 2026-05-03
-- 说明: 兼容新库初始化后的重复执行。

SET @sf_has_notification_link := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'SF_notification'
    AND COLUMN_NAME = 'link'
);

SET @sf_sql := IF(
  @sf_has_notification_link = 0,
  'ALTER TABLE `SF_notification` ADD COLUMN `link` varchar(255) DEFAULT '''' COMMENT ''跳转链接'' AFTER `type`',
  'SELECT 1'
);
PREPARE sf_stmt FROM @sf_sql;
EXECUTE sf_stmt;
DEALLOCATE PREPARE sf_stmt;
