-- Configurable platform commission for paid plugin sales.
-- Existing installations keep the historical behavior: enabled, 10% by default.

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_commission_enabled','plugin_market','启用插件销售平台抽成','开启后，余额及在线支付的插件订单按设置比例抽成；关闭后发布者获得全部销售收入。积分支付始终免抽成。','bool','1','','','',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name`='plugin_commission_enabled');

INSERT INTO `QH_config` (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`,`tip_type`)
SELECT 'plugin_commission_rate','plugin_market','插件销售平台抽成比例（%）','仅在抽成开关开启时生效，范围 0～100，最多保留两位小数；新比例仅影响后续支付成功的订单。','number','10.00','','required','min="0" max="100" step="0.01"',''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name`='plugin_commission_rate');

UPDATE `QH_config`
SET `group`='plugin_market',
    `title`='启用插件销售平台抽成',
    `tip`='开启后，余额及在线支付的插件订单按设置比例抽成；关闭后发布者获得全部销售收入。积分支付始终免抽成。',
    `type`='bool'
WHERE `name`='plugin_commission_enabled';

UPDATE `QH_config`
SET `group`='plugin_market',
    `title`='插件销售平台抽成比例（%）',
    `tip`='仅在抽成开关开启时生效，范围 0～100，最多保留两位小数；新比例仅影响后续支付成功的订单。',
    `type`='number',
    `rule`='required',
    `extend`='min="0" max="100" step="0.01"'
WHERE `name`='plugin_commission_rate';
