-- This removes OAuth bindings. Use only after an explicit backup.

DROP TABLE IF EXISTS `QH_user_social_identity`;
DROP TABLE IF EXISTS `QH_social_identity`;
