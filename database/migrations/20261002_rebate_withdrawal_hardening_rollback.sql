-- 仅用于代码也已回滚时撤销本次结构变更。

DROP TRIGGER IF EXISTS `QH_user_withdrawable_before_insert`;
DROP TRIGGER IF EXISTS `QH_user_withdrawable_before_update`;

DELETE FROM `QH_config`
WHERE `name` IN (
  'rebate_hold_days',
  'rebate_pair_daily_count',
  'rebate_daily_limit',
  'rebate_monthly_limit'
);

DROP PROCEDURE IF EXISTS QH_REBATE_DROP_COLUMN_IF_EXISTS;
DELIMITER $$
CREATE PROCEDURE QH_REBATE_DROP_COLUMN_IF_EXISTS(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64)
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @qh_rebate_sql = CONCAT(
      'ALTER TABLE `', p_table_name, '` DROP COLUMN `', p_column_name, '`'
    );
    PREPARE qh_rebate_stmt FROM @qh_rebate_sql;
    EXECUTE qh_rebate_stmt;
    DEALLOCATE PREPARE qh_rebate_stmt;
  END IF;
END$$
DELIMITER ;

CALL QH_REBATE_DROP_COLUMN_IF_EXISTS('QH_rebate_record', 'risk_reason');
CALL QH_REBATE_DROP_COLUMN_IF_EXISTS('QH_rebate_record', 'settled_at');
CALL QH_REBATE_DROP_COLUMN_IF_EXISTS('QH_rebate_record', 'settle_at');
CALL QH_REBATE_DROP_COLUMN_IF_EXISTS('QH_rebate_record', 'rebate_base_amount');
CALL QH_REBATE_DROP_COLUMN_IF_EXISTS('QH_user', 'withdrawable_balance');

DROP PROCEDURE IF EXISTS QH_REBATE_DROP_COLUMN_IF_EXISTS;

ALTER TABLE `QH_rebate_record`
  MODIFY `status` varchar(20) NOT NULL DEFAULT 'settled' COMMENT 'settled/canceled';
