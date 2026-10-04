-- Repair missing check-in settings and expose withdrawal settings in their
-- dedicated admin configuration tab. Safe to run repeatedly.

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'checkin_enabled', 'checkin', '启用打卡功能', '开启后用户可在面板首页进行每日打卡获取积分', 'bool', '1', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'checkin_enabled');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'checkin_base_points', 'checkin', '单次打卡积分', '用户每次打卡获得的基础积分', 'number', '5', '', 'required', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'checkin_base_points');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'checkin_consecutive_days', 'checkin', '连续打卡天数阈值', '使用英文逗号分隔，并与奖励积分逐项对应，例如：3,7,15,30', 'string', '3,7,15,30', '', 'required', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'checkin_consecutive_days');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'checkin_consecutive_bonus', 'checkin', '连续打卡奖励积分', '使用英文逗号分隔，并与天数阈值逐项对应，例如：3,7,15,30', 'string', '3,7,15,30', '', 'required', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'checkin_consecutive_bonus');

-- Normalize the legacy seed format so CheckinService can parse the values.
UPDATE `SF_config`
SET `type` = 'string',
    `value` = CASE
        WHEN `value` = '{"field":["3","7","15","30"]}' THEN '3,7,15,30'
        ELSE `value`
    END,
    `rule` = 'required'
WHERE `name` IN ('checkin_consecutive_days', 'checkin_consecutive_bonus');

-- These settings were previously placed in the generic "功能配置" tab even
-- though the application already defines a dedicated "提现配置" tab.
UPDATE `SF_config`
SET `group` = 'withdraw'
WHERE `name` IN ('withdraw_enable', 'withdraw_min_amount', 'withdraw_interval');

UPDATE `SF_config`
SET `title` = '允许提交提现',
    `tip` = '关闭后保留提现记录访问，但用户不能提交新的提现申请'
WHERE `name` = 'withdraw_enable';

UPDATE `SF_config`
SET `title` = '提现模块入口',
    `tip` = '控制用户端提现入口和管理员端提现管理入口；关闭后两个入口均不可访问'
WHERE `name` = 'feature_withdraw_enabled';
