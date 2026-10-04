-- Metadata-only rollback. OSS objects are intentionally not deleted.

DELIMITER $$
DROP PROCEDURE IF EXISTS sf_drop_column_if_exists $$
CREATE PROCEDURE sf_drop_column_if_exists(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND COLUMN_NAME=p_column)
    THEN
        SET @sql=CONCAT('ALTER TABLE `',p_table,'` DROP COLUMN `',p_column,'`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

CALL sf_drop_column_if_exists('SF_patch', 'package_object_key');
CALL sf_drop_column_if_exists('SF_patch', 'storage_driver');
CALL sf_drop_column_if_exists('SF_release', 'package_object_key');
CALL sf_drop_column_if_exists('SF_release', 'storage_driver');
CALL sf_drop_column_if_exists('SF_version', 'package_size');
CALL sf_drop_column_if_exists('SF_version', 'package_sha256');
CALL sf_drop_column_if_exists('SF_version', 'package_object_key');
CALL sf_drop_column_if_exists('SF_version', 'storage_driver');
CALL sf_drop_column_if_exists('SF_app', 'installer_object_key');
CALL sf_drop_column_if_exists('SF_app', 'installer_storage_driver');

DROP PROCEDURE IF EXISTS sf_drop_column_if_exists;
SELECT '20261003_oss_managed_storage rolled back' AS migration_result;
