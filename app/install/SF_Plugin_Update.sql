-- 插件表补充字段迁移
-- 执行时间: 2026-05-03

ALTER TABLE `SF_plugin` ADD COLUMN `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '发布者用户ID' AFTER `id`;
ALTER TABLE `SF_plugin` ADD COLUMN `category` varchar(30) DEFAULT '' COMMENT '分类' AFTER `slug`;
ALTER TABLE `SF_plugin` ADD COLUMN `origin_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '来源:1=原创,2=转载' AFTER `images`;
ALTER TABLE `SF_plugin` ADD COLUMN `origin_url` varchar(255) DEFAULT '' COMMENT '转载来源地址' AFTER `origin_type`;
ALTER TABLE `SF_plugin` ADD COLUMN `origin_author` varchar(100) DEFAULT '' COMMENT '转载原作者' AFTER `origin_url`;
ALTER TABLE `SF_plugin` ADD COLUMN `origin_note` varchar(500) DEFAULT '' COMMENT '转载声明/备注' AFTER `origin_author`;

ALTER TABLE `SF_plugin_comment` ADD COLUMN `reply_content` text COMMENT '开发者回复内容' AFTER `status`;
ALTER TABLE `SF_plugin_comment` ADD COLUMN `reply_at` datetime DEFAULT NULL COMMENT '开发者回复时间' AFTER `reply_content`;

ALTER TABLE `SF_plugin_order` ADD COLUMN `commission_rate` decimal(5,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平台抽成比例(%)' AFTER `price`;
ALTER TABLE `SF_plugin_order` ADD COLUMN `commission_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平台抽成金额' AFTER `commission_rate`;
ALTER TABLE `SF_plugin_order` ADD COLUMN `developer_income` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '开发者收入' AFTER `commission_amount`;

ALTER TABLE `SF_notification` ADD COLUMN `link` varchar(255) DEFAULT '' COMMENT '跳转链接' AFTER `type`;
