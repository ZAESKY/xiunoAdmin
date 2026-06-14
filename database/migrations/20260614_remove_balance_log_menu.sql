-- Remove the standalone "余额日志" left-side menu entry.
-- 余额日志已合并到个人中心余额明细tab，移除独立菜单项（彻底删除，避免历史菜单残留）。
-- Safe to run repeatedly on an existing SF_* database.

DELETE FROM `SF_menu`
WHERE `url` IN ('BalanceLog/list', 'BalanceLog/index') AND `power` = 2;
