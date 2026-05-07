-- ================================================================
-- SF授权系统 - 线上测试环境一键更新脚本
-- 版本: 4.2.9 → 4.3.0
-- 生成日期: 2026-05-04
-- ================================================================
--
-- 【执行前注意事项】
-- 1. 请务必备份数据库！
-- 2. 请在低峰期执行，部分 ALTER TABLE 可能锁表
-- 3. 先上传新代码，再执行此 SQL
-- 4. 执行后清除 runtime/cache/ 目录
-- 5. 如果使用 Redis，请执行 FLUSHALL 清除缓存
--
-- 【备份命令参考】
-- mysqldump -u用户名 -p 数据库名 > backup_20260504.sql
--
-- ================================================================

-- 开启事务（MySQL 5.5+ 支持）
START TRANSACTION;

-- ================================================================
-- 第一部分：表结构更新
-- ================================================================

-- 创建缺失的 balance_log 表（余额变动日志）
-- 如果表已存在则跳过
CREATE TABLE IF NOT EXISTS `SF_balance_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(30) DEFAULT 'recharge' COMMENT '类型: recharge/consume/refund/adjust',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '变动金额（正=增加，负=减少）',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '变动后余额',
  `description` varchar(255) DEFAULT '' COMMENT '描述',
  `source_type` varchar(50) DEFAULT '' COMMENT '来源类型',
  `source_no` varchar(64) DEFAULT '' COMMENT '来源单号',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='余额变动日志';

-- 创建缺失的 withdraw 表（提现记录）
CREATE TABLE IF NOT EXISTS `SF_withdraw` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '申请人用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '应用ID',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '提现金额',
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '手续费',
  `actual_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际到账金额',
  `method` varchar(30) DEFAULT 'alipay' COMMENT '提现方式: alipay/wxpay/bank',
  `account` varchar(255) DEFAULT '' COMMENT '收款账号',
  `real_name` varchar(100) DEFAULT '' COMMENT '真实姓名',
  `remark` varchar(500) DEFAULT '' COMMENT '备注',
  `status` tinyint(1) DEFAULT 0 COMMENT '0=待审核 1=已通过 2=已拒绝 3=已打款',
  `review_remark` varchar(500) DEFAULT '' COMMENT '审核备注',
  `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` datetime DEFAULT NULL COMMENT '申请时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='提现记录';

-- ================================================================
-- 第二部分：字段更新（可重复执行）
-- ================================================================

-- 为 SF_user 增加 is_developer 字段（插件开发者标识）
-- 使用存储过程兼容不支持 IF NOT EXISTS 的 MySQL 版本
DROP PROCEDURE IF EXISTS `sf_add_column`;
DELIMITER $$
CREATE PROCEDURE `sf_add_column`()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;

  -- SF_user.is_developer
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user' AND COLUMN_NAME = 'is_developer') THEN
    ALTER TABLE `SF_user` ADD COLUMN `is_developer` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为开发者' AFTER `appid`;
  END IF;

  -- SF_user.created_at
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_user' AND COLUMN_NAME = 'created_at') THEN
    ALTER TABLE `SF_user` ADD COLUMN `created_at` datetime DEFAULT NULL COMMENT '注册时间' AFTER `addtime`;
  END IF;

END$$
DELIMITER ;
CALL `sf_add_column`();
DROP PROCEDURE IF EXISTS `sf_add_column`;

-- ================================================================
-- 第三部分：默认配置初始化（可重复执行）
-- ================================================================

-- 使用 INSERT ... ON DUPLICATE KEY UPDATE 确保幂等
-- 插件佣金比例（默认10%）
INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`)
VALUES ('plugin_commission_rate', 'function', '插件佣金比例(%)', '平台从插件销售中抽取的佣金百分比', 'number', '10', '', '', '')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `type` = VALUES(`type`);

-- ================================================================
-- 第四部分：菜单/权限初始化（可重复执行）
-- ================================================================

-- 由于模板管理功能已废弃，删除已存在的模板配置菜单
DELETE FROM `SF_menu` WHERE `url` = 'Set/template';

-- 确保插件市场菜单在用户端存在（如果不存在则插入）
INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件市场', 'UserPlugin/market', 'layui-icon-template-1', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'UserPlugin/market' AND `parentid` = 0);

-- 确保用户端"我的插件"菜单存在
INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的插件', 'UserPlugin/list', '',
  (SELECT id FROM `SF_menu` WHERE `url` = 'UserPlugin/market' AND `parentid` = 0 LIMIT 1),
  NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'UserPlugin/list');

-- ================================================================
-- 第五部分：数据修复
-- ================================================================

-- 修复 SF_check_type 表数据（如果存在重复插入导致的不一致）
-- 确保默认检查类型存在
INSERT IGNORE INTO `SF_check_type` (`name`, `type`, `addtime`, `status`) VALUES
('域名', 'domain', NOW(), 1),
('QQ', 'qq', NOW(), 1),
('机器码', 'machineCode', NOW(), 1);

-- 清除模板相关缓存（通过删除缓存标记让系统重建）
-- 注意：如果使用文件缓存，请手动删除 runtime/cache/ 目录
-- 如果使用 Redis，请执行 FLUSHALL

-- ================================================================
-- 第八部分：插件发布类型字段
-- ================================================================

DROP PROCEDURE IF EXISTS `sf_add_plugin_publish`;
DELIMITER $$
CREATE PROCEDURE `sf_add_plugin_publish`()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;

  -- SF_plugin.publish_type (0=立即发布, 1=定时发布)
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_plugin' AND COLUMN_NAME = 'publish_type') THEN
    ALTER TABLE `SF_plugin` ADD COLUMN `publish_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '发布类型:0=立即发布,1=定时发布' AFTER `published_at`;
  END IF;

  -- SF_plugin.publish_time (定时发布时间)
  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_plugin' AND COLUMN_NAME = 'publish_time') THEN
    ALTER TABLE `SF_plugin` ADD COLUMN `publish_time` datetime DEFAULT NULL COMMENT '定时发布时间' AFTER `publish_type`;
  END IF;

END$$
DELIMITER ;
CALL `sf_add_plugin_publish`();
DROP PROCEDURE IF EXISTS `sf_add_plugin_publish`;

-- 已上架的旧数据默认设为立即发布
UPDATE `SF_plugin` SET `publish_type` = 0 WHERE `publish_type` IS NULL AND `status` = 1;

COMMIT;

-- ================================================================
-- 第六部分：回滚 SQL（仅在更新失败时使用）
-- ================================================================

-- 如需回滚，请执行以下 SQL：
--
-- -- 回滚新增字段
-- ALTER TABLE `SF_user` DROP COLUMN IF EXISTS `is_developer`;
-- ALTER TABLE `SF_user` DROP COLUMN IF EXISTS `created_at`;
--
-- -- 恢复模板配置菜单（如果需要）
-- INSERT IGNORE INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
-- VALUES ('模板配置', 'Set/template', '',
--   (SELECT id FROM (SELECT id FROM `SF_menu` WHERE `url` = 'Set/index' LIMIT 1) AS t),
--   NOW(), 1, 1);
--
-- -- 注意：balance_log 和 withdraw 表如果已有新数据请不要删除
-- -- DROP TABLE IF EXISTS `SF_balance_log`;
-- -- DROP TABLE IF EXISTS `SF_withdraw`;

-- ================================================================
-- 第七部分：验证查询
-- ================================================================

-- 执行以下查询验证更新成功：
--
-- 1. 检查表是否创建成功：
--    SHOW TABLES LIKE 'SF_balance_log';
--    SHOW TABLES LIKE 'SF_withdraw';
--
-- 2. 检查字段是否新增成功：
--    SHOW COLUMNS FROM `SF_user` LIKE 'is_developer';
--    SHOW COLUMNS FROM `SF_user` LIKE 'created_at';
--
-- 3. 检查模板菜单是否已删除：
--    SELECT * FROM `SF_menu` WHERE `url` = 'Set/template';  -- 应返回空
--
-- 4. 检查配置是否存在：
--    SELECT * FROM `SF_config` WHERE `name` = 'plugin_commission_rate';
