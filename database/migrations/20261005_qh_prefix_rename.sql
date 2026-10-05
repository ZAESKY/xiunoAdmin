-- 将现有授权系统表从旧前缀无损迁移到 QH_。
-- 旧前缀使用编码常量，仅用于一次性兼容迁移，避免继续扩散旧项目命名。

SET @qh_legacy_prefix = CONVERT(FROM_BASE64('U0Zf') USING utf8mb4);
SET @qh_target_prefix = 'QH_';

SET @qh_old_group_concat_max_len = @@SESSION.group_concat_max_len;
SET SESSION group_concat_max_len = 65535;

SELECT GROUP_CONCAT(
  CONCAT(
    '`', REPLACE(src.TABLE_SCHEMA, '`', '``'), '`.`', REPLACE(src.TABLE_NAME, '`', '``'),
    '` TO `', REPLACE(src.TABLE_SCHEMA, '`', '``'), '`.`',
    REPLACE(CONCAT(@qh_target_prefix, SUBSTRING(src.TABLE_NAME, CHAR_LENGTH(@qh_legacy_prefix) + 1)), '`', '``'), '`'
  )
  ORDER BY src.TABLE_NAME
  SEPARATOR ', '
)
INTO @qh_rename_pairs
FROM information_schema.TABLES src
WHERE src.TABLE_SCHEMA = DATABASE()
  AND src.TABLE_TYPE = 'BASE TABLE'
  AND LEFT(src.TABLE_NAME, CHAR_LENGTH(@qh_legacy_prefix)) = @qh_legacy_prefix
  AND NOT EXISTS (
    SELECT 1
    FROM information_schema.TABLES dest
    WHERE dest.TABLE_SCHEMA = src.TABLE_SCHEMA
      AND dest.TABLE_NAME = CONCAT(@qh_target_prefix, SUBSTRING(src.TABLE_NAME, CHAR_LENGTH(@qh_legacy_prefix) + 1))
  );

SET @qh_rename_sql = IF(
  COALESCE(@qh_rename_pairs, '') = '',
  'SELECT 1',
  CONCAT('RENAME TABLE ', @qh_rename_pairs)
);
PREPARE qh_stmt FROM @qh_rename_sql;
EXECUTE qh_stmt;
DEALLOCATE PREPARE qh_stmt;
SET SESSION group_concat_max_len = @qh_old_group_concat_max_len;

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

-- 同步迁移安装时写入的品牌默认值，避免表名已更新但页面仍显示旧名称。
-- 只处理明确的系统配置项，不触碰应用密钥、私钥和历史日志。
SET @qh_legacy_upper = CONVERT(FROM_BASE64('U0Y=') USING utf8mb4) COLLATE utf8mb4_unicode_ci;
SET @qh_legacy_lower = CONVERT(FROM_BASE64('c2Y=') USING utf8mb4) COLLATE utf8mb4_unicode_ci;
SET @qh_target_upper = CONVERT('QH' USING utf8mb4) COLLATE utf8mb4_unicode_ci;
SET @qh_target_lower = CONVERT('qh' USING utf8mb4) COLLATE utf8mb4_unicode_ci;
UPDATE `QH_config`
SET `value` = REPLACE(REPLACE(`value` COLLATE utf8mb4_unicode_ci, @qh_legacy_upper, @qh_target_upper), @qh_legacy_lower, @qh_target_lower),
    `tip` = REPLACE(REPLACE(`tip` COLLATE utf8mb4_unicode_ci, @qh_legacy_upper, @qh_target_upper), @qh_legacy_lower, @qh_target_lower)
WHERE `name` IN ('title', 'keywords', 'description', 'foot', 'cdkey_head');

-- API 密钥属于凭据，绝不能对自定义值做模糊替换；仅迁移历史安装器的公开默认值。
UPDATE `QH_config`
SET `value` = CONCAT(@qh_target_lower, '-2129876388')
WHERE `name` = 'api_key'
  AND BINARY `value` = BINARY CONCAT(@qh_legacy_lower, '-2129876388');

SET @qh_legacy_prefix = NULL;
SET @qh_target_prefix = NULL;
SET @qh_rename_pairs = NULL;
SET @qh_rename_sql = NULL;
SET @qh_old_group_concat_max_len = NULL;
SET @qh_legacy_upper = NULL;
SET @qh_legacy_lower = NULL;
SET @qh_target_upper = NULL;
SET @qh_target_lower = NULL;
