-- Plugin version history/resources and OSS storage fields.
-- Safe to run multiple times on an existing database.

DELIMITER $$

DROP PROCEDURE IF EXISTS sf_add_column_if_missing $$
CREATE PROCEDURE sf_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT,
    IN p_after VARCHAR(64)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        IF p_after IS NOT NULL AND p_after != '' THEN
            SET @sql = CONCAT(@sql, ' AFTER `', p_after, '`');
        END IF;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sf_add_index_if_missing $$
CREATE PROCEDURE sf_add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL sf_add_column_if_missing('SF_plugin', 'cover', "varchar(255) DEFAULT '' COMMENT '插件封面图URL'", 'images');
CALL sf_add_column_if_missing('SF_plugin', 'related_plugin_id', "int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关联插件ID，可选'", 'origin_note');
CALL sf_add_column_if_missing('SF_plugin', 'storage_driver', "varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss'", 'related_plugin_id');
CALL sf_add_column_if_missing('SF_plugin', 'package_object_key', "varchar(500) DEFAULT '' COMMENT '插件包OSS对象Key'", 'file_path');
CALL sf_add_column_if_missing('SF_plugin', 'package_file_name', "varchar(255) DEFAULT '' COMMENT '插件包原始文件名'", 'package_object_key');
CALL sf_add_column_if_missing('SF_plugin', 'package_mime_type', "varchar(100) DEFAULT '' COMMENT '插件包MIME类型'", 'file_size');
CALL sf_add_column_if_missing('SF_plugin', 'icon_object_key', "varchar(500) DEFAULT '' COMMENT '图标OSS对象Key'", 'file_hash');
CALL sf_add_column_if_missing('SF_plugin', 'cover_object_key', "varchar(500) DEFAULT '' COMMENT '封面OSS对象Key'", 'icon_object_key');
CALL sf_add_column_if_missing('SF_plugin', 'update_description', "text COMMENT '最新版本更新说明'", 'cover_object_key');
CALL sf_add_column_if_missing('SF_plugin', 'publish_type', "tinyint(1) NOT NULL DEFAULT '0' COMMENT '发布类型:0=立即发布,1=定时发布'", 'published_at');
CALL sf_add_column_if_missing('SF_plugin', 'publish_time', "datetime DEFAULT NULL COMMENT '定时发布时间'", 'publish_type');
CALL sf_add_index_if_missing('SF_plugin', 'related_plugin_id', 'INDEX `related_plugin_id` (`related_plugin_id`)');

CREATE TABLE IF NOT EXISTS `SF_plugin_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '版本记录ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `version` varchar(50) NOT NULL DEFAULT '' COMMENT '版本号',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `package_path` varchar(500) DEFAULT '' COMMENT '本地插件包路径或URL',
  `package_object_key` varchar(500) DEFAULT '' COMMENT '插件包OSS对象Key',
  `package_file_name` varchar(255) DEFAULT '' COMMENT '插件包原始文件名',
  `package_file_size` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '插件包大小',
  `package_mime_type` varchar(100) DEFAULT '' COMMENT '插件包MIME类型',
  `package_hash` varchar(64) DEFAULT '' COMMENT '插件包SHA-256哈希',
  `update_description` text COMMENT '更新说明',
  `created_by` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建人用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_version` (`plugin_id`,`version`),
  KEY `idx_plugin_id` (`plugin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件版本历史表';

CREATE TABLE IF NOT EXISTS `SF_plugin_resources` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '资源ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件ID',
  `version_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '插件版本ID',
  `resource_type` varchar(50) NOT NULL DEFAULT '' COMMENT '资源类型:icon/cover/package/attachment',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `url` varchar(500) DEFAULT '' COMMENT '资源访问URL或本地路径',
  `object_key` varchar(500) DEFAULT '' COMMENT 'OSS对象Key',
  `file_name` varchar(255) DEFAULT '' COMMENT '原始文件名',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '文件大小',
  `mime_type` varchar(100) DEFAULT '' COMMENT 'MIME类型',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_by` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建人用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_plugin_id` (`plugin_id`),
  KEY `idx_version_id` (`version_id`),
  KEY `idx_resource_type` (`resource_type`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件资源表';

DROP PROCEDURE IF EXISTS sf_add_column_if_missing;
DROP PROCEDURE IF EXISTS sf_add_index_if_missing;
