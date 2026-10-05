-- Migration: P0 安全加固
-- 对应审计条目：A-05 / A-06 / A-07 / A-09 / A-17 / A-25
--
-- 内容：
--   1. 新增 QH_download_ticket —— 一次性下载票据（替换 md5(uniqid()) 缓存凭证）
--   2. 新增 QH_app.auth_enforce —— 授权判定模式（1 强制 / 2 监控）
--   3. 新增 7 项系统配置开关
--
-- 幂等：可重复执行。
-- 兼容 MySQL 5.7+/8.0+
--
-- ⚠ 重要：本迁移会把「已存在的应用」统一置为 auth_enforce = 2（监控模式），
--   保证上线瞬间不会因为授权判定收紧而误伤任何现网客户。
--   观察日志（关键字 [QH-API][auth-monitor]）确认无误后，执行下面一行切到强制拦截：
--       UPDATE `QH_app` SET `auth_enforce` = 1;
--   在切换之前，A-06 的绕过风险仍然存在。

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


-- ============================================================
-- 1. 一次性下载票据表 (A-07)
-- ============================================================
-- 设计要点：
--   - ticket_hash 存 SHA-256，库泄露不产生可直接使用的票据
--   - payload 只放 ID 引用，不存 authcode（A-25）
--   - ip_hash 为 HMAC 结果，审计表中不出现明文 IP
--   - used + expires_at 支持单次消费与过期回收
CREATE TABLE IF NOT EXISTS `QH_download_ticket` (
  `id`          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_hash` char(64)   NOT NULL DEFAULT '' COMMENT '票据 SHA-256，明文不落库',
  `appid`       int(11)    NOT NULL DEFAULT 0  COMMENT '所属应用',
  `auth_id`     int(11)    NOT NULL DEFAULT 0  COMMENT '授权记录 ID',
  `ip_hash`     char(64)   NOT NULL DEFAULT '' COMMENT '签发时来源 IP 的 HMAC',
  `payload`     varchar(512) NOT NULL DEFAULT '' COMMENT 'ID 引用载荷(JSON)',
  `used`        tinyint(1) NOT NULL DEFAULT 0  COMMENT '0 未使用 1 已消费',
  `used_at`     datetime   NULL DEFAULT NULL,
  `expires_at`  datetime   NOT NULL,
  `created_at`  datetime   NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_hash` (`ticket_hash`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_auth` (`auth_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一次性更新包下载票据';


-- ============================================================
-- 2. QH_app.auth_enforce (A-06)
-- ============================================================
-- 先以 DEFAULT 2 建列 —— 存量应用自动进入监控模式，上线零影响；
-- 随后把列默认值改为 1 —— 之后新建的应用默认强制拦截。
CALL qh_add_column_if_missing(
    'QH_app', 'auth_enforce',
    "tinyint(1) NOT NULL DEFAULT 2 COMMENT '授权判定模式:1=强制拦截,2=监控放行'",
    'pirate_msg_switch'
);

ALTER TABLE `QH_app` ALTER COLUMN `auth_enforce` SET DEFAULT 1;


-- ============================================================
-- 3. 系统配置开关
-- ============================================================

-- A-07：下载票据是否绑定来源 IP。多出口 IP 的部署可置 0。
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'download_ticket_bind_ip', 'system', '下载票据绑定 IP', '开启后下载票据只能由签发时的同一 IP 使用；若服务器有多个出口 IP 导致下载失败，可关闭', 'bool', '1', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'download_ticket_bind_ip');

-- A-07：历史 md5 凭证过渡开关。老凭证最长存活 12 小时，上线满 12 小时后置 0 收口。
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'download_legacy_sign_enabled', 'system', '兼容历史下载凭证', '过渡开关。上线满 12 小时（老凭证全部过期）后请置为 0，彻底关闭可重放的历史凭证通道', 'bool', '1', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'download_legacy_sign_enabled');

-- A-09：插件 API 签名与 nonce 是否必填
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'plugin_api_sign_required', 'system', '插件API强制签名', '开启后 sign 与 nonce 为必填。关闭会重新允许调用方省略字段绕过防重放与完整性校验，仅供应急排查', 'bool', '1', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'plugin_api_sign_required');

-- A-09：签名算法。auto = 同时接受 HMAC-SHA256 与历史 md5；hmac = 只接受 HMAC-SHA256
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'plugin_api_sign_algo', 'system', '插件API签名算法', 'auto=过渡期同时接受 HMAC-SHA256 与历史 md5；hmac=只接受 HMAC-SHA256。客户端全部升级后请改为 hmac', 'string', 'auto', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'plugin_api_sign_algo');

-- A-05：插件 API 用户令牌强校验
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'plugin_api_user_strict', 'system', '插件API用户令牌强校验', '开启后必须携带有效 user_token。关闭会重新允许任意冒充 user_id 下载已购插件，仅供应急排查', 'bool', '1', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'plugin_api_user_strict');

-- A-02 部分缓解：下载基址与协议
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'download_base_url', 'system', '更新下载基址', '留空则按当前请求协议自动推导。建议填写完整 HTTPS 地址，例如 https://auth.example.com', 'string', '', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'download_base_url');

INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'force_https_download', 'system', '强制 HTTPS 下载', '开启后下发给客户端的下载地址一律使用 https，避免更新包经明文信道传输', 'bool', '0', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'force_https_download');


DROP PROCEDURE IF EXISTS qh_add_column_if_missing;
