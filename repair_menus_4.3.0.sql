-- QH 4.3.0 menu repair script.
-- Purpose: fix duplicated/hidden menus caused by wrong power values in earlier
-- migration scripts. Safe to run multiple times after migration_4.3.0.sql.

START TRANSACTION;

-- Remove obsolete and malformed menu rows.
DELETE FROM `QH_menu`
WHERE `url` IN (
  'Payment/list',
  'MyList/payment',
  'Set/template',
  '/Plugin/list',
  '/PluginOrder/list',
  '/PluginComment/list',
  '/Plugin/publish',
  '/Plugin/edit',
  '/Plugin/drop',
  '/Plugin/setStatus',
  '/UserPlugin/market',
  '/UserPlugin/list',
  '/UserPlugin/comments',
  '/UserPlugin/purchases'
);

-- Admin-only feature menus must be power=1. Earlier scripts inserted some of
-- them as power=0, which made them leak into the user panel.
UPDATE `QH_menu`
SET `power` = 1, `status` = 1
WHERE `url` IN (
  'Plugin/list',
  'PluginOrder/list',
  'PluginComment/list',
  'Feedback/list',
  'Checkin/list',
  'PointProduct/list'
);

-- Remove shared placeholder parents that belonged to admin-only features.
DELETE FROM `QH_menu`
WHERE `parentid` = 0
  AND `power` = 0
  AND `url` = '#'
  AND `name` IN ('插件中心', '积分管理');

-- Admin plugin center.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件中心', '#', 'layui-icon-component', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
);

SET @admin_plugin_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件列表', 'Plugin/list', '', @admin_plugin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Plugin/list' AND `power` = 1);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件订单', 'PluginOrder/list', '', @admin_plugin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PluginOrder/list' AND `power` = 1);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件评论', 'PluginComment/list', '', @admin_plugin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PluginComment/list' AND `power` = 1);

UPDATE `QH_menu`
SET `parentid` = @admin_plugin_id, `power` = 1, `status` = 1
WHERE `url` IN ('Plugin/list', 'PluginOrder/list', 'PluginComment/list');

-- Admin feedback and check-in are top-level entries.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '反馈管理', 'Feedback/list', 'layui-icon-email', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Feedback/list' AND `power` = 1);

UPDATE `QH_menu`
SET `name` = '反馈管理', `icon` = 'layui-icon-email', `parentid` = 0, `power` = 1, `status` = 1
WHERE `url` = 'Feedback/list';

DELETE FROM `QH_menu`
WHERE `parentid` = 0 AND `url` = '#' AND `name` = '反馈管理';

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '打卡管理', 'Checkin/list', 'layui-icon-date', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Checkin/list' AND `power` = 1);

UPDATE `QH_menu`
SET `name` = '打卡管理', `icon` = 'layui-icon-date', `parentid` = 0, `power` = 1, `status` = 1
WHERE `url` = 'Checkin/list';

-- Admin point management.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分管理', '#', 'layui-icon-cart-simple', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '积分管理' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
);

SET @admin_point_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '积分管理' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分商品', 'PointProduct/list', '', @admin_point_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointProduct/list' AND `power` = 1);

UPDATE `QH_menu`
SET `parentid` = @admin_point_id, `power` = 1, `status` = 1
WHERE `url` = 'PointProduct/list';

-- User-only menus must be power=2.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件中心', '#', 'layui-icon-util', 0, NOW(), 2, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 2
);

SET @user_plugin_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 2
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件市场', 'UserPlugin/market', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/market' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的插件', 'UserPlugin/list', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/list' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '评论管理', 'UserPlugin/comments', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/comments' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的购买', 'UserPlugin/purchases', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/purchases' AND `power` = 2);

UPDATE `QH_menu`
SET `parentid` = @user_plugin_id, `power` = 2, `status` = 1
WHERE `url` IN ('UserPlugin/market', 'UserPlugin/list', 'UserPlugin/comments', 'UserPlugin/purchases');

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '每日打卡', 'Checkin/index', 'layui-icon-date', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Checkin/index' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '意见反馈', 'Feedback/index', 'layui-icon-email', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Feedback/index' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '返利中心', 'Rebate/index', 'layui-icon-rmb', 0, NOW(), 2, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '返利中心' AND `parentid` = 0 AND `url` = 'Rebate/index' AND `power` = 2
);

SET @user_rebate_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '返利中心' AND `parentid` = 0 AND `url` = 'Rebate/index' AND `power` = 2
  ORDER BY `id` ASC LIMIT 1
);

UPDATE `QH_menu`
SET `parentid` = 0, `url` = 'Rebate/index', `icon` = 'layui-icon-rmb', `power` = 2, `status` = 1
WHERE `id` = @user_rebate_id;

UPDATE `QH_menu`
SET `status` = 0
WHERE `parentid` = @user_rebate_id AND `url` = 'Rebate/index';

UPDATE `QH_menu`
SET `parentid` = @user_rebate_id, `power` = 2, `status` = 1
WHERE `url` = 'Rebate/myRebateList' AND `power` = 2;

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分兑换', 'PointExchange/index', 'layui-icon-cart-simple', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointExchange/index' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分日志', 'PointLog/list', 'layui-icon-list', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointLog/list' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '余额日志', 'BalanceLog/list', 'layui-icon-list', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'BalanceLog/list' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '余额提现', 'Withdraw/index', 'layui-icon-rmb', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Withdraw/index' AND `power` = 2);

UPDATE `QH_menu`
SET `parentid` = 0, `power` = 2, `status` = 1
WHERE `url` IN ('Checkin/index', 'Feedback/index', 'PointExchange/index', 'PointLog/list', 'BalanceLog/list', 'Withdraw/index');

-- Keep one row for each concrete URL+power. This removes rows created by
-- repeated imports when the table has no unique key.
DELETE m1 FROM `QH_menu` m1
JOIN `QH_menu` m2
  ON m1.`id` > m2.`id`
 AND m1.`url` = m2.`url`
 AND m1.`power` = m2.`power`
WHERE m1.`url` NOT IN ('', '#');

-- Keep one placeholder parent for each name+power.
DELETE m1 FROM `QH_menu` m1
JOIN `QH_menu` m2
  ON m1.`id` > m2.`id`
 AND m1.`name` = m2.`name`
 AND m1.`url` = m2.`url`
 AND m1.`power` = m2.`power`
 AND m1.`parentid` = m2.`parentid`
WHERE m1.`url` IN ('', '#');

COMMIT;

-- Verification:
-- SELECT id, name, url, parentid, power, status FROM QH_menu
-- WHERE url IN (
--   'Plugin/list','PluginOrder/list','PluginComment/list','Feedback/list','Checkin/list','PointProduct/list',
--   'UserPlugin/market','UserPlugin/list','UserPlugin/comments','UserPlugin/purchases',
--   'Checkin/index','Feedback/index','Rebate/index','Rebate/myRebateList',
--   'PointExchange/index','PointLog/list','BalanceLog/list','Withdraw/index'
-- )
-- ORDER BY power, parentid, id;
--
-- SELECT url, power, COUNT(*) c FROM QH_menu
-- WHERE url NOT IN ('', '#')
-- GROUP BY url, power HAVING c > 1;
