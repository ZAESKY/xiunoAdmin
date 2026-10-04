-- Purpose-aware Aliyun SMS templates and delivery audit metadata.
-- Idempotent and safe to execute repeatedly on MySQL 5.7+/8.0+.

DELIMITER $$

DROP PROCEDURE IF EXISTS sf_sms_add_column_if_missing $$
CREATE PROCEDURE sf_sms_add_column_if_missing(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT
)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND COLUMN_NAME=p_column)
    THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL sf_sms_add_column_if_missing(
  'SF_sms_audit', 'template_code',
  "varchar(32) NOT NULL DEFAULT '' COMMENT '本次发送实际使用的模板CODE' AFTER `error_code`"
);

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_template_login_register','sms','登录/注册模板 CODE','阿里云号码认证赠送模板默认使用 100001；用于短信登录或注册','string','100001','','','maxlength="32"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_template_login_register');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_template_phone_change','sms','修改手机号模板 CODE','阿里云号码认证赠送模板默认使用 100002；用于发起修改绑定手机号','string','100002','','','maxlength="32"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_template_phone_change');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_template_password_reset','sms','重置密码模板 CODE','阿里云号码认证赠送模板默认使用 100003；用于手机验证码找回密码','string','100003','','','maxlength="32"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_template_password_reset');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_template_phone_bind','sms','绑定新手机号模板 CODE','阿里云号码认证赠送模板默认使用 100004；用于首次绑定或验证新手机号','string','100004','','','maxlength="32"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_template_phone_bind');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_template_phone_verify','sms','验证已绑定手机号模板 CODE','阿里云号码认证赠送模板默认使用 100005；用于提现、返利等敏感操作验证','string','100005','','','maxlength="32"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_template_phone_verify');

UPDATE `SF_config`
SET `title`='备用/自定义验证码模板 CODE',
    `tip`='某个业务模板留空时使用此 CODE；可填写数字赠送模板或 SMS_ 开头的自定义模板。全部业务模板已配置时可留空'
WHERE `name`='sms_template_code';

DROP PROCEDURE IF EXISTS sf_sms_add_column_if_missing;

SELECT '20261004_sms_template_scenes applied' AS migration_result;
