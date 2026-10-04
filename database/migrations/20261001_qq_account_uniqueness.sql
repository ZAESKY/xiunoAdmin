-- Enforce one QQ identity / legacy QQ number per local account.
-- Existing duplicate accounts are preserved. Only the QQ binding is removed
-- from secondary accounts; usernames, passwords, balances and history remain.

CREATE TABLE IF NOT EXISTS `SF_qq_account_uniqueness_audit` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `qq` varchar(20) NOT NULL,
  `primary_user_id` int(11) unsigned NOT NULL,
  `detached_user_id` int(11) unsigned NOT NULL,
  `identity_id` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_detached_user` (`detached_user_id`),
  KEY `idx_primary_user` (`primary_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='QQ账号唯一化审计记录';

ALTER TABLE `SF_user`
  MODIFY `qq` varchar(20) NULL DEFAULT NULL;
ALTER TABLE `SF_admin`
  MODIFY `qq` varchar(20) NULL DEFAULT NULL,
  MODIFY `access_token` varchar(128) NULL DEFAULT NULL;

START TRANSACTION;

UPDATE `SF_user` SET `qq` = NULL WHERE `qq` IS NOT NULL AND LENGTH(TRIM(`qq`)) = 0;
UPDATE `SF_admin` SET `qq` = NULL WHERE `qq` IS NOT NULL AND LENGTH(TRIM(`qq`)) = 0;
UPDATE `SF_admin` SET `access_token` = NULL
WHERE `access_token` IS NOT NULL AND LENGTH(TRIM(`access_token`)) = 0;

DROP TEMPORARY TABLE IF EXISTS `tmp_qq_primary`;
CREATE TEMPORARY TABLE `tmp_qq_primary` (
  `qq` varchar(20) NOT NULL,
  `primary_user_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`qq`)
) ENGINE=InnoDB;

INSERT INTO `tmp_qq_primary` (`qq`, `primary_user_id`)
SELECT `qq`, CAST(SUBSTRING_INDEX(
    GROUP_CONCAT(`id` ORDER BY
      `oauth_links` DESC,
      `business_rows` DESC,
      `balance` DESC,
      `integral` DESC,
      `power` DESC,
      `status` DESC,
      `id` ASC
    ), ',', 1
  ) AS UNSIGNED)
FROM (
  SELECT
    u.`id`, u.`qq`, u.`status`, u.`balance`, u.`integral`, u.`power`,
    (SELECT COUNT(*) FROM `SF_user_social_identity` usi WHERE usi.`user_id` = u.`id`) AS `oauth_links`,
    (
      (SELECT COUNT(*) FROM `SF_auth` a WHERE a.`bindingid` = u.`id`) +
      (SELECT COUNT(*) FROM `SF_auth` a WHERE a.`userid` = u.`id`) +
      (SELECT COUNT(*) FROM `SF_order` o WHERE o.`userid` = u.`id`) +
      (SELECT COUNT(*) FROM `SF_pay` p WHERE p.`userid` = u.`id`) +
      (SELECT COUNT(*) FROM `SF_plugin_purchase` pp WHERE pp.`user_id` = u.`id`) +
      (SELECT COUNT(*) FROM `SF_user` child WHERE child.`userid` = u.`id`)
    ) AS `business_rows`
  FROM `SF_user` u
  WHERE u.`qq` IS NOT NULL AND LENGTH(TRIM(u.`qq`)) > 0
) ranked
GROUP BY `qq`
HAVING COUNT(*) > 1;

INSERT IGNORE INTO `SF_qq_account_uniqueness_audit`
  (`qq`, `primary_user_id`, `detached_user_id`, `identity_id`, `created_at`)
SELECT p.`qq`, p.`primary_user_id`, u.`id`, MIN(usi.`identity_id`), NOW()
FROM `tmp_qq_primary` p
JOIN `SF_user` u ON u.`qq` = p.`qq` AND u.`id` <> p.`primary_user_id`
LEFT JOIN `SF_user_social_identity` usi ON usi.`user_id` = u.`id`
GROUP BY p.`qq`, p.`primary_user_id`, u.`id`;

-- Keep an OAuth mapping only on the selected primary account for each QQ.
DELETE usi
FROM `SF_user_social_identity` usi
JOIN `SF_user` u ON u.`id` = usi.`user_id`
JOIN `tmp_qq_primary` p ON p.`qq` = u.`qq`
WHERE u.`id` <> p.`primary_user_id`;

-- Preserve every secondary account but remove its duplicated QQ binding.
UPDATE `SF_user` u
JOIN `tmp_qq_primary` p ON p.`qq` = u.`qq`
SET u.`qq` = NULL
WHERE u.`id` <> p.`primary_user_id`;

-- Defensive cleanup for historical mappings not represented by the QQ field.
DROP TEMPORARY TABLE IF EXISTS `tmp_identity_primary`;
CREATE TEMPORARY TABLE `tmp_identity_primary` AS
SELECT `identity_id`, MIN(`user_id`) AS `keep_user_id`
FROM `SF_user_social_identity`
GROUP BY `identity_id`
HAVING COUNT(*) > 1;

DELETE usi
FROM `SF_user_social_identity` usi
JOIN `tmp_identity_primary` p ON p.`identity_id` = usi.`identity_id`
WHERE usi.`user_id` <> p.`keep_user_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_user_identity_primary`;
CREATE TEMPORARY TABLE `tmp_user_identity_primary` AS
SELECT `user_id`, MIN(`identity_id`) AS `keep_identity_id`
FROM `SF_user_social_identity`
GROUP BY `user_id`
HAVING COUNT(*) > 1;

DELETE usi
FROM `SF_user_social_identity` usi
JOIN `tmp_user_identity_primary` p ON p.`user_id` = usi.`user_id`
WHERE usi.`identity_id` <> p.`keep_identity_id`;

COMMIT;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user' AND INDEX_NAME = 'uk_user_qq') = 0,
  'ALTER TABLE `SF_user` ADD UNIQUE KEY `uk_user_qq` (`qq`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user' AND INDEX_NAME = 'uk_user_username') = 0,
  'ALTER TABLE `SF_user` ADD UNIQUE KEY `uk_user_username` (`username`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user_social_identity' AND INDEX_NAME = 'uk_social_identity_once') = 0,
  'ALTER TABLE `SF_user_social_identity` ADD UNIQUE KEY `uk_social_identity_once` (`identity_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user_social_identity' AND INDEX_NAME = 'uk_social_user_once') = 0,
  'ALTER TABLE `SF_user_social_identity` ADD UNIQUE KEY `uk_social_user_once` (`user_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_admin' AND INDEX_NAME = 'uk_admin_qq') = 0,
  'ALTER TABLE `SF_admin` ADD UNIQUE KEY `uk_admin_qq` (`qq`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_admin' AND INDEX_NAME = 'uk_admin_access_token') = 0,
  'ALTER TABLE `SF_admin` ADD UNIQUE KEY `uk_admin_access_token` (`access_token`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TEMPORARY TABLE IF EXISTS `tmp_qq_primary`;
DROP TEMPORARY TABLE IF EXISTS `tmp_identity_primary`;
DROP TEMPORARY TABLE IF EXISTS `tmp_user_identity_primary`;
