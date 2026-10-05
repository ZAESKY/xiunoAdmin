-- Synchronize the upgraded admin menu with the current application code.
-- Idempotent and intended to run only against the new production database.

-- Disable a legacy entry whose controller/view no longer exists.
UPDATE `QH_menu`
SET `status` = 0
WHERE `url` = 'Payment/list' AND `power` IN (0, 1);

-- Admin plugin center.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件中心', '#', 'layui-icon-component', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '插件中心' AND `url` = '#' AND `parentid` = 0 AND `power` = 1
);

SET @admin_plugin_id := (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '插件中心' AND `url` = '#' AND `parentid` = 0 AND `power` = 1
  ORDER BY `id` LIMIT 1
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
WHERE `url` IN ('Plugin/list', 'PluginOrder/list', 'PluginComment/list') AND `power` = 1;

-- Feedback management.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '反馈管理', 'Feedback/list', 'layui-icon-email', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Feedback/list' AND `power` = 1);
UPDATE `QH_menu`
SET `name` = '反馈管理', `icon` = 'layui-icon-email', `parentid` = 0, `power` = 1, `status` = 1
WHERE `url` = 'Feedback/list' AND `power` = 1;

-- Point product and exchange management.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分管理', '#', 'layui-icon-cart-simple', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '积分管理' AND `url` = '#' AND `parentid` = 0 AND `power` = 1
);

SET @admin_point_id := (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '积分管理' AND `url` = '#' AND `parentid` = 0 AND `power` = 1
  ORDER BY `id` LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分商品', 'PointProduct/list', '', @admin_point_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointProduct/list' AND `power` = 1);
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '兑换记录', 'PointProduct/records', '', @admin_point_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointProduct/records' AND `power` = 1);
UPDATE `QH_menu`
SET `parentid` = @admin_point_id, `power` = 1, `status` = 1
WHERE `url` IN ('PointProduct/list', 'PointProduct/records') AND `power` = 1;

-- Withdraw management.
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '提现管理', 'Order/withdraw', 'layui-icon-rmb', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Order/withdraw' AND `power` = 1);
UPDATE `QH_menu`
SET `name` = '提现管理', `icon` = 'layui-icon-rmb', `parentid` = 0, `power` = 1, `status` = 1
WHERE `url` = 'Order/withdraw' AND `power` = 1;

-- Feature flags used by menu filtering and access guards.
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'feature_point_exchange_enabled', 'feature_access', '积分兑换', '关闭后用户端积分兑换、管理员端积分商品/兑换记录均不可访问', 'bool', '1', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'feature_point_exchange_enabled');
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'feature_user_plugin_enabled', 'feature_access', '用户插件中心', '关闭后用户端插件市场、发布插件、我的插件、我的购买等页面均不可访问', 'bool', '1', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'feature_user_plugin_enabled');
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'feature_admin_plugin_enabled', 'feature_access', '管理员插件管理', '关闭后管理员端插件列表、插件订单、插件评论等页面均不可访问', 'bool', '1', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'feature_admin_plugin_enabled');
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'feature_withdraw_enabled', 'feature_access', '提现功能', '关闭后用户端提现记录/申请、管理员端提现管理均不可访问', 'bool', '1', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'feature_withdraw_enabled');
