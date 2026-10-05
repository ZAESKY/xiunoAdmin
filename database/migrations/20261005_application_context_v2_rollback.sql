-- 回滚到单应用 QQ 身份模型。多应用关联只能保留每个身份最早的一条。

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
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

DROP TEMPORARY TABLE IF EXISTS `tmp_identity_keep_user`;
CREATE TEMPORARY TABLE `tmp_identity_keep_user` AS
SELECT `identity_id`, MIN(`user_id`) AS `user_id`
FROM `QH_user_social_identity`
GROUP BY `identity_id`;

DELETE usi FROM `QH_user_social_identity` usi
JOIN `tmp_identity_keep_user` keep_row ON keep_row.`identity_id` = usi.`identity_id`
WHERE usi.`user_id` <> keep_row.`user_id`;

CALL qh_drop_index_if_exists('QH_user_social_identity', 'uk_social_identity_app');
CALL qh_drop_index_if_exists('QH_user_social_identity', 'idx_social_app');
CALL qh_add_index_if_missing(
    'QH_user_social_identity', 'uk_social_identity_once',
    'UNIQUE KEY `uk_social_identity_once` (`identity_id`)'
);
CALL qh_drop_column_if_exists('QH_user_social_identity', 'app_id');

-- 恢复全局 slug 唯一性前，给跨应用重名记录追加应用后缀，避免回滚 DDL 失败。
UPDATE `QH_plugin` p
JOIN (
    SELECT `slug`, MIN(`id`) AS `keep_id`
    FROM `QH_plugin`
    GROUP BY `slug`
    HAVING COUNT(*) > 1
) duplicate_slug ON duplicate_slug.`slug` = p.`slug` AND p.`id` <> duplicate_slug.`keep_id`
SET p.`slug` = CONCAT(LEFT(p.`slug`, 80), '_app', p.`app_id`, '_', p.`id`);
CALL qh_drop_index_if_exists('QH_plugin', 'uk_plugin_app_slug');
CALL qh_drop_index_if_exists('QH_plugin', 'idx_plugin_app_status');
CALL qh_add_index_if_missing('QH_plugin', 'slug', 'UNIQUE KEY `slug` (`slug`)');
CALL qh_drop_column_if_exists('QH_plugin', 'app_id');

CALL qh_drop_index_if_exists('QH_app', 'uk_app_product_id');
CALL qh_drop_column_if_exists('QH_app', 'package_profile');
CALL qh_drop_column_if_exists('QH_app', 'product_id');

DROP TEMPORARY TABLE IF EXISTS `tmp_identity_keep_user`;
DROP PROCEDURE IF EXISTS qh_add_index_if_missing;
DROP PROCEDURE IF EXISTS qh_drop_index_if_exists;
DROP PROCEDURE IF EXISTS qh_drop_column_if_exists;

SELECT '20261005_application_context_v2 rolled back' AS migration_result;
