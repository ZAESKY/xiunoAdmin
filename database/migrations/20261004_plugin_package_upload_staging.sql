-- Durable, actor-bound staging records for plugin ZIP uploads.
-- The table makes private OSS uploads publishable without exposing a URL and
-- allows abandoned uploads to be removed safely after their token expires.

CREATE TABLE IF NOT EXISTS `QH_plugin_package_upload` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '上传记录ID',
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '一次性上传凭证SHA-256',
  `actor_type` varchar(10) NOT NULL COMMENT 'user/admin',
  `actor_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上传者ID',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT 'local/oss',
  `file_path` varchar(500) NOT NULL DEFAULT '' COMMENT '本地私有文件路径',
  `package_object_key` varchar(500) NOT NULL DEFAULT '' COMMENT 'OSS对象Key',
  `package_file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `package_file_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '文件字节数',
  `package_mime_type` varchar(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `package_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT 'SHA-256',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/cleaning/cleaned/consumed',
  `consumed_plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '最终关联插件ID',
  `expires_at` datetime NOT NULL COMMENT '凭证过期时间',
  `consumed_at` datetime DEFAULT NULL COMMENT '提交发布时间',
  `cleaned_at` datetime DEFAULT NULL COMMENT '孤儿文件清理时间',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_actor_status` (`actor_type`,`actor_id`,`status`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_consumed_plugin` (`consumed_plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件包待提交上传记录';

SELECT '20261004_plugin_package_upload_staging applied' AS migration_result;
