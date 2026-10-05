-- Quality review migration: add indexes used by dashboards, lists, and permission-scoped queries.
-- Safe to run repeatedly on MySQL 5.7+/8.0+. It does not modify or delete data.

SET @db_name = DATABASE();

SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'QH_auth' AND INDEX_NAME = 'idx_user_addtime'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `QH_auth` ADD INDEX `idx_user_addtime` (`userid`, `addtime`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'QH_cdkey' AND INDEX_NAME = 'idx_user_addtime'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `QH_cdkey` ADD INDEX `idx_user_addtime` (`userid`, `addtime`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'QH_user' AND INDEX_NAME = 'idx_parent_addtime'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `QH_user` ADD INDEX `idx_parent_addtime` (`userid`, `addtime`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'QH_user' AND INDEX_NAME = 'idx_app_integral'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `QH_user` ADD INDEX `idx_app_integral` (`appid`, `integral`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'QH_user' AND INDEX_NAME = 'idx_app_balance'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `QH_user` ADD INDEX `idx_app_balance` (`appid`, `balance`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'QH_log' AND INDEX_NAME = 'idx_actor_admin_time'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `QH_log` ADD INDEX `idx_actor_admin_time` (`username`, `is_admin`, `create_time`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'QH_order' AND INDEX_NAME = 'idx_status_addtime'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `QH_order` ADD INDEX `idx_status_addtime` (`status`, `addtime`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
