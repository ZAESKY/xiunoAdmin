-- 为 QH_notification 表添加 link 字段
-- 执行时间: 2026-05-03
-- 说明: 兼容新库初始化后的重复执行。

SET @qh_has_notification_link := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'QH_notification'
    AND COLUMN_NAME = 'link'
);

SET @qh_sql := IF(
  @qh_has_notification_link = 0,
  'ALTER TABLE `QH_notification` ADD COLUMN `link` varchar(255) DEFAULT '''' COMMENT ''跳转链接'' AFTER `type`',
  'SELECT 1'
);
PREPARE qh_stmt FROM @qh_sql;
EXECUTE qh_stmt;
DEALLOCATE PREPARE qh_stmt;
