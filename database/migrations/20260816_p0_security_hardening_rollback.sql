-- Rollback: P0 安全加固
-- 对应 20260816_p0_security_hardening.sql
--
-- ⚠ 执行本回滚会重新打开以下风险，仅在确认新逻辑导致线上故障时使用：
--     A-06 授权判定绕过、A-07 下载凭证可重放、A-09 签名可省略、A-05 用户身份可冒充
--
-- 建议的「软回滚」顺序（优先尝试，无需删表删列）：
--     UPDATE `QH_config` SET `value` = '0' WHERE `name` = 'plugin_api_user_strict';
--     UPDATE `QH_config` SET `value` = '0' WHERE `name` = 'plugin_api_sign_required';
--     UPDATE `QH_config` SET `value` = '1' WHERE `name` = 'download_legacy_sign_enabled';
--     UPDATE `QH_app`    SET `auth_enforce` = 2;
-- 软回滚即可恢复到加固前的对外行为，且保留全部审计能力。
--
-- 以下为「硬回滚」——彻底移除本次迁移引入的结构。
-- 幂等：可重复执行。

DELIMITER $$

DROP PROCEDURE IF EXISTS qh_drop_column_if_exists $$
CREATE PROCEDURE qh_drop_column_if_exists(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- 1. 移除授权判定模式列
CALL qh_drop_column_if_exists('QH_app', 'auth_enforce');

-- 2. 移除配置项
DELETE FROM `QH_config` WHERE `name` IN (
    'download_ticket_bind_ip',
    'download_legacy_sign_enabled',
    'plugin_api_sign_required',
    'plugin_api_sign_algo',
    'plugin_api_user_strict',
    'download_base_url',
    'force_https_download'
);

-- 3. 移除下载票据表
--    注意：表中只有短时效票据，删除不会丢失业务数据。
DROP TABLE IF EXISTS `QH_download_ticket`;

DROP PROCEDURE IF EXISTS qh_drop_column_if_exists;
