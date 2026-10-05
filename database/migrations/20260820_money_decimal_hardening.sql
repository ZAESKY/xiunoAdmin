-- New production database only: store payment amounts as exact decimals.
-- Precondition: QH_pay.money contains only non-negative values with at most 2 decimals.
ALTER TABLE `QH_pay`
    MODIFY COLUMN `money` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
