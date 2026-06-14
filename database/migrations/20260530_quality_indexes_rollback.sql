-- Rollback for 20260530_quality_indexes.sql.
-- Drops only indexes created by that migration.

ALTER TABLE `SF_auth` DROP INDEX `idx_user_addtime`;
ALTER TABLE `SF_cdkey` DROP INDEX `idx_user_addtime`;
ALTER TABLE `SF_user` DROP INDEX `idx_parent_addtime`;
ALTER TABLE `SF_user` DROP INDEX `idx_app_integral`;
ALTER TABLE `SF_user` DROP INDEX `idx_app_balance`;
ALTER TABLE `SF_log` DROP INDEX `idx_actor_admin_time`;
ALTER TABLE `SF_order` DROP INDEX `idx_status_addtime`;
