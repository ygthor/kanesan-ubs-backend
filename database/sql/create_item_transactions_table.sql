-- Create item_transactions table for inventory management
-- This table tracks all stock movements (in/out/adjustment) and can link to invoices

CREATE TABLE IF NOT EXISTS `item_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ITEMNO` VARCHAR(50) NOT NULL COMMENT 'Item number from icitem table',
  `transaction_type` ENUM('in', 'out', 'adjustment') NOT NULL COMMENT 'Type of transaction: in (stock in), out (stock out), adjustment (manual adjustment)',
  `quantity` DECIMAL(10, 2) NOT NULL COMMENT 'Quantity: positive for in, negative for out, can be positive/negative for adjustment',
  `reference_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Type of reference: invoice, adjustment, purchase, etc.',
  `reference_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Reference ID (e.g., invoice REFNO, adjustment id)',
  `notes` TEXT NULL DEFAULT NULL COMMENT 'Additional notes or remarks',
  `stock_before` DECIMAL(10, 2) NULL DEFAULT NULL COMMENT 'Stock quantity before this transaction',
  `stock_after` DECIMAL(10, 2) NULL DEFAULT NULL COMMENT 'Stock quantity after this transaction',
  `CREATED_BY` VARCHAR(255) NULL DEFAULT NULL,
  `UPDATED_BY` VARCHAR(255) NULL DEFAULT NULL,
  `CREATED_ON` TIMESTAMP NULL DEFAULT NULL,
  `UPDATED_ON` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_item_transactions_itemno` (`ITEMNO`),
  INDEX `idx_item_transactions_type` (`transaction_type`),
  INDEX `idx_item_transactions_reference` (`reference_type`, `reference_id`),
  INDEX `idx_item_transactions_created_on` (`CREATED_ON`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory transaction log for tracking stock movements';

