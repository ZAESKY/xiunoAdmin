-- 返利/折扣码功能 — 数据库迁移
-- power_price 新增返利配置字段
ALTER TABLE `SF_power_price`
  ADD COLUMN `rebate_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用返利 0=否 1=是',
  ADD COLUMN `rebate_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '返利比例(%)，如5.00=5%',
  ADD COLUMN `discount_code_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用折扣码功能 0=否 1=是';

-- 折扣码表
CREATE TABLE `SF_discount_code` (
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
CREATE TABLE `SF_rebate_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) unsigned NOT NULL COMMENT 'SF_order.id',
  `pay_trade_no` varchar(255) DEFAULT NULL COMMENT 'SF_pay.trade_no',
  `payer_user_id` int(11) unsigned NOT NULL COMMENT '付款用户ID',
  `referrer_user_id` int(11) unsigned NOT NULL COMMENT '返利归属用户ID（折扣码所有者）',
  `discount_code` varchar(32) NOT NULL COMMENT '使用的折扣码',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
  `rebate_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '结算时的返利比例(%)',
  `rebate_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '返利金额',
  `status` varchar(20) NOT NULL DEFAULT 'settled' COMMENT 'settled/canceled',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pay_trade_no` (`pay_trade_no`),
  KEY `idx_referrer` (`referrer_user_id`),
  KEY `idx_code` (`discount_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='返利记录表';

-- pay/order 表增加折扣码字段
ALTER TABLE `SF_pay` ADD COLUMN `discount_code` varchar(32) DEFAULT NULL COMMENT '使用的折扣码';
ALTER TABLE `SF_order` ADD COLUMN `discount_code` varchar(32) DEFAULT NULL COMMENT '使用的折扣码';
