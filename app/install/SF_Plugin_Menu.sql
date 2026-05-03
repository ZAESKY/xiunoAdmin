-- 插件中心菜单添加SQL
-- 执行此SQL后，刷新页面即可看到插件中心菜单

-- 插入主菜单：插件中心
INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
('插件中心', '/Plugin/list', 'layui-icon-component', 0, NOW(), 0, 1);

-- 获取刚插入的主菜单ID
SET @plugin_center_id = LAST_INSERT_ID();

-- 插入子菜单
INSERT INTO `SF_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
('插件列表', '/Plugin/list', '', @plugin_center_id, NOW(), 0, 1),
('插件订单', '/PluginOrder/list', '', @plugin_center_id, NOW(), 0, 1),
('插件评论', '/PluginComment/list', '', @plugin_center_id, NOW(), 0, 1);

-- 清理菜单缓存（如果使用了缓存）
-- 注意：执行完SQL后，需要在后台清理缓存或重启PHP服务
