DELETE FROM `QH_config` WHERE `name` IN (
  'plugin_reward_enabled',
  'plugin_reward_points',
  'plugin_reward_balance',
  'plugin_reward_original_only',
  'plugin_reward_monthly_limit',
  'plugin_reward_min_account_days',
  'plugin_reward_duplicate_hash'
);

DROP TABLE IF EXISTS `QH_plugin_reward_hash_claim`;
DROP TABLE IF EXISTS `QH_plugin_reward`;
