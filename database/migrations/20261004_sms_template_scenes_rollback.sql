DELETE FROM `SF_config` WHERE `name` IN (
  'sms_template_login_register','sms_template_phone_change',
  'sms_template_password_reset','sms_template_phone_bind','sms_template_phone_verify'
);

UPDATE `SF_config`
SET `title`='验证码模板CODE',
    `tip`='号码认证赠送模板填数字 CODE（如 100001），普通短信模板填 SMS_ 开头的 CODE；模板须包含 ${code}'
WHERE `name`='sms_template_code';

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_sms_audit' AND COLUMN_NAME='template_code') > 0,
  'ALTER TABLE `SF_sms_audit` DROP COLUMN `template_code`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT '20261004_sms_template_scenes rolled back' AS migration_result;
