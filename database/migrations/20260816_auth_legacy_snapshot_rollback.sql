-- Rollback: 旧授权记录冻结快照
-- 对应 20260816_auth_legacy_snapshot.sql
--
-- ⚠ 强烈建议不要执行本回滚。
--   QH_auth_legacy 是老用户「旧码换新」的唯一依据，一旦删除，
--   已兑换/未兑换状态将全部丢失，老用户可能重复兑换或无法兑换。
--
--   本次迁移对 QH_auth 是只读的，保留 QH_auth_legacy 不会对任何业务造成影响，
--   因此正常回滚流程中「什么都不做」才是正确选择。
--
-- 仅在确认该表完全无用（例如迁移误建到了错误的数据库）时才执行以下语句。
-- 执行前请务必先导出备份：
--   mysqldump -u<user> -p <db> QH_auth_legacy > QH_auth_legacy_backup.sql

-- DROP TABLE IF EXISTS `QH_auth_legacy`;

SELECT '本回滚脚本默认不执行任何删除操作。如确需删除，请手动取消上一行的注释。' AS notice;
