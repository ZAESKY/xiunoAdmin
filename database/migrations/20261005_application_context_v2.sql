-- 应用上下文 v2：产品、发布规则、QQ OAuth 账号都以应用为业务边界。
-- 幂等，可重复执行。

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
    'QH_app', 'product_id',
    "varchar(64) NULL DEFAULT NULL COMMENT '对外稳定产品标识' AFTER `name`"
);
CALL qh_add_column_if_missing(
    'QH_app', 'package_profile',
    "varchar(32) NOT NULL DEFAULT 'generic' COMMENT '发布包校验规则 generic/xiuno_theme' AFTER `product_id`"
);
CALL qh_add_index_if_missing(
    'QH_app', 'uk_app_product_id',
    'UNIQUE KEY `uk_app_product_id` (`product_id`)'
);

-- 已有轻鸿主题保持原产品身份；其它历史应用由管理员编辑后显式补齐。
UPDATE `QH_app`
SET `product_id` = 'zaesky_theme_light', `package_profile` = 'xiuno_theme'
WHERE `id` = 1 AND (`product_id` IS NULL OR `product_id` = '' OR `product_id` = 'zaesky_theme_light');

CALL qh_add_column_if_missing(
    'QH_user_social_identity', 'app_id',
    "int(11) unsigned NOT NULL DEFAULT 0 COMMENT '关联账号所属应用ID' AFTER `user_id`"
);

-- 应用归属来自服务端用户记录，绝不从 OAuth 回调参数推导。
UPDATE `QH_user_social_identity` usi
JOIN `QH_user` u ON u.`id` = usi.`user_id`
SET usi.`app_id` = u.`appid`
WHERE usi.`app_id` = 0 OR usi.`app_id` <> u.`appid`;

CALL qh_drop_index_if_exists('QH_user_social_identity', 'uk_social_identity_once');
CALL qh_add_index_if_missing(
    'QH_user_social_identity', 'uk_social_identity_app',
    'UNIQUE KEY `uk_social_identity_app` (`identity_id`,`app_id`)'
);
CALL qh_add_index_if_missing(
    'QH_user_social_identity', 'idx_social_app',
    'KEY `idx_social_app` (`app_id`)'
);

CALL qh_add_column_if_missing(
    'QH_plugin', 'app_id',
    "int(11) unsigned NOT NULL DEFAULT 1 COMMENT '所属应用ID' AFTER `id`"
);
UPDATE `QH_plugin` p
LEFT JOIN `QH_user` u ON u.`id` = p.`user_id`
SET p.`app_id` = COALESCE(NULLIF(u.`appid`, 0), NULLIF(p.`app_id`, 0), 1);
CALL qh_drop_index_if_exists('QH_plugin', 'slug');
CALL qh_add_index_if_missing(
    'QH_plugin', 'uk_plugin_app_slug',
    'UNIQUE KEY `uk_plugin_app_slug` (`app_id`,`slug`)'
);
CALL qh_add_index_if_missing(
    'QH_plugin', 'idx_plugin_app_status',
    'KEY `idx_plugin_app_status` (`app_id`,`status`,`sort`)'
);

-- 新发布流程只保留完整 release；差分包由完整包清单自动生成。
UPDATE `QH_version` SET `type` = 0 WHERE `type` <> 0;

DROP PROCEDURE IF EXISTS qh_add_index_if_missing;
DROP PROCEDURE IF EXISTS qh_drop_index_if_exists;
DROP PROCEDURE IF EXISTS qh_add_column_if_missing;

SELECT '20261005_application_context_v2 applied' AS migration_result;
