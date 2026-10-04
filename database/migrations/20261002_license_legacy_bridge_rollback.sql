-- Rollback: 旧 SF_auth 授权按需迁移标记
-- 注意：仅删除来源标记，不删除已经签发的授权、站点绑定或用户数据。

DELIMITER $$

DROP PROCEDURE IF EXISTS sf_drop_index_if_exists $$
CREATE PROCEDURE sf_drop_index_if_exists(IN p_table VARCHAR(64), IN p_index VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sf_drop_column_if_exists $$
CREATE PROCEDURE sf_drop_column_if_exists(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL sf_drop_index_if_exists('SF_license', 'uk_product_source_auth');
CALL sf_drop_column_if_exists('SF_license', 'source_auth_id');

DROP PROCEDURE IF EXISTS sf_drop_index_if_exists;
DROP PROCEDURE IF EXISTS sf_drop_column_if_exists;

SELECT '20261002_license_legacy_bridge rolled back' AS migration_result;
