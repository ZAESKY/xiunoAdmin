-- Migration: 程序补丁与主题版本/build 精确绑定
-- 幂等，可重复执行；旧补丁默认 theme_build=0，不会被新版客户端误下发。

DELIMITER $$

DROP PROCEDURE IF EXISTS qh_add_column_if_missing $$
CREATE PROCEDURE qh_add_column_if_missing(
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

DROP PROCEDURE IF EXISTS qh_add_index_if_missing $$
CREATE PROCEDURE qh_add_index_if_missing(
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

CALL qh_add_column_if_missing(
    'QH_patch', 'theme_build',
    "int(11) NOT NULL DEFAULT 0 COMMENT '绑定的主题数字 build' AFTER `level`"
);
CALL qh_add_column_if_missing(
    'QH_patch', 'theme_edition',
    "varchar(64) NOT NULL DEFAULT '' COMMENT '绑定的主题展示版本' AFTER `theme_build`"
);

-- 旧索引不含 build，会阻止同一个 patch/revision 为不同主题版本分别发布。
CALL qh_drop_index_if_exists('QH_patch', 'uk_patch');
CALL qh_drop_index_if_exists('QH_patch', 'idx_status');
CALL qh_add_index_if_missing(
    'QH_patch', 'uk_patch',
    'UNIQUE KEY `uk_patch` (`product_id`,`theme_build`,`patch_id`,`revision`)'
);
CALL qh_add_index_if_missing(
    'QH_patch', 'idx_status',
    'KEY `idx_status` (`product_id`,`theme_build`,`status`,`level`)'
);

DROP PROCEDURE IF EXISTS qh_add_index_if_missing;
DROP PROCEDURE IF EXISTS qh_drop_index_if_exists;
DROP PROCEDURE IF EXISTS qh_add_column_if_missing;

SELECT '20261003_program_patch_version_binding applied' AS migration_result;
