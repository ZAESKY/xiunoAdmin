-- This removes OAuth bindings. Use only after an explicit backup.

DROP TABLE IF EXISTS `SF_user_social_identity`;
DROP TABLE IF EXISTS `SF_social_identity`;
