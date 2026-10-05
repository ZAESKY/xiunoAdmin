DELETE FROM `QH_menu` WHERE `url`='Set/emailNotification' AND `power`=1;
DELETE FROM `QH_config` WHERE `name`='email_notification_enabled';
DROP TABLE IF EXISTS `QH_notification_email_log`;
DROP TABLE IF EXISTS `QH_notification_email_template`;
DROP TABLE IF EXISTS `QH_notification_email_preference`;
SELECT '20261004_email_notifications rolled back' AS migration_result;
