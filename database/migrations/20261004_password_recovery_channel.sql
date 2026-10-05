-- Migration: administrator-selected password recovery verification channel.
-- Idempotent and safe to execute repeatedly.

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'password_recovery_channel','safe','找回密码验证码','选择用户找回密码时接收验证码的渠道；短信模式仅允许已验证手机号','radio','email','{"email":"邮箱验证码","sms":"手机短信验证码"}','','',''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM `QH_config` WHERE `name`='password_recovery_channel'
);

UPDATE `QH_config`
SET `group`='safe',
    `title`='找回密码验证码',
    `tip`='选择用户找回密码时接收验证码的渠道；短信模式仅允许已验证手机号',
    `type`='radio',
    `content`='{"email":"邮箱验证码","sms":"手机短信验证码"}',
    `value`=IF(`value` IN ('email','sms'), `value`, 'email')
WHERE `name`='password_recovery_channel';

SELECT '20261004_password_recovery_channel applied' AS migration_result;
