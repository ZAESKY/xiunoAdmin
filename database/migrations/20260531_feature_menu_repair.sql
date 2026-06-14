-- Repair feature menus and first-install compatibility gaps.
-- Safe to run repeatedly on an existing SF_* database.

CREATE TABLE IF NOT EXISTS `SF_carousel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片URL',
  `url` varchar(500) NOT NULL DEFAULT '' COMMENT '跳转地址',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轮播图管理';

CREATE TABLE IF NOT EXISTS `SF_user_notice` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '公告标题',
  `content` text COMMENT '公告内容',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户公告';

CREATE TABLE IF NOT EXISTS `SF_withdraw` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '申请人用户ID',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '提现金额',
  `phone` varchar(20) DEFAULT '' COMMENT '手机号',
  `real_name` varchar(100) DEFAULT '' COMMENT '真实姓名',
  `pay_method` varchar(20) DEFAULT 'alipay' COMMENT '收款方式:alipay/wechat/bank',
  `qr_image` varchar(500) DEFAULT '' COMMENT '收款码图片',
  `user_remark` varchar(500) DEFAULT '' COMMENT '用户备注',
  `admin_remark` varchar(500) DEFAULT '' COMMENT '管理员处理备注',
  `transfer_image` varchar(500) DEFAULT '' COMMENT '管理员转账凭证',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态:pending/approved/rejected/withdrawn',
  `applied_at` datetime DEFAULT NULL COMMENT '申请时间',
  `handled_at` datetime DEFAULT NULL COMMENT '管理员处理时间',
  `withdrawn_at` datetime DEFAULT NULL COMMENT '用户撤回时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现记录';

SET @db_name := DATABASE();
SET @table_name := 'SF_withdraw';

SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `phone` varchar(20) DEFAULT '''' COMMENT ''手机号'' AFTER `amount`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'phone');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `pay_method` varchar(20) DEFAULT ''alipay'' COMMENT ''收款方式:alipay/wechat/bank'' AFTER `real_name`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'pay_method');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `qr_image` varchar(500) DEFAULT '''' COMMENT ''收款码图片'' AFTER `pay_method`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'qr_image');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `user_remark` varchar(500) DEFAULT '''' COMMENT ''用户备注'' AFTER `qr_image`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'user_remark');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `admin_remark` varchar(500) DEFAULT '''' COMMENT ''管理员处理备注'' AFTER `user_remark`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'admin_remark');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `transfer_image` varchar(500) DEFAULT '''' COMMENT ''管理员转账凭证'' AFTER `admin_remark`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'transfer_image');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `applied_at` datetime DEFAULT NULL COMMENT ''申请时间'' AFTER `status`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'applied_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `handled_at` datetime DEFAULT NULL COMMENT ''管理员处理时间'' AFTER `applied_at`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'handled_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `SF_withdraw` ADD COLUMN `withdrawn_at` datetime DEFAULT NULL COMMENT ''用户撤回时间'' AFTER `handled_at`', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'withdrawn_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE `SF_withdraw` MODIFY COLUMN `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态:pending/approved/rejected/withdrawn';

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'withdraw_enable', 'function', '余额提现', '开启后用户可提交余额提现申请', 'bool', '1', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'withdraw_enable');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'withdraw_min_amount', 'function', '最低提现金额', '用户单次提现最低金额', 'number', '10', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'withdraw_min_amount');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'withdraw_interval', 'function', '提现间隔(小时)', '同一用户两次提现申请之间的最小间隔，0表示不限制', 'number', '24', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'withdraw_interval');

UPDATE `SF_menu` SET `url` = 'PointExchange/list', `status` = 1
WHERE `url` = 'PointExchange/index' AND `power` = 2;

UPDATE `SF_menu` SET `url` = 'Checkin/records', `status` = 1
WHERE `url` IN ('Checkin/index', 'Checkin/list') AND `power` = 2;

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '系统设置', '#', 'layui-icon-set', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `name` = '系统设置' AND `url` = '#' AND `power` = 1);

SET @admin_set_id := (
  SELECT `id` FROM `SF_menu`
  WHERE `name` = '系统设置' AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '轮播图管理', 'Set/carousel', '', @admin_set_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'Set/carousel' AND `power` = 1);

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '用户通知', 'Set/userNotice', '', @admin_set_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'Set/userNotice' AND `power` = 1);

UPDATE `SF_menu`
SET `parentid` = @admin_set_id, `power` = 1, `status` = 1
WHERE `url` IN ('Set/index', 'Set/carousel', 'Set/userNotice') AND `power` = 1;

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '打卡管理', '#', 'layui-icon-date', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `name` = '打卡管理' AND `url` = '#' AND `power` = 1);

SET @admin_checkin_id := (
  SELECT `id` FROM `SF_menu`
  WHERE `name` = '打卡管理' AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '打卡配置', 'Checkin/config', '', @admin_checkin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'Checkin/config' AND `power` = 1);

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '打卡记录', 'Checkin/records', '', @admin_checkin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'Checkin/records' AND `power` = 1);

UPDATE `SF_menu`
SET `name` = '打卡记录', `icon` = '', `parentid` = @admin_checkin_id, `power` = 1, `status` = 1
WHERE `url` = 'Checkin/records' AND `power` = 1;

UPDATE `SF_menu`
SET `name` = '打卡配置', `icon` = '', `parentid` = @admin_checkin_id, `power` = 1, `status` = 1
WHERE `url` = 'Checkin/config' AND `power` = 1;

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '每日打卡', 'Checkin/records', 'layui-icon-date', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'Checkin/records' AND `power` = 2);

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分兑换', 'PointExchange/list', 'layui-icon-cart-simple', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'PointExchange/list' AND `power` = 2);

UPDATE `SF_menu`
SET `name` = '每日打卡', `icon` = 'layui-icon-date', `parentid` = 0, `power` = 2, `status` = 1
WHERE `url` = 'Checkin/records' AND `power` = 2;

UPDATE `SF_menu`
SET `name` = '积分兑换', `icon` = 'layui-icon-cart-simple', `parentid` = 0, `power` = 2, `status` = 1
WHERE `url` = 'PointExchange/list' AND `power` = 2;

DELETE m1 FROM `SF_menu` m1
JOIN `SF_menu` m2
  ON m1.`id` > m2.`id`
 AND m1.`url` = m2.`url`
 AND m1.`power` = m2.`power`
WHERE m1.`url` NOT IN ('', '#');

DELETE m1 FROM `SF_menu` m1
JOIN `SF_menu` m2
  ON m1.`id` > m2.`id`
 AND m1.`name` = m2.`name`
 AND m1.`url` = m2.`url`
 AND m1.`power` = m2.`power`
 AND m1.`parentid` = m2.`parentid`
WHERE m1.`url` IN ('', '#');
