-- 返利/折扣码功能 — 数据库迁移
-- 说明: 兼容旧库升级和新库初始化后的重复执行。

DROP PROCEDURE IF EXISTS QH_ADD_COLUMN_IF_MISSING;
DELIMITER $$
CREATE PROCEDURE QH_ADD_COLUMN_IF_MISSING(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_column_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @qh_sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_definition);
    PREPARE qh_stmt FROM @qh_sql;
    EXECUTE qh_stmt;
    DEALLOCATE PREPARE qh_stmt;
  END IF;
END$$
DELIMITER ;

-- power_price 新增返利配置字段
CALL QH_ADD_COLUMN_IF_MISSING('QH_power_price', 'rebate_enabled', '`rebate_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''启用返利 0=否 1=是''');
CALL QH_ADD_COLUMN_IF_MISSING('QH_power_price', 'rebate_rate', '`rebate_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT ''返利比例(%)，如5.00=5%''');
CALL QH_ADD_COLUMN_IF_MISSING('QH_power_price', 'discount_code_enabled', '`discount_code_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''启用折扣码功能 0=否 1=是''');
CALL QH_ADD_COLUMN_IF_MISSING('QH_user', 'withdrawable_balance', '`withdrawable_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT ''可提现收益余额，为总余额的子集'' AFTER `balance`');

-- 折扣码表
CREATE TABLE IF NOT EXISTS `QH_discount_code` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '所属用户ID',
  `code` varchar(32) NOT NULL COMMENT '唯一折扣码',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `created_at` datetime NOT NULL COMMENT '生成时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='折扣码表';

-- 返利记录表
CREATE TABLE IF NOT EXISTS `QH_rebate_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) unsigned NOT NULL COMMENT 'QH_order.id',
  `pay_trade_no` varchar(255) DEFAULT NULL COMMENT 'QH_pay.trade_no',
  `payer_user_id` int(11) unsigned NOT NULL COMMENT '付款用户ID',
  `referrer_user_id` int(11) unsigned NOT NULL COMMENT '返利归属用户ID（折扣码所有者）',
  `discount_code` varchar(32) NOT NULL COMMENT '使用的折扣码',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
  `rebate_base_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '返利计算基数（充值面额）',
  `rebate_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '结算时的返利比例(%)',
  `rebate_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '返利金额',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/settled/rejected/canceled',
  `settle_at` datetime DEFAULT NULL COMMENT '预计结算时间',
  `settled_at` datetime DEFAULT NULL COMMENT '实际结算时间',
  `risk_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '内部风控原因',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pay_trade_no` (`pay_trade_no`),
  KEY `idx_referrer` (`referrer_user_id`),
  KEY `idx_code` (`discount_code`),
  KEY `idx_status_settle_at` (`status`,`settle_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='返利记录表';

CALL QH_ADD_COLUMN_IF_MISSING('QH_rebate_record', 'rebate_base_amount', '`rebate_base_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT ''返利计算基数（充值面额）'' AFTER `paid_amount`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_rebate_record', 'settle_at', '`settle_at` datetime DEFAULT NULL COMMENT ''预计结算时间'' AFTER `status`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_rebate_record', 'settled_at', '`settled_at` datetime DEFAULT NULL COMMENT ''实际结算时间'' AFTER `settle_at`');
CALL QH_ADD_COLUMN_IF_MISSING('QH_rebate_record', 'risk_reason', '`risk_reason` varchar(255) NOT NULL DEFAULT '''' COMMENT ''内部风控原因'' AFTER `settled_at`');

-- pay/order 表增加折扣码字段
CALL QH_ADD_COLUMN_IF_MISSING('QH_pay', 'discount_code', '`discount_code` varchar(32) DEFAULT NULL COMMENT ''使用的折扣码''');
CALL QH_ADD_COLUMN_IF_MISSING('QH_order', 'discount_code', '`discount_code` varchar(32) DEFAULT NULL COMMENT ''使用的折扣码''');

DROP PROCEDURE IF EXISTS QH_ADD_COLUMN_IF_MISSING;
