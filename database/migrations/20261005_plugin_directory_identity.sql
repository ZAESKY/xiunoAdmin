-- Separate the public market slug from the Xiuno installation directory.
-- Existing packages are backfilled only when their historical file naming is unambiguous.

DROP PROCEDURE IF EXISTS QH_ADD_COLUMN_IF_MISSING;

DELIMITER $$
CREATE PROCEDURE QH_ADD_COLUMN_IF_MISSING(
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
    SET @qh_sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_definition);
    PREPARE qh_stmt FROM @qh_sql;
    EXECUTE qh_stmt;
    DEALLOCATE PREPARE qh_stmt;
  END IF;
END$$
DELIMITER ;

CALL QH_ADD_COLUMN_IF_MISSING(
  'QH_plugin',
  'plugin_dir',
  '`plugin_dir` varchar(64) NOT NULL DEFAULT '''' COMMENT ''Xiuno插件安装目录'' AFTER `slug`'
);
CALL QH_ADD_COLUMN_IF_MISSING(
  'QH_plugin_package_upload',
  'plugin_dir',
  '`plugin_dir` varchar(64) NOT NULL DEFAULT '''' COMMENT ''ZIP唯一顶层插件目录'' AFTER `package_file_name`'
);

UPDATE `QH_plugin`
SET `plugin_dir` = LEFT(`package_file_name`, CHAR_LENGTH(`package_file_name`) - CHAR_LENGTH(CONCAT('_v', `version`, '.zip')))
WHERE `plugin_dir` = ''
  AND CHAR_LENGTH(`package_file_name`) > CHAR_LENGTH(CONCAT('_v', `version`, '.zip'))
  AND LOWER(RIGHT(`package_file_name`, CHAR_LENGTH(CONCAT('_v', `version`, '.zip')))) = LOWER(CONCAT('_v', `version`, '.zip'))
  AND LEFT(`package_file_name`, CHAR_LENGTH(`package_file_name`) - CHAR_LENGTH(CONCAT('_v', `version`, '.zip'))) REGEXP '^[A-Za-z0-9_]{1,64}$';

UPDATE `QH_plugin`
SET `plugin_dir` = `slug`
WHERE `plugin_dir` = '' AND `slug` REGEXP '^[A-Za-z0-9_]{1,64}$';

DROP PROCEDURE IF EXISTS QH_ADD_COLUMN_IF_MISSING;

SELECT `id`, `slug`, `plugin_dir`
FROM `QH_plugin`
WHERE `plugin_dir` = '' OR `plugin_dir` NOT REGEXP '^[A-Za-z0-9_]{1,64}$';
