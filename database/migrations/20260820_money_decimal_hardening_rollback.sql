-- Roll back only on the new production database if application compatibility requires it.
ALTER TABLE `QH_pay`
    MODIFY COLUMN `money` VARCHAR(32) NULL DEFAULT NULL;
