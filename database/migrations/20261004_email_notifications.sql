-- Business email notification preferences, templates and delivery audit.
-- Idempotent and safe to execute repeatedly on MySQL 5.7+/8.0+.

CREATE TABLE IF NOT EXISTS `QH_notification_email_preference` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_type` varchar(16) NOT NULL COMMENT 'user/admin',
  `owner_id` int(11) unsigned NOT NULL,
  `event_code` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_owner_event` (`owner_type`,`owner_id`,`event_code`),
  KEY `idx_event_enabled` (`event_code`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件通知接收偏好';

-- Preferences are opt-in for users. Existing explicit choices are preserved;
-- only the schema default is corrected for future rows and direct inserts.
ALTER TABLE `QH_notification_email_preference`
  MODIFY COLUMN `enabled` tinyint(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `QH_notification_email_template` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `event_code` varchar(64) NOT NULL,
  `audience` varchar(16) NOT NULL COMMENT 'user/admin',
  `subject` varchar(255) NOT NULL,
  `html_body` mediumtext NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_code` (`event_code`),
  KEY `idx_audience_enabled` (`audience`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业务邮件HTML模板覆盖';

CREATE TABLE IF NOT EXISTS `QH_notification_email_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_code` varchar(64) NOT NULL,
  `recipient_type` varchar(16) NOT NULL,
  `recipient_id` int(11) unsigned NOT NULL DEFAULT 0,
  `email_hash` char(64) NOT NULL,
  `email_masked` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL,
  `error_code` varchar(64) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_time` (`event_code`,`created_at`),
  KEY `idx_recipient_time` (`recipient_type`,`recipient_id`,`created_at`),
  KEY `idx_status_time` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业务邮件发送审计（不保存正文和完整邮箱）';

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'email_notification_enabled','email_notification','业务邮件通知','总开关关闭时仅保留站内消息，不发送审核、交易等业务邮件','bool','0','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name`='email_notification_enabled');

UPDATE `QH_config` SET `group`='email_notification' WHERE `name`='email_notification_enabled';

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '邮件消息通知', 'Set/emailNotification', '', `id`, NOW(), 1, 1
FROM `QH_menu`
WHERE `name`='系统设置' AND `url`='#' AND `parentid`=0 AND `power`=1
  AND NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url`='Set/emailNotification' AND `power`=1)
ORDER BY `id` ASC LIMIT 1;

SET @email_set_parent := (
  SELECT `id` FROM `QH_menu`
  WHERE `name`='系统设置' AND `url`='#' AND `parentid`=0 AND `power`=1
  ORDER BY `id` ASC LIMIT 1
);
UPDATE `QH_menu`
SET `name`='邮件消息通知', `parentid`=@email_set_parent, `status`=1
WHERE `url`='Set/emailNotification' AND `power`=1 AND @email_set_parent IS NOT NULL;

SELECT '20261004_email_notifications applied' AS migration_result;
