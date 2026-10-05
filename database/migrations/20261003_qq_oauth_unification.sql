-- Migration: unify official QQ OAuth and persist one-time verified legacy QQ claims.
-- Additive and safe to run repeatedly on MySQL 5.7+/8.0+.

CREATE TABLE IF NOT EXISTS `QH_qq_identity_claim` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(11) unsigned NOT NULL COMMENT 'QH_social_identity.id',
  `user_id` int(11) unsigned NOT NULL COMMENT 'QH_user.id',
  `legacy_qq` varchar(20) NOT NULL COMMENT '旧扫码一次性验证的数字QQ',
  `proof_method` varchar(32) NOT NULL DEFAULT 'legacy_qr',
  `verified_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_qq_claim_identity` (`identity_id`),
  UNIQUE KEY `uk_qq_claim_user` (`user_id`),
  UNIQUE KEY `uk_qq_claim_number` (`legacy_qq`),
  KEY `idx_qq_claim_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='正规QQ身份与已验证历史QQ关系';
