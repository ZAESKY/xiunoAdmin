-- One-time reward for a user plugin's first approval.

CREATE TABLE IF NOT EXISTS `SF_plugin_reward` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '奖励ID',
  `plugin_id` int(11) unsigned NOT NULL COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '发布者用户ID',
  `plugin_name` varchar(255) NOT NULL DEFAULT '' COMMENT '插件名称快照',
  `scene` varchar(32) NOT NULL DEFAULT 'first_approval' COMMENT '奖励场景',
  `status` varchar(20) NOT NULL DEFAULT 'issued' COMMENT 'issued/skipped/revoked',
  `points` int(11) NOT NULL DEFAULT 0 COMMENT '奖励积分',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '奖励平台余额（不可提现）',
  `file_hash` varchar(64) NOT NULL DEFAULT '' COMMENT '插件包哈希快照',
  `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '跳过或撤销原因',
  `config_snapshot` text COMMENT '发放时配置快照',
  `approved_by` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `approved_at` datetime NOT NULL COMMENT '审核通过时间',
  `issued_at` datetime DEFAULT NULL COMMENT '奖励到账时间',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_scene` (`plugin_id`,`scene`),
  KEY `idx_user_status_time` (`user_id`,`status`,`issued_at`),
  KEY `idx_hash_scene_status` (`file_hash`,`scene`,`status`),
  KEY `idx_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件发布奖励记录';

CREATE TABLE IF NOT EXISTS `SF_plugin_reward_hash_claim` (
  `file_hash` varchar(64) NOT NULL COMMENT '已占用的插件包哈希',
  `plugin_id` int(11) unsigned NOT NULL COMMENT '首次获奖插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '首次获奖用户ID',
  `claimed_at` datetime NOT NULL COMMENT '占用时间',
  PRIMARY KEY (`file_hash`),
  UNIQUE KEY `uk_plugin_id` (`plugin_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件奖励包哈希原子占用';

INSERT IGNORE INTO `SF_plugin_reward_hash_claim` (`file_hash`,`plugin_id`,`user_id`,`claimed_at`)
SELECT LOWER(`file_hash`),`plugin_id`,`user_id`,COALESCE(`issued_at`,`created_at`)
FROM `SF_plugin_reward`
WHERE `status`='issued' AND `file_hash`<>'';

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_reward_enabled','plugin_reward','启用发布奖励','用户原创插件首次审核通过时触发；关闭期间通过的插件以后也不会补发','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='plugin_reward_enabled');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_reward_points','plugin_reward','首次通过奖励积分','填0表示不奖励积分；每个插件只奖励一次','number','100','','','min="0" max="1000000" step="1"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='plugin_reward_points');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_reward_balance','plugin_reward','首次通过奖励金额','作为平台余额发放，不进入可提现余额；填0表示不奖励金额','number','0.00','','','min="0" max="1000000" step="0.01"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='plugin_reward_balance');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_reward_original_only','plugin_reward','仅奖励原创插件','开启后转载插件审核通过但不会获得奖励','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='plugin_reward_original_only');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_reward_monthly_limit','plugin_reward','用户每月奖励上限','单个用户每月最多获得奖励的插件数量；0表示不限制','number','3','','','min="0" max="1000" step="1"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='plugin_reward_monthly_limit');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_reward_min_account_days','plugin_reward','账号最低注册天数','账号注册达到该天数后才可获得插件发布奖励；0表示不限制','number','7','','','min="0" max="3650" step="1"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='plugin_reward_min_account_days');

INSERT INTO `SF_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_reward_duplicate_hash','plugin_reward','拦截重复插件包','相同插件包哈希全站只允许获得一次首次发布奖励','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name`='plugin_reward_duplicate_hash');
