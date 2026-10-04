-- QQ one-click registration and Aliyun SMS phone verification.
-- Idempotent and safe to execute repeatedly on MySQL 5.7+/8.0+.

DELIMITER $$

DROP PROCEDURE IF EXISTS sf_add_column_if_missing $$
CREATE PROCEDURE sf_add_column_if_missing(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT
)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL sf_add_column_if_missing('SF_user', 'phone_verified_at',
    "datetime DEFAULT NULL COMMENT '手机号通过短信验证的时间' AFTER `phone`");
CALL sf_add_column_if_missing('SF_user', 'phone_verified_source',
    "varchar(20) NOT NULL DEFAULT '' COMMENT '手机号验证来源' AFTER `phone_verified_at`");

CREATE TABLE IF NOT EXISTS `SF_user_phone_identity` (
  `user_id` int(11) unsigned NOT NULL,
  `phone_hash` char(64) NOT NULL COMMENT '带服务端pepper的手机号HMAC',
  `phone_last4` char(4) NOT NULL DEFAULT '',
  `verified_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_phone_hash` (`phone_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='已验证手机号唯一身份';

CREATE TABLE IF NOT EXISTS `SF_sms_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT 0,
  `scene` varchar(32) NOT NULL DEFAULT '',
  `phone_hash` char(64) NOT NULL DEFAULT '',
  `phone_masked` varchar(20) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT '',
  `provider_request_id` varchar(128) NOT NULL DEFAULT '',
  `error_code` varchar(64) NOT NULL DEFAULT '',
  `template_code` varchar(32) NOT NULL DEFAULT '' COMMENT '本次发送实际使用的模板CODE',
  `ip_hash` char(64) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_scene_time` (`user_id`,`scene`,`created_at`),
  KEY `idx_phone_time` (`phone_hash`,`created_at`),
  KEY `idx_status_time` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信发送与验证审计（不保存验证码）';

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_enabled','sms','启用阿里云短信','开启后可绑定已验证手机号，并按下方开关保护敏感操作','bool','0','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_enabled');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_access_key_id','sms','AccessKey ID','建议使用仅授予短信发送权限的RAM子账号，不要使用主账号AccessKey','string','','','','autocomplete="off"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_access_key_id');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_access_key_secret','sms','AccessKey Secret','密钥加密保存且永不回显；留空保持原值，填写新值才会替换','string','','','','autocomplete="new-password"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_access_key_secret');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_sign_name','sms','短信签名','填写阿里云短信控制台审核通过的签名名称，不包含【】','string','','','','maxlength="100"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_sign_name');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_template_code','sms','验证码模板CODE','号码认证赠送模板填数字 CODE（如 100001），普通短信模板填 SMS_ 开头的 CODE；模板须包含 ${code}','string','','','','maxlength="32"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_template_code');

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

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_code_ttl','sms','验证码有效期（秒）','建议300秒，可设置120至600秒','number','300','','','min="120" max="600" step="1"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_code_ttl');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_daily_limit','sms','单手机号每日上限','包括绑定和敏感操作验证码，建议不超过10条','number','10','','','min="1" max="30" step="1"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_daily_limit');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_require_withdraw','sms','提现需要短信验证','申请提现时必须使用已绑定手机号接收一次性验证码','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_require_withdraw');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_require_rebate','sms','生成返利码需要短信验证','首次生成专属折扣码时必须验证已绑定手机号','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_require_rebate');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'sms_require_plugin_reward','sms','插件奖励需要已验证手机号','未绑定已验证手机号的账号可以发布插件，但不会获得首次发布奖励','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='sms_require_plugin_reward');

UPDATE `SF_config`
SET `extend`='autocomplete="new-password"'
WHERE `name`='sms_access_key_secret';

UPDATE `SF_config`
SET `title`='备用/自定义验证码模板 CODE',
    `tip`='某个业务模板留空时使用此 CODE；可填写数字赠送模板或 SMS_ 开头的自定义模板。全部业务模板已配置时可留空'
WHERE `name`='sms_template_code';

DROP PROCEDURE IF EXISTS sf_add_column_if_missing;

SELECT '20261004_qq_sms_registration applied' AS migration_result;
