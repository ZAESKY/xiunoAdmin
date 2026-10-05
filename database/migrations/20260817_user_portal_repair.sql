-- Repair the upgraded ordinary-user portal menu and PHP 8.2-visible routes.
-- Idempotent; run only against the new production database.

-- Normalize the legacy "我的授权" parent/children layout to the current
-- single-page entry. The legacy MyList/payment action no longer exists.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的授权', 'MyList/auth', 'layui-icon-face-smile-b', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `QH_menu`
    WHERE `name` = '我的授权' AND `parentid` = 0 AND `power` = 2
);

SET @user_auth_menu_id := (
    SELECT `id` FROM `QH_menu`
    WHERE `name` = '我的授权' AND `parentid` = 0 AND `power` = 2
    ORDER BY `id` LIMIT 1
);

UPDATE `QH_menu`
SET `name` = '我的授权', `url` = 'MyList/auth',
    `icon` = 'layui-icon-face-smile-b', `parentid` = 0,
    `power` = 2, `status` = 1
WHERE `id` = @user_auth_menu_id;

UPDATE `QH_menu`
SET `status` = 0
WHERE `power` = 2
  AND `id` <> @user_auth_menu_id
  AND (`url` IN ('MyList/auth', 'MyList/payment') OR `parentid` = @user_auth_menu_id);

-- User plugin center.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件中心', '#', 'layui-icon-util', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `QH_menu`
    WHERE `name` = '插件中心' AND `url` = '#' AND `parentid` = 0 AND `power` = 2
);

SET @user_plugin_menu_id := (
    SELECT `id` FROM `QH_menu`
    WHERE `name` = '插件中心' AND `url` = '#' AND `parentid` = 0 AND `power` = 2
    ORDER BY `id` LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件市场', 'UserPlugin/market', '', @user_plugin_menu_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/market' AND `power` = 2);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的插件', 'UserPlugin/list', '', @user_plugin_menu_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/list' AND `power` = 2);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '发布插件', 'UserPlugin/create', '', @user_plugin_menu_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/create' AND `power` = 2);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '评论管理', 'UserPlugin/comments', '', @user_plugin_menu_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/comments' AND `power` = 2);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的购买', 'UserPlugin/purchases', '', @user_plugin_menu_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/purchases' AND `power` = 2);

UPDATE `QH_menu`
SET `parentid` = @user_plugin_menu_id, `power` = 2, `status` = 1
WHERE `url` IN ('UserPlugin/market', 'UserPlugin/list', 'UserPlugin/create',
                'UserPlugin/comments', 'UserPlugin/purchases')
  AND `power` = 2;

-- Current top-level user functions.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '意见反馈', 'Feedback/index', 'layui-icon-email', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Feedback/index' AND `power` = 2);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分兑换', 'PointExchange/list', 'layui-icon-cart-simple', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointExchange/list' AND `power` = 2);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '余额提现', 'Withdraw/index', 'layui-icon-rmb', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Withdraw/index' AND `power` = 2);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '返利中心', 'Rebate/index', 'layui-icon-rmb', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Rebate/index' AND `power` = 2);

UPDATE `QH_menu`
SET `parentid` = 0, `power` = 2, `status` = 1
WHERE `url` IN ('Feedback/index', 'PointExchange/list', 'Withdraw/index', 'Rebate/index')
  AND `power` = 2;

UPDATE `QH_menu`
SET `name` = CASE `url`
        WHEN 'Feedback/index' THEN '意见反馈'
        WHEN 'PointExchange/list' THEN '积分兑换'
        WHEN 'Withdraw/index' THEN '余额提现'
        WHEN 'Rebate/index' THEN '返利中心'
        ELSE `name`
    END,
    `icon` = CASE `url`
        WHEN 'Feedback/index' THEN 'layui-icon-email'
        WHEN 'PointExchange/list' THEN 'layui-icon-cart-simple'
        WHEN 'Withdraw/index' THEN 'layui-icon-rmb'
        WHEN 'Rebate/index' THEN 'layui-icon-rmb'
        ELSE `icon`
    END
WHERE `url` IN ('Feedback/index', 'PointExchange/list', 'Withdraw/index', 'Rebate/index')
  AND `power` = 2;

-- These functions are now tabs/cards in the user home or personal center,
-- not separate sidebar entries. Disable legacy rows instead of deleting them.
UPDATE `QH_menu`
SET `status` = 0
WHERE `power` = 2
  AND `url` IN ('Checkin/index', 'Checkin/list', 'Checkin/records',
                'PointLog/list', 'PointLog/index',
                'BalanceLog/list', 'BalanceLog/index');
