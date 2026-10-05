-- QQ OAuth identity mapping.
-- Additive and safe to run repeatedly on MySQL 5.7+/8.0+.

CREATE TABLE IF NOT EXISTS `QH_social_identity` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL COMMENT 'qq/wechat/etc',
  `provider_appid` varchar(64) NOT NULL COMMENT '第三方平台应用ID',
  `provider_uid` varchar(128) NOT NULL COMMENT '第三方稳定用户标识，如QQ OpenID',
  `unionid` varchar(128) NOT NULL DEFAULT '' COMMENT '跨应用标识（平台支持时）',
  `nickname` varchar(255) NOT NULL DEFAULT '',
  `avatar` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provider_subject` (`provider`,`provider_appid`,`provider_uid`),
  KEY `idx_unionid` (`provider`,`unionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方登录身份';

CREATE TABLE IF NOT EXISTS `QH_user_social_identity` (
  `identity_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`identity_id`,`user_id`),
  UNIQUE KEY `uk_social_identity_once` (`identity_id`),
  UNIQUE KEY `uk_social_user_once` (`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户与第三方身份关联';
