DELETE FROM `SF_config` WHERE `name` IN (
  'sms_enabled','sms_access_key_id','sms_access_key_secret','sms_sign_name',
  'sms_template_code','sms_template_login_register','sms_template_phone_change',
  'sms_template_password_reset','sms_template_phone_bind','sms_template_phone_verify',
  'sms_code_ttl','sms_daily_limit','sms_require_withdraw',
  'sms_require_rebate','sms_require_plugin_reward'
);

DROP TABLE IF EXISTS `SF_sms_audit`;
DROP TABLE IF EXISTS `SF_user_phone_identity`;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_user' AND COLUMN_NAME='phone_verified_source') > 0,
  'ALTER TABLE `SF_user` DROP COLUMN `phone_verified_source`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_user' AND COLUMN_NAME='phone_verified_at') > 0,
  'ALTER TABLE `SF_user` DROP COLUMN `phone_verified_at`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT '20261004_qq_sms_registration rolled back' AS migration_result;
