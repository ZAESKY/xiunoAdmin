-- 插件中心用户端菜单添加SQL
-- power=2 表示仅用户端可见
-- 执行此SQL后，用户端刷新即可看到插件中心菜单

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
('插件中心', '/UserPlugin/market', 'layui-icon-util', 0, NOW(), 2, 1);

SET @plugin_center_user_id = LAST_INSERT_ID();

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
('插件市场', '/UserPlugin/market', '', @plugin_center_user_id, NOW(), 2, 1),
('我的插件', '/UserPlugin/list', '', @plugin_center_user_id, NOW(), 2, 1),
('评论管理', '/UserPlugin/comments', '', @plugin_center_user_id, NOW(), 2, 1),
('我的购买', '/UserPlugin/purchases', '', @plugin_center_user_id, NOW(), 2, 1);
