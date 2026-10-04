-- New production database only: store payment amounts as exact decimals.
-- Precondition: SF_pay.money contains only non-negative values with at most 2 decimals.
ALTER TABLE `SF_pay`
    MODIFY COLUMN `money` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
