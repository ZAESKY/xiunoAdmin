-- Migration: enforce one normalized host per license
-- Idempotent: safe to execute repeatedly.

-- Keep the historical columns for wire/schema compatibility, but make the
-- product policy explicit in existing rows. All data changes are committed
-- together before the final metadata-only default change.
START TRANSACTION;

UPDATE `QH_license`
   SET `max_sites` = 1,
       `allow_cross_root` = 0
 WHERE `max_sites` <> 1 OR `allow_cross_root` <> 0;

-- Historical builds could create a primary plus secondary binding. Preserve
-- the oldest primary binding (or the oldest active row when no primary exists)
-- and deactivate every other row. Rows are retained for audit/recovery.
UPDATE `QH_license_site` AS `site`
INNER JOIN (
    SELECT `picked`.`license_id`, `picked`.`keep_id`
      FROM (
          SELECT `license_id`,
                 COALESCE(
                     MIN(CASE WHEN `role` = 'primary' THEN `id` END),
                     MIN(`id`)
                 ) AS `keep_id`
            FROM `QH_license_site`
           WHERE `status` = 1
           GROUP BY `license_id`
      ) AS `picked`
) AS `active`
        ON `active`.`license_id` = `site`.`license_id`
       SET `site`.`status` = 0,
           `site`.`last_seen_at` = NOW()
     WHERE `site`.`status` = 1
       AND `site`.`id` <> `active`.`keep_id`;

-- The sole active binding is always the primary binding in single-site mode.
UPDATE `QH_license_site` AS `site`
INNER JOIN (
    SELECT `picked`.`license_id`, `picked`.`keep_id`
      FROM (
          SELECT `license_id`, MIN(`id`) AS `keep_id`
            FROM `QH_license_site`
           WHERE `status` = 1
           GROUP BY `license_id`
      ) AS `picked`
) AS `active`
        ON `active`.`keep_id` = `site`.`id`
       SET `site`.`role` = 'primary'
     WHERE `site`.`status` = 1
       AND `site`.`role` <> 'primary';

-- Keep the denormalized host on the license row aligned with the sole binding.
UPDATE `QH_license` AS `license`
INNER JOIN `QH_license_site` AS `site`
        ON `site`.`license_id` = `license`.`license_id`
       AND `site`.`status` = 1
       SET `license`.`bound_host` = `site`.`host`,
           `license`.`updated_at` = NOW()
     WHERE `license`.`bound_host` <> `site`.`host`;

COMMIT;

ALTER TABLE `QH_license`
  MODIFY `max_sites` tinyint(4) NOT NULL DEFAULT 1 COMMENT '一个授权仅允许一个规范化域名',
  MODIFY `allow_cross_root` tinyint(1) NOT NULL DEFAULT 0 COMMENT '历史兼容字段，单站点模式固定为0';
