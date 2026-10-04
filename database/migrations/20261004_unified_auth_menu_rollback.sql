-- Rollback: restore the legacy "我的授权" sidebar entry.
UPDATE `SF_menu`
SET `name` = '我的授权', `icon` = 'layui-icon-face-smile-b', `parentid` = 0, `status` = 1
WHERE `power` = 2 AND `url` = 'MyList/auth';
