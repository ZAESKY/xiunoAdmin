-- 返利防套利与可提现收益余额拆分。
-- 可重复执行；历史可提现余额仅在字段首次创建时回填。

SET @qh_withdrawable_was_missing = (
  SELECT COUNT(*) = 0
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'QH_user'
    AND COLUMN_NAME = 'withdrawable_balance'
);

DROP PROCEDURE IF EXISTS QH_REBATE_ADD_COLUMN_IF_MISSING;
DROP PROCEDURE IF EXISTS QH_REBATE_ADD_INDEX_IF_MISSING;
DELIMITER $$
CREATE PROCEDURE QH_REBATE_ADD_COLUMN_IF_MISSING(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_column_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @qh_rebate_sql = CONCAT(
      'ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_definition
    );
    PREPARE qh_rebate_stmt FROM @qh_rebate_sql;
    EXECUTE qh_rebate_stmt;
    DEALLOCATE PREPARE qh_rebate_stmt;
  END IF;
END$$

CREATE PROCEDURE QH_REBATE_ADD_INDEX_IF_MISSING(
  IN p_table_name VARCHAR(64),
  IN p_index_name VARCHAR(64),
  IN p_index_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @qh_rebate_sql = CONCAT(
      'ALTER TABLE `', p_table_name, '` ADD INDEX `', p_index_name, '` ', p_index_definition
    );
    PREPARE qh_rebate_stmt FROM @qh_rebate_sql;
    EXECUTE qh_rebate_stmt;
    DEALLOCATE PREPARE qh_rebate_stmt;
  END IF;
END$$
DELIMITER ;

CALL QH_REBATE_ADD_COLUMN_IF_MISSING(
  'QH_user',
  'withdrawable_balance',
  '`withdrawable_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT ''可提现收益余额，为总余额的子集'' AFTER `balance`'
);
CALL QH_REBATE_ADD_COLUMN_IF_MISSING(
  'QH_rebate_record',
  'rebate_base_amount',
  '`rebate_base_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT ''返利计算基数（充值面额）'' AFTER `paid_amount`'
);
CALL QH_REBATE_ADD_COLUMN_IF_MISSING(
  'QH_rebate_record',
  'settle_at',
  '`settle_at` datetime DEFAULT NULL COMMENT ''预计结算时间'' AFTER `status`'
);
CALL QH_REBATE_ADD_COLUMN_IF_MISSING(
  'QH_rebate_record',
  'settled_at',
  '`settled_at` datetime DEFAULT NULL COMMENT ''实际结算时间'' AFTER `settle_at`'
);
CALL QH_REBATE_ADD_COLUMN_IF_MISSING(
  'QH_rebate_record',
  'risk_reason',
  '`risk_reason` varchar(255) NOT NULL DEFAULT '''' COMMENT ''内部风控原因'' AFTER `settled_at`'
);
CALL QH_REBATE_ADD_INDEX_IF_MISSING(
  'QH_rebate_record',
  'idx_status_settle_at',
  '(`status`, `settle_at`)'
);

DROP PROCEDURE IF EXISTS QH_REBATE_ADD_COLUMN_IF_MISSING;
DROP PROCEDURE IF EXISTS QH_REBATE_ADD_INDEX_IF_MISSING;

ALTER TABLE `QH_rebate_record`
  MODIFY `status` varchar(20) NOT NULL DEFAULT 'pending'
    COMMENT 'pending/settled/rejected/canceled';

-- 兼容历史数据：旧记录已完成入账，不再次冻结或重复加款。
UPDATE `QH_rebate_record`
SET
  `rebate_base_amount` = CASE
    WHEN `rebate_base_amount` <= 0 THEN `paid_amount`
    ELSE `rebate_base_amount`
  END,
  `settled_at` = CASE
    WHEN `status` = 'settled' AND `settled_at` IS NULL
      THEN COALESCE(`updated_at`, `created_at`)
    ELSE `settled_at`
  END;

-- 首次新增字段时保守回填：
-- 历史返利 + 插件销售收益 + 提现退回 - 所有历史支出，且不超过当前总余额。
UPDATE `QH_user` u
LEFT JOIN (
  SELECT `referrer_user_id` AS `user_id`, SUM(`rebate_amount`) AS `rebate_income`
  FROM `QH_rebate_record`
  WHERE `status` = 'settled'
  GROUP BY `referrer_user_id`
) r ON r.`user_id` = u.`id`
LEFT JOIN (
  SELECT
    `user_id`,
    SUM(CASE
      WHEN `type` = 'plugin_income' AND `amount` > 0 THEN `amount`
      WHEN `type` IN ('withdraw_cancel', 'withdraw_reject') AND `amount` > 0 THEN `amount`
      WHEN `amount` < 0 THEN `amount`
      ELSE 0
    END) AS `income_adjustment`
  FROM `QH_balance_log`
  GROUP BY `user_id`
) b ON b.`user_id` = u.`id`
SET u.`withdrawable_balance` = LEAST(
  GREATEST(u.`balance`, 0.00),
  GREATEST(COALESCE(r.`rebate_income`, 0.00) + COALESCE(b.`income_adjustment`, 0.00), 0.00)
)
WHERE @qh_withdrawable_was_missing = 1;

INSERT INTO `QH_config`
  (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'rebate_hold_days', 'rebate', '返利冻结天数', '返利到期并通过复核后进入可提现收益余额，建议不少于7天', 'number', '7', '', '', '', NULL
WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'rebate_hold_days');

INSERT INTO `QH_config`
  (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'rebate_pair_daily_count', 'rebate', '同一邀请关系每日上限', '同一付款账号与同一码主每天最多产生的返利笔数', 'number', '3', '', '', '', NULL
WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'rebate_pair_daily_count');

INSERT INTO `QH_config`
  (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'rebate_daily_limit', 'rebate', '码主每日返利上限', '单个码主每天可进入冻结期的返利金额上限（元）', 'number', '50', '', '', '', NULL
WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'rebate_daily_limit');

INSERT INTO `QH_config`
  (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'rebate_monthly_limit', 'rebate', '码主每月返利上限', '单个码主每月可进入冻结期的返利金额上限（元）', 'number', '500', '', '', '', NULL
WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'rebate_monthly_limit');

-- 非可提现余额优先消费：总余额下降到收益余额以下时，自动压低收益余额。
DROP TRIGGER IF EXISTS `QH_user_withdrawable_before_insert`;
DROP TRIGGER IF EXISTS `QH_user_withdrawable_before_update`;
DELIMITER $$
CREATE TRIGGER `QH_user_withdrawable_before_insert`
BEFORE INSERT ON `QH_user`
FOR EACH ROW
BEGIN
  IF NEW.`withdrawable_balance` < 0 THEN
    SET NEW.`withdrawable_balance` = 0.00;
  END IF;
  IF NEW.`withdrawable_balance` > NEW.`balance` THEN
    SET NEW.`withdrawable_balance` = GREATEST(NEW.`balance`, 0.00);
  END IF;
END$$

CREATE TRIGGER `QH_user_withdrawable_before_update`
BEFORE UPDATE ON `QH_user`
FOR EACH ROW
BEGIN
  IF NEW.`withdrawable_balance` < 0 THEN
    SET NEW.`withdrawable_balance` = 0.00;
  END IF;
  IF NEW.`withdrawable_balance` > NEW.`balance` THEN
    SET NEW.`withdrawable_balance` = GREATEST(NEW.`balance`, 0.00);
  END IF;
END$$
DELIMITER ;
