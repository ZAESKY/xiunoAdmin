-- Migration: merge user authorization entries into Auth/list tabs.
-- Idempotent: preserve the legacy route for compatibility, but hide its menu row.

INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '授权管理', 'Auth/list', 'layui-icon-auz', 0, NOW(), 0, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `SF_menu` WHERE `url` = 'Auth/list');

UPDATE `SF_menu`
SET `name` = '授权管理', `icon` = 'layui-icon-auz', `parentid` = 0, `status` = 1
WHERE `url` = 'Auth/list';

UPDATE `SF_menu`
SET `status` = 0
WHERE `power` = 2 AND `url` IN ('MyList/auth', 'MyList/payment');
