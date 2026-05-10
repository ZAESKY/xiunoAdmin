-- Check-in feature tables and config
-- Table structure for check-in records
CREATE TABLE IF NOT EXISTS `SF_checkin_record` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT '用户ID',
    `checkin_date` DATE NOT NULL COMMENT '打卡日期',
    `consecutive_days` INT(11) NOT NULL DEFAULT 1 COMMENT '连续打卡天数',
    `points_earned` INT(11) NOT NULL DEFAULT 0 COMMENT '获得积分',
    `ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '打卡IP',
    `created_at` DATETIME NOT NULL COMMENT '打卡时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_date` (`user_id`, `checkin_date`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_checkin_date` (`checkin_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡记录表';

-- Config entries for check-in settings
INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`) VALUES
('checkin_enabled', 'checkin', '启用打卡功能', '开启后用户可在面板首页进行每日打卡获取积分', 'bool', '1', '', '', '', ''),
('checkin_base_points', 'checkin', '单次打卡积分', '用户每次打卡获得的基础积分', 'number', '5', '', 'required', '', ''),
('checkin_consecutive_days', 'checkin', '连续打卡天数阈值', '达到指定连续天数时发放额外奖励，与下方奖励积分一一对应', 'array', '{"field":["3","7","15","30"]}', '', '', '', ''),
('checkin_consecutive_bonus', 'checkin', '连续打卡奖励积分', '达到对应连续天数时额外奖励的积分，与上方天数阈值一一对应', 'array', '{"field":["3","7","15","30"]}', '', '', '', '');
