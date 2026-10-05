DELETE FROM `QH_config` WHERE `name`='plugin_commission_enabled';

UPDATE `QH_config`
SET `group`='function',
    `title`='插件佣金比例(%)',
    `tip`='平台从插件销售中抽取的佣金百分比',
    `type`='number',
    `rule`='',
    `extend`=''
WHERE `name`='plugin_commission_rate';
