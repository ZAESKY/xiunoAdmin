-- Migration: 旧 SF_auth 授权按需迁移到 v2
-- 幂等，可重复执行。现有 v2 授权保持 source_auth_id=NULL，不改变其语义。

DELIMITER $$

DROP PROCEDURE IF EXISTS sf_add_column_if_missing $$
CREATE PROCEDURE sf_add_column_if_missing(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT
)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sf_add_index_if_missing $$
CREATE PROCEDURE sf_add_index_if_missing(
    IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_definition TEXT
)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL sf_add_column_if_missing(
    'SF_license',
    'source_auth_id',
    "int(11) NULL DEFAULT NULL COMMENT '按需迁移来源 SF_auth.id' AFTER `migrated_from`"
);
CALL sf_add_index_if_missing(
    'SF_license',
    'uk_product_source_auth',
    'UNIQUE KEY `uk_product_source_auth` (`product_id`,`source_auth_id`)'
);

DROP PROCEDURE IF EXISTS sf_add_index_if_missing;
DROP PROCEDURE IF EXISTS sf_add_column_if_missing;

SELECT '20261002_license_legacy_bridge applied' AS migration_result;
