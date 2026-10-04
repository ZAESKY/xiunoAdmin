DELETE FROM `SF_config` WHERE `name`='password_recovery_channel';

SELECT '20261004_password_recovery_channel rolled back' AS migration_result;
