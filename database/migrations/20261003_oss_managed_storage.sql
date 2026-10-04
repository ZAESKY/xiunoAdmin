-- Store application installers and version/release packages in OSS when the
-- global OSS switch is enabled. Idempotent and safe to execute repeatedly.

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

DELIMITER ;

CALL sf_add_column_if_missing('SF_app', 'installer_storage_driver',
    "varchar(20) NOT NULL DEFAULT 'local' COMMENT '安装包存储驱动 local/oss' AFTER `download_file`");
CALL sf_add_column_if_missing('SF_app', 'installer_object_key',
    "varchar(500) NOT NULL DEFAULT '' COMMENT '安装包 OSS 对象 Key' AFTER `installer_storage_driver`");
ALTER TABLE `SF_app`
  MODIFY `logo` varchar(500) NOT NULL DEFAULT '/Assets/img/logo.png' COMMENT '应用LOGO或私有OSS媒体网关URL';

CALL sf_add_column_if_missing('SF_version', 'storage_driver',
    "varchar(20) NOT NULL DEFAULT 'local' COMMENT '版本包存储驱动 local/oss' AFTER `download_catalogue`");
CALL sf_add_column_if_missing('SF_version', 'package_object_key',
    "varchar(500) NOT NULL DEFAULT '' COMMENT '版本包 OSS 对象 Key' AFTER `storage_driver`");
CALL sf_add_column_if_missing('SF_version', 'package_sha256',
    "char(64) NOT NULL DEFAULT '' COMMENT '版本包 SHA-256' AFTER `package_object_key`");
CALL sf_add_column_if_missing('SF_version', 'package_size',
    "bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '版本包字节数' AFTER `package_sha256`");

CALL sf_add_column_if_missing('SF_release', 'storage_driver',
    "varchar(20) NOT NULL DEFAULT 'local' COMMENT '发布包存储驱动 local/oss' AFTER `package_file`");
CALL sf_add_column_if_missing('SF_release', 'package_object_key',
    "varchar(500) NOT NULL DEFAULT '' COMMENT '发布包 OSS 对象 Key' AFTER `storage_driver`");

CALL sf_add_column_if_missing('SF_patch', 'storage_driver',
    "varchar(20) NOT NULL DEFAULT 'local' COMMENT '补丁包存储驱动 local/oss' AFTER `package_file`");
CALL sf_add_column_if_missing('SF_patch', 'package_object_key',
    "varchar(500) NOT NULL DEFAULT '' COMMENT '补丁包 OSS 对象 Key' AFTER `storage_driver`");

UPDATE `SF_config`
   SET `value` = '1',
       `tip` = '必须开启；文件回显和授权下载均由后端生成短时签名 URL，新对象强制使用 private ACL'
 WHERE `name` = 'oss_use_private_bucket';

DROP PROCEDURE IF EXISTS sf_add_column_if_missing;

SELECT '20261003_oss_managed_storage applied' AS migration_result;
