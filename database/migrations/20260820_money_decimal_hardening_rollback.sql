-- Roll back only on the new production database if application compatibility requires it.
ALTER TABLE `SF_pay`
    MODIFY COLUMN `money` VARCHAR(32) NULL DEFAULT NULL;
