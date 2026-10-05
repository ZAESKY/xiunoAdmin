-- Rollback: 恢复程序补丁旧索引并移除主题版本绑定列。
-- 若不同 theme_build 下存在同 patch_id/revision，旧唯一索引无法恢复，本回滚会安全失败。

DELIMITER $$

DROP PROCEDURE IF EXISTS qh_drop_index_if_exists $$
CREATE PROCEDURE qh_drop_index_if_exists(IN p_table VARCHAR(64), IN p_index VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS qh_drop_column_if_exists $$
CREATE PROCEDURE qh_drop_column_if_exists(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL qh_drop_index_if_exists('QH_patch', 'uk_patch');
CALL qh_drop_index_if_exists('QH_patch', 'idx_status');

ALTER TABLE `QH_patch`
  ADD UNIQUE KEY `uk_patch` (`product_id`,`patch_id`,`revision`),
  ADD KEY `idx_status` (`product_id`,`status`,`level`);

CALL qh_drop_column_if_exists('QH_patch', 'theme_edition');
CALL qh_drop_column_if_exists('QH_patch', 'theme_build');

DROP PROCEDURE IF EXISTS qh_drop_column_if_exists;
DROP PROCEDURE IF EXISTS qh_drop_index_if_exists;

SELECT '20261003_program_patch_version_binding rolled back' AS migration_result;
