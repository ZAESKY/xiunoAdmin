-- 为 SF_notification 表添加 link 字段
-- 执行时间: 2026-05-03

ALTER TABLE `SF_notification` ADD COLUMN `link` varchar(255) DEFAULT '' COMMENT '跳转链接' AFTER `type`;
