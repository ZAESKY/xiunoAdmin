-- Rollback: 应用级公开引导安装包元数据
-- 仅删除元数据列，不删除磁盘中的安装包，便于恢复。

DELIMITER $$

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

CALL qh_drop_column_if_exists('QH_app', 'installer_uploaded_at');
CALL qh_drop_column_if_exists('QH_app', 'installer_size');
CALL qh_drop_column_if_exists('QH_app', 'installer_sha256');
CALL qh_drop_column_if_exists('QH_app', 'installer_file_name');

DROP PROCEDURE IF EXISTS qh_drop_column_if_exists;

SELECT '20261002_application_installer_packages rolled back' AS migration_result;
