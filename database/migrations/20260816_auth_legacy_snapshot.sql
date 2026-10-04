-- Migration: 旧授权记录冻结快照
-- 用途：为「新老并行 + 旧码换新」提供永不变更的兑换依据（用户需求 ①）
--
-- 设计意图：
--   SF_auth_legacy 是 SF_auth 在本次改造前的一次性全量快照，与在线业务表物理分离。
--   无论后续 v2 授权体系如何演进、SF_auth 如何重构，这张表都保持只读不变，
--   保证任何一位老用户在任何时候都能凭旧授权码换取新授权。
--
-- 安全性：
--   本迁移只做「读 SF_auth / 写新表」，不修改、不删除任何既有数据。
--
-- 幂等：可重复执行。快照只在目标表为空时写入一次，重复执行不会覆盖已有兑换状态。
-- 兼容 MySQL 5.7+/8.0+

DELIMITER $$

DROP PROCEDURE IF EXISTS sf_build_auth_legacy $$
CREATE PROCEDURE sf_build_auth_legacy()
BEGIN
    DECLARE v_legacy_exists INT DEFAULT 0;
    DECLARE v_legacy_rows   INT DEFAULT 0;
    DECLARE v_has_col       INT DEFAULT 0;

    SELECT COUNT(*) INTO v_legacy_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_auth_legacy';

    -- 1) 首次执行：以 SF_auth 的结构创建快照表，并整表复制一次
    IF v_legacy_exists = 0 THEN
        CREATE TABLE `SF_auth_legacy` LIKE `SF_auth`;
        INSERT INTO `SF_auth_legacy` SELECT * FROM `SF_auth`;
    END IF;

    -- 2) 追加兑换状态列（与 SF_auth 结构解耦，后续互不影响）
    SELECT COUNT(*) INTO v_has_col FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_auth_legacy' AND COLUMN_NAME = 'snapshot_at';
    IF v_has_col = 0 THEN
        ALTER TABLE `SF_auth_legacy`
            ADD COLUMN `snapshot_at` datetime NULL DEFAULT NULL COMMENT '快照写入时间';
    END IF;

    SELECT COUNT(*) INTO v_has_col FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_auth_legacy' AND COLUMN_NAME = 'redeemed_at';
    IF v_has_col = 0 THEN
        ALTER TABLE `SF_auth_legacy`
            ADD COLUMN `redeemed_at` datetime NULL DEFAULT NULL COMMENT '兑换为新授权的时间，NULL 表示尚未兑换';
    END IF;

    SELECT COUNT(*) INTO v_has_col FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_auth_legacy' AND COLUMN_NAME = 'redeemed_license_id';
    IF v_has_col = 0 THEN
        ALTER TABLE `SF_auth_legacy`
            ADD COLUMN `redeemed_license_id` char(32) NULL DEFAULT NULL COMMENT '兑换后对应的新 license_id';
    END IF;

    SELECT COUNT(*) INTO v_has_col FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_auth_legacy' AND COLUMN_NAME = 'redeem_note';
    IF v_has_col = 0 THEN
        ALTER TABLE `SF_auth_legacy`
            ADD COLUMN `redeem_note` varchar(255) NOT NULL DEFAULT '' COMMENT '兑换备注(人工审核记录等)';
    END IF;

    -- 3) 补齐快照时间（只补 NULL 行，已兑换记录不受影响）
    UPDATE `SF_auth_legacy` SET `snapshot_at` = NOW() WHERE `snapshot_at` IS NULL;

    -- 4) 兑换查询所需索引
    SELECT COUNT(*) INTO v_has_col FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_auth_legacy' AND INDEX_NAME = 'idx_legacy_authcode';
    IF v_has_col = 0 THEN
        ALTER TABLE `SF_auth_legacy` ADD INDEX `idx_legacy_authcode` (`authcode`);
    END IF;

    SELECT COUNT(*) INTO v_has_col FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'SF_auth_legacy' AND INDEX_NAME = 'idx_legacy_redeemed';
    IF v_has_col = 0 THEN
        ALTER TABLE `SF_auth_legacy` ADD INDEX `idx_legacy_redeemed` (`redeemed_at`);
    END IF;

    SELECT COUNT(*) INTO v_legacy_rows FROM `SF_auth_legacy`;
    SELECT v_legacy_rows AS legacy_rows_snapshotted;
END $$

DELIMITER ;

CALL sf_build_auth_legacy();

DROP PROCEDURE IF EXISTS sf_build_auth_legacy;
