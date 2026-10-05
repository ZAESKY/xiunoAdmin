-- Migration: QH_user.is_developer field + QH_loginlog table
-- 修复 1: QH_user 表缺 is_developer 字段(在 QH_Auth.sql 中已定义,但部分老库未升级)
-- 修复 2: QH_loginlog 登录日志表(已在部分环境中存在但初始化 SQL 未包含)
-- Safe to run multiple times.
-- 兼容 MySQL 5.7+/8.0+

DELIMITER $$

DROP PROCEDURE IF EXISTS qh_add_column_if_missing $$
CREATE PROCEDURE qh_add_column_if_missing(
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

DELIMITER ;

-- 1. QH_user.is_developer
CALL qh_add_column_if_missing(
    'QH_user', 'is_developer',
    "tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为开发者'",
    'appid'
);

-- 2. QH_loginlog 表(若不存在则创建,与部分环境下已存在的字段一致)
CREATE TABLE IF NOT EXISTS `QH_loginlog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `username` varchar(100) NOT NULL DEFAULT '' COMMENT '登录名',
  `power` varchar(20) NOT NULL DEFAULT '' COMMENT '权限类型:admin/user',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=成功 0=失败',
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT '登录IP',
  `create_time` datetime DEFAULT NULL COMMENT '登录时间',
  PRIMARY KEY (`id`),
  KEY `idx_uid_power_status` (`uid`,`power`,`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志表';

DROP PROCEDURE IF EXISTS qh_add_column_if_missing;
