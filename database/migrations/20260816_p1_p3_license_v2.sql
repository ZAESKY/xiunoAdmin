-- Migration: P1–P3 授权体系 v2
--
-- P1  QH_auth 授权码哈希化（A-13）
-- P2  QH_license / QH_license_site / QH_license_event / QH_offline_activation / QH_trial
-- P3  QH_release / QH_patch（签名发布物与核心兼容补丁）
--
-- 与 v1 完全并行：本迁移不修改 QH_auth 的既有列语义，不删除任何数据，
-- v1 接口（/api.php/Auth/*）行为不受影响。
--
-- 幂等：可重复执行。
-- 兼容 MySQL 5.7+/8.0+

DELIMITER $$

DROP PROCEDURE IF EXISTS qh_add_column_if_missing $$
CREATE PROCEDURE qh_add_column_if_missing(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64),
    IN p_definition TEXT,   IN p_after VARCHAR(64)
)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        IF p_after IS NOT NULL AND p_after != '' THEN
            SET @sql = CONCAT(@sql, ' AFTER `', p_after, '`');
        END IF;
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;


-- ============================================================
-- P1  QH_auth 授权码哈希化（A-13）
-- ============================================================
-- 过渡策略：新增哈希列并回填，明文列暂时保留以保证 v1 接口不中断。
-- 全部客户迁移到 v2 后，执行清理脚本把明文列置空（见文件末尾说明）。

CALL qh_add_column_if_missing('QH_auth', 'authcode_hash',
    "char(64) NOT NULL DEFAULT '' COMMENT '授权码 HMAC-SHA256'", 'authcode');
CALL qh_add_column_if_missing('QH_auth', 'authcode_last4',
    "char(4) NOT NULL DEFAULT '' COMMENT '授权码尾4位，供客服核对'", 'authcode_hash');
CALL qh_add_column_if_missing('QH_auth', 'must_rotate',
    "tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=该授权码由旧的可预测算法生成，应换发'", 'authcode_last4');
CALL qh_add_column_if_missing('QH_auth', 'pepper_version',
    "smallint(6) NOT NULL DEFAULT 1 COMMENT 'pepper 版本'", 'must_rotate');

-- 尾4位可直接回填；authcode_hash 需要 pepper，由 CLI 完成：
--     php think qh:authcode-backfill
UPDATE `QH_auth`
   SET `authcode_last4` = RIGHT(`authcode`, 4)
 WHERE `authcode_last4` = '' AND `authcode` IS NOT NULL AND `authcode` != '';


-- ============================================================
-- P2  授权主表
-- ============================================================
CREATE TABLE IF NOT EXISTS `QH_license` (
  `id`                    bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `license_id`            char(32)    NOT NULL COMMENT '对外授权标识',
  `product_id`            varchar(64) NOT NULL DEFAULT '' COMMENT '产品标识，如 zaesky_theme_light',
  `appid`                 int(11)     NOT NULL DEFAULT 0,
  `user_id`               int(11)     NOT NULL DEFAULT 0 COMMENT '购买账号',
  `authcode_hash`         char(64)    NOT NULL DEFAULT '' COMMENT '授权码 HMAC，明文不落库',
  `authcode_last4`        char(4)     NOT NULL DEFAULT '',
  `pepper_version`        smallint(6) NOT NULL DEFAULT 1,
  `secret_hash`           char(64)    NOT NULL DEFAULT '' COMMENT 'license_secret 的 SHA-256',
  `secret_enc`            varchar(255) NOT NULL DEFAULT '' COMMENT 'license_secret 的 AEAD 密文(可选)',
  `status`                varchar(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/active/suspended/revoked/expired',
  `permanent`             tinyint(1)  NOT NULL DEFAULT 1 COMMENT '1=永久授权',
  `expires_at`            datetime    NULL DEFAULT NULL,
  `channel`               varchar(16) NOT NULL DEFAULT 'stable' COMMENT 'stable/beta',
  `channel_changed_at`    datetime    NULL DEFAULT NULL,
  `beta_blocked`          tinyint(1)  NOT NULL DEFAULT 0 COMMENT '1=禁止加入测试通道',
  `max_sites`             tinyint(4)  NOT NULL DEFAULT 1 COMMENT '一个授权仅允许一个规范化域名',
  `allow_cross_root`      tinyint(1)  NOT NULL DEFAULT 0 COMMENT '历史兼容字段，单站点模式固定为0',
  `bound_host`            varchar(255) NOT NULL DEFAULT '',
  `bound_at`              datetime    NULL DEFAULT NULL,
  `rebind_limit`          smallint(6) NOT NULL DEFAULT 3,
  `rebind_count`          smallint(6) NOT NULL DEFAULT 0,
  `rebind_cooldown_until` datetime    NULL DEFAULT NULL,
  `migrated_from`         int(11)     NOT NULL DEFAULT 0 COMMENT '来源 QH_auth_legacy.id',
  `source_auth_id`        int(11)     NULL DEFAULT NULL COMMENT '按需迁移来源 QH_auth.id',
  `last_seen_at`          datetime    NULL DEFAULT NULL,
  `created_at`            datetime    NOT NULL,
  `updated_at`            datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_license_id` (`license_id`),
  KEY `idx_authcode_hash` (`authcode_hash`),
  KEY `idx_product_status` (`product_id`,`status`),
  KEY `idx_user` (`user_id`),
  KEY `idx_migrated` (`migrated_from`),
  UNIQUE KEY `uk_product_source_auth` (`product_id`,`source_auth_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='授权主表 v2';


-- ============================================================
-- P2  站点绑定（一个授权仅允许一个规范化域名）
-- ============================================================
CREATE TABLE IF NOT EXISTS `QH_license_site` (
  `id`            bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `license_id`    char(32)    NOT NULL,
  `site_id`       char(64)    NOT NULL COMMENT 'SHA-256(归一化域名|install_uuid)',
  `host`          varchar(255) NOT NULL DEFAULT '',
  `role`          varchar(16) NOT NULL DEFAULT 'primary' COMMENT 'primary/secondary',
  `install_uuid`  varchar(64) NOT NULL DEFAULT '',
  `verify_method` varchar(16) NOT NULL DEFAULT '' COMMENT 'https/http/offline',
  `verified_at`   datetime    NULL DEFAULT NULL,
  `status`        tinyint(1)  NOT NULL DEFAULT 1 COMMENT '1=生效 0=已解绑',
  `first_seen_at` datetime    NULL DEFAULT NULL,
  `last_seen_at`  datetime    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site` (`site_id`),
  KEY `idx_license_role` (`license_id`,`role`,`status`),
  KEY `idx_host` (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='授权站点绑定';


-- ============================================================
-- P2  审计（需求 ⑩：不落完整授权码与明文 IP）
-- ============================================================
CREATE TABLE IF NOT EXISTS `QH_license_event` (
  `id`         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `license_id` char(32)    NOT NULL DEFAULT '',
  `event`      varchar(32) NOT NULL DEFAULT '' COMMENT 'activate/redeem/verify/rebind/channel/ticket/upgrade/offline_issue',
  `result`     varchar(16) NOT NULL DEFAULT '',
  `ip_hash`    char(64)    NOT NULL DEFAULT '' COMMENT 'IP 的 HMAC，非明文',
  `ua_hash`    varchar(32) NOT NULL DEFAULT '',
  `site_id`    char(64)    NOT NULL DEFAULT '',
  `request_id` char(32)    NOT NULL DEFAULT '' COMMENT '幂等键',
  `detail`     varchar(1024) NOT NULL DEFAULT '' COMMENT '已脱敏的字段白名单 JSON',
  `created_at` datetime    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_license_time` (`license_id`,`created_at`),
  KEY `idx_event_time` (`event`,`created_at`),
  KEY `idx_request` (`license_id`,`event`,`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='授权审计日志';


-- ============================================================
-- P2  离线激活记账
-- ============================================================
CREATE TABLE IF NOT EXISTS `QH_offline_activation` (
  `id`         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `license_id` char(32)    NOT NULL,
  `site_id`    char(64)    NOT NULL DEFAULT '',
  `nonce`      char(32)    NOT NULL,
  `host`       varchar(255) NOT NULL DEFAULT '',
  `used`       tinyint(1)  NOT NULL DEFAULT 0,
  `used_at`    datetime    NULL DEFAULT NULL,
  `expires_at` datetime    NOT NULL,
  `created_at` datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nonce` (`nonce`),
  KEY `idx_license` (`license_id`,`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='离线激活凭证记账';


-- ============================================================
-- P2  试用（用户需求 ③：盗版站 7 天体验）
-- ============================================================
CREATE TABLE IF NOT EXISTS `QH_trial` (
  `id`          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_id`     char(64)    NOT NULL,
  `product_id`  varchar(64) NOT NULL DEFAULT '',
  `root_domain` varchar(255) NOT NULL DEFAULT '' COMMENT '按注册根域判重，不按 IP，避免误伤同机房不同客户',
  `host_hash`   char(64)    NOT NULL DEFAULT '',
  `started_at`  datetime    NOT NULL,
  `expires_at`  datetime    NOT NULL,
  `created_at`  datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trial_site` (`site_id`),
  KEY `idx_root` (`root_domain`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='试用登记';


-- ============================================================
-- P3  发布物（签名后的更新包）
-- ============================================================
CREATE TABLE IF NOT EXISTS `QH_release` (
  `id`              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id`      varchar(64) NOT NULL DEFAULT '',
  `build_no`        int(11)     NOT NULL DEFAULT 0 COMMENT '单调递增，版本比较只用它',
  `edition`         varchar(32) NOT NULL DEFAULT '' COMMENT '展示用语义版本',
  `channel`         varchar(16) NOT NULL DEFAULT 'stable',
  `package_file`    varchar(160) NOT NULL DEFAULT '' COMMENT 'app/common/download/v2/ 下的文件名',
  `storage_driver`  varchar(20)  NOT NULL DEFAULT 'local' COMMENT '发布包存储驱动 local/oss',
  `package_object_key` varchar(500) NOT NULL DEFAULT '' COMMENT '发布包 OSS 对象 Key',
  `package_sha256`  char(64)    NOT NULL DEFAULT '',
  `package_size`    bigint(20)  NOT NULL DEFAULT 0,
  `manifest_json`   mediumtext  NULL COMMENT '逐文件哈希与兼容区间',
  `manifest_sig`    varchar(255) NULL DEFAULT NULL COMMENT 'Ed25519 detached 签名',
  `sig_key_id`      varchar(64) NOT NULL DEFAULT '',
  `min_bbs_version` varchar(32) NOT NULL DEFAULT '',
  `max_bbs_version` varchar(32) NOT NULL DEFAULT '',
  `min_php_version` varchar(32) NOT NULL DEFAULT '',
  `is_security`     tinyint(1)  NOT NULL DEFAULT 0,
  `release_note`    text        NULL,
  `download_count`  int(11)     NOT NULL DEFAULT 0,
  `status`          tinyint(1)  NOT NULL DEFAULT 0 COMMENT '0=草稿 1=已发布',
  `published_at`    datetime    NULL DEFAULT NULL,
  `created_at`      datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_build` (`product_id`,`build_no`),
  KEY `idx_channel_status` (`product_id`,`channel`,`status`,`build_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签名发布物 v2';

-- 完整包是权威来源；该表保存由相邻完整清单自动计算出的精确差分。
CREATE TABLE IF NOT EXISTS `QH_release_delta` (
  `id`                 bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id`         varchar(64)  NOT NULL DEFAULT '',
  `from_build`         int(11)      NOT NULL DEFAULT 0,
  `build_no`           int(11)      NOT NULL DEFAULT 0,
  `edition`            varchar(32)  NOT NULL DEFAULT '',
  `channel`            varchar(16)  NOT NULL DEFAULT 'stable',
  `package_file`       varchar(160) NOT NULL DEFAULT '',
  `storage_driver`     varchar(20)  NOT NULL DEFAULT 'local',
  `package_object_key` varchar(500) NOT NULL DEFAULT '',
  `package_sha256`     char(64)     NOT NULL DEFAULT '',
  `package_size`       bigint(20)   NOT NULL DEFAULT 0,
  `manifest_json`      mediumtext   NULL,
  `manifest_sig`       varchar(255) NULL DEFAULT NULL,
  `sig_key_id`         varchar(64)  NOT NULL DEFAULT '',
  `download_count`     int(11)      NOT NULL DEFAULT 0,
  `status`             tinyint(1)   NOT NULL DEFAULT 0,
  `published_at`       datetime     NULL DEFAULT NULL,
  `created_at`         datetime     NOT NULL,
  `updated_at`         datetime     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_channel_range` (`product_id`,`channel`,`from_build`,`build_no`),
  KEY `idx_target_status` (`product_id`,`channel`,`build_no`,`status`),
  KEY `idx_source_status` (`product_id`,`channel`,`from_build`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签名差分发布物 v2';


-- ============================================================
-- P3  Xiuno 核心兼容补丁（用户需求 ⑧⑨）
-- ============================================================
CREATE TABLE IF NOT EXISTS `QH_patch` (
  `id`              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id`      varchar(64) NOT NULL DEFAULT '',
  `patch_id`        varchar(64) NOT NULL DEFAULT '' COMMENT '如 php8-html-safe',
  `revision`        int(11)     NOT NULL DEFAULT 1 COMMENT '同一 patch_id 的修订号',
  `level`           tinyint(1)  NOT NULL DEFAULT 1 COMMENT '1=overwrite 覆写(安全) 2=直写核心文件(需确认)',
  `theme_build`     int(11)     NOT NULL DEFAULT 0 COMMENT '绑定的主题数字 build',
  `theme_edition`   varchar(64) NOT NULL DEFAULT '' COMMENT '绑定的主题展示版本',
  `title`           varchar(128) NOT NULL DEFAULT '',
  `description`     varchar(512) NOT NULL DEFAULT '',
  `package_file`    varchar(160) NOT NULL DEFAULT '' COMMENT 'app/common/download/patch/ 下的文件名',
  `storage_driver`  varchar(20)  NOT NULL DEFAULT 'local' COMMENT '补丁包存储驱动 local/oss',
  `package_object_key` varchar(500) NOT NULL DEFAULT '' COMMENT '补丁包 OSS 对象 Key',
  `package_sha256`  char(64)    NOT NULL DEFAULT '',
  `package_size`    bigint(20)  NOT NULL DEFAULT 0,
  `manifest_json`   mediumtext  NULL COMMENT '含 expect_sha256(level2 用)与逐文件哈希',
  `manifest_sig`    varchar(255) NULL DEFAULT NULL,
  `sig_key_id`      varchar(64) NOT NULL DEFAULT '',
  `min_bbs_version` varchar(32) NOT NULL DEFAULT '',
  `max_bbs_version` varchar(32) NOT NULL DEFAULT '' COMMENT '超出区间自动失效，避免覆盖官方新版修改',
  `min_php_version` varchar(32) NOT NULL DEFAULT '',
  `download_count`  int(11)     NOT NULL DEFAULT 0,
  `status`          tinyint(1)  NOT NULL DEFAULT 0,
  `created_at`      datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_patch` (`product_id`,`theme_build`,`patch_id`,`revision`),
  KEY `idx_status` (`product_id`,`theme_build`,`status`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Xiuno 核心兼容补丁';


-- ============================================================
-- 票据表补列：v2 票据需要关联 license
-- ============================================================
CALL qh_add_column_if_missing('QH_download_ticket', 'license_id',
    "char(32) NOT NULL DEFAULT '' COMMENT 'v2 授权标识'", 'auth_id');


DROP PROCEDURE IF EXISTS qh_add_column_if_missing;

-- ============================================================
-- 后续清理（全部客户迁移到 v2 后再执行，不属于本次迁移）
-- ============================================================
--   UPDATE `QH_auth` SET `authcode` = '' WHERE `authcode_hash` != '';
-- 执行前请确认 v1 接口已下线，否则 v1 的授权码校验会全部失败。
