-- Remove the one-to-one constraints and restore QQ bindings recorded by the migration.
-- Use the full database backup for a complete point-in-time rollback.

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user' AND INDEX_NAME = 'uk_user_qq') > 0,
  'ALTER TABLE `SF_user` DROP INDEX `uk_user_qq`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user' AND INDEX_NAME = 'uk_user_username') > 0,
  'ALTER TABLE `SF_user` DROP INDEX `uk_user_username`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user_social_identity' AND INDEX_NAME = 'uk_social_identity_once') > 0,
  'ALTER TABLE `SF_user_social_identity` DROP INDEX `uk_social_identity_once`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user_social_identity' AND INDEX_NAME = 'uk_social_user_once') > 0,
  'ALTER TABLE `SF_user_social_identity` DROP INDEX `uk_social_user_once`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_admin' AND INDEX_NAME = 'uk_admin_qq') > 0,
  'ALTER TABLE `SF_admin` DROP INDEX `uk_admin_qq`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_admin' AND INDEX_NAME = 'uk_admin_access_token') > 0,
  'ALTER TABLE `SF_admin` DROP INDEX `uk_admin_access_token`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `SF_user` u
JOIN `SF_qq_account_uniqueness_audit` a ON a.`detached_user_id` = u.`id`
SET u.`qq` = a.`qq`;

INSERT IGNORE INTO `SF_user_social_identity` (`identity_id`, `user_id`, `created_at`)
SELECT a.`identity_id`, a.`detached_user_id`, a.`created_at`
FROM `SF_qq_account_uniqueness_audit` a
WHERE a.`identity_id` IS NOT NULL;

DROP TABLE IF EXISTS `SF_qq_account_uniqueness_audit`;
