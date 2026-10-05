-- Rollback: P1–P3 授权体系 v2
-- 对应 20260816_p1_p3_license_v2.sql
--
-- 说明：v2 与 v1 完全并行，v1 接口不依赖本次任何新表。
-- 因此「软回滚」= 停止对外暴露 v2 路由即可，无需删表：
--     把 app/api/route/route.php 重命名或清空，v2 路径立即 404，
--     v1（/api.php/Auth/*）继续正常服务。
--
-- ⚠ 硬回滚会删除已签发的 v2 授权、站点绑定与全部审计日志。
--   若已有客户完成激活或旧码兑换，删除后这些客户将无法验证授权，
--   且 QH_auth_legacy 中的 redeemed_at 仍为已兑换状态 —— 会造成
--   「既不能用新授权、也不能重新兑换」的死锁。
--
--   如确需硬回滚，必须同时清除兑换标记：
--     UPDATE `QH_auth_legacy` SET `redeemed_at` = NULL, `redeemed_license_id` = NULL;
--
-- 幂等：可重复执行。

DELIMITER $$

DROP PROCEDURE IF EXISTS qh_drop_column_if_exists $$
CREATE PROCEDURE qh_drop_column_if_exists(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column)
    THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- P1 列（删除后 v1 行为完全不变，因为 v1 只读 authcode 明文列）
CALL qh_drop_column_if_exists('QH_auth', 'authcode_hash');
CALL qh_drop_column_if_exists('QH_auth', 'authcode_last4');
CALL qh_drop_column_if_exists('QH_auth', 'must_rotate');
CALL qh_drop_column_if_exists('QH_auth', 'pepper_version');

CALL qh_drop_column_if_exists('QH_download_ticket', 'license_id');

-- P2 / P3 表
DROP TABLE IF EXISTS `QH_license_event`;
DROP TABLE IF EXISTS `QH_license_site`;
DROP TABLE IF EXISTS `QH_offline_activation`;
DROP TABLE IF EXISTS `QH_trial`;
DROP TABLE IF EXISTS `QH_license`;
DROP TABLE IF EXISTS `QH_patch`;
DROP TABLE IF EXISTS `QH_release`;

DROP PROCEDURE IF EXISTS qh_drop_column_if_exists;

SELECT '硬回滚完成。若此前已有客户兑换过旧码，请务必执行上方注释中的 QH_auth_legacy 重置语句。' AS notice;
