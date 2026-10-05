-- Metadata rollback only. Consumed plugin/version packages remain referenced.
-- Pending objects should be cleaned before invoking this rollback.
DROP TABLE IF EXISTS `QH_plugin_package_upload`;
SELECT '20261004_plugin_package_upload_staging rolled back' AS migration_result;
