-- 回滚 QH_ 表前缀迁移。仅在应用代码也同步回滚时使用。

SET @qh_legacy_prefix = CONVERT(FROM_BASE64('U0Zf') USING utf8mb4);
SET @qh_target_prefix = 'QH_';

DROP TRIGGER IF EXISTS `QH_user_withdrawable_before_insert`;
DROP TRIGGER IF EXISTS `QH_user_withdrawable_before_update`;

-- 与前向迁移成对恢复系统默认品牌值；旧文本仍只通过编码常量生成。
SET @qh_legacy_upper = CONVERT(FROM_BASE64('U0Y=') USING utf8mb4) COLLATE utf8mb4_unicode_ci;
SET @qh_legacy_lower = CONVERT(FROM_BASE64('c2Y=') USING utf8mb4) COLLATE utf8mb4_unicode_ci;
SET @qh_target_upper = CONVERT('QH' USING utf8mb4) COLLATE utf8mb4_unicode_ci;
SET @qh_target_lower = CONVERT('qh' USING utf8mb4) COLLATE utf8mb4_unicode_ci;
UPDATE `QH_config`
SET `value` = REPLACE(REPLACE(`value` COLLATE utf8mb4_unicode_ci, @qh_target_upper, @qh_legacy_upper), @qh_target_lower, @qh_legacy_lower),
    `tip` = REPLACE(REPLACE(`tip` COLLATE utf8mb4_unicode_ci, @qh_target_upper, @qh_legacy_upper), @qh_target_lower, @qh_legacy_lower)
WHERE `name` IN ('title', 'keywords', 'description', 'foot', 'cdkey_head');

UPDATE `QH_config`
SET `value` = CONCAT(@qh_legacy_lower, '-2129876388')
WHERE `name` = 'api_key'
  AND BINARY `value` = BINARY CONCAT(@qh_target_lower, '-2129876388');

SET @qh_old_group_concat_max_len = @@SESSION.group_concat_max_len;
SET SESSION group_concat_max_len = 65535;

SELECT GROUP_CONCAT(
  CONCAT(
    '`', REPLACE(src.TABLE_SCHEMA, '`', '``'), '`.`', REPLACE(src.TABLE_NAME, '`', '``'),
    '` TO `', REPLACE(src.TABLE_SCHEMA, '`', '``'), '`.`',
    REPLACE(CONCAT(@qh_legacy_prefix, SUBSTRING(src.TABLE_NAME, CHAR_LENGTH(@qh_target_prefix) + 1)), '`', '``'), '`'
  )
  ORDER BY src.TABLE_NAME
  SEPARATOR ', '
)
INTO @qh_rename_pairs
FROM information_schema.TABLES src
WHERE src.TABLE_SCHEMA = DATABASE()
  AND src.TABLE_TYPE = 'BASE TABLE'
  AND LEFT(src.TABLE_NAME, CHAR_LENGTH(@qh_target_prefix)) = @qh_target_prefix
  AND NOT EXISTS (
    SELECT 1
    FROM information_schema.TABLES dest
    WHERE dest.TABLE_SCHEMA = src.TABLE_SCHEMA
      AND dest.TABLE_NAME = CONCAT(@qh_legacy_prefix, SUBSTRING(src.TABLE_NAME, CHAR_LENGTH(@qh_target_prefix) + 1))
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

SET @qh_legacy_prefix = NULL;
SET @qh_target_prefix = NULL;
SET @qh_rename_pairs = NULL;
SET @qh_rename_sql = NULL;
SET @qh_old_group_concat_max_len = NULL;
SET @qh_legacy_upper = NULL;
SET @qh_legacy_lower = NULL;
SET @qh_target_upper = NULL;
SET @qh_target_lower = NULL;
