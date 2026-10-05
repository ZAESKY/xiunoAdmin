-- Migration: 应用级公开引导安装包元数据
-- 幂等，可重复执行；安装包文件本身保存在应用专属下载目录中。

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

DELIMITER ;

CALL qh_add_column_if_missing(
    'QH_app', 'installer_storage_driver',
    "varchar(20) NOT NULL DEFAULT 'local' COMMENT '安装包存储驱动 local/oss' AFTER `download_file`"
);
CALL qh_add_column_if_missing(
    'QH_app', 'installer_object_key',
    "varchar(500) NOT NULL DEFAULT '' COMMENT '安装包 OSS 对象 Key' AFTER `installer_storage_driver`"
);
CALL qh_add_column_if_missing(
    'QH_app', 'installer_file_name',
    "varchar(255) NOT NULL DEFAULT '' COMMENT '公开引导安装包原始文件名' AFTER `installer_object_key`"
);
CALL qh_add_column_if_missing(
    'QH_app', 'installer_sha256',
    "char(64) NOT NULL DEFAULT '' COMMENT '公开引导安装包 SHA-256' AFTER `installer_file_name`"
);
CALL qh_add_column_if_missing(
    'QH_app', 'installer_size',
    "bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '公开引导安装包字节数' AFTER `installer_sha256`"
);
CALL qh_add_column_if_missing(
    'QH_app', 'installer_uploaded_at',
    "datetime NULL DEFAULT NULL COMMENT '公开引导安装包上传时间' AFTER `installer_size`"
);

DROP PROCEDURE IF EXISTS qh_add_column_if_missing;

SELECT '20261002_application_installer_packages applied' AS migration_result;
