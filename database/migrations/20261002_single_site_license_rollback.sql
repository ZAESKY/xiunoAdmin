-- Rollback: restore the legacy two-site schema setting.
-- Exact reactivation of bindings intentionally requires restoring the database
-- backup created before migration; this rollback never guesses which disabled
-- historical binding should become active.

ALTER TABLE `SF_license`
  MODIFY `max_sites` tinyint(4) NOT NULL DEFAULT 2 COMMENT '旧版双站点授权上限',
  MODIFY `allow_cross_root` tinyint(1) NOT NULL DEFAULT 0 COMMENT '旧版跨根域控制字段';

UPDATE `SF_license`
   SET `max_sites` = 2
 WHERE `max_sites` = 1;
