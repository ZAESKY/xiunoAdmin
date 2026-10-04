-- Rollback for 20261003_qq_oauth_unification.sql.
-- Only removes the additive claim table; existing QQ OAuth identity mappings remain intact.

DROP TABLE IF EXISTS `SF_qq_identity_claim`;
