-- SQL for linking invoices to orders
-- This allows tracking which order an invoice was created from
-- 
-- Usage:
-- When creating an invoice from an order, you can link the invoice to the order
-- by inserting a record into this pivot table.
--
-- Example:
-- INSERT INTO invoice_orders (invoice_refno, order_id, created_at, updated_at)
-- VALUES ('INV00001', 1, NOW(), NOW());

-- Create pivot table to link invoices to orders
CREATE TABLE IF NOT EXISTS invoice_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_refno VARCHAR(255) NOT NULL COMMENT 'References artrans.REFNO (invoice reference number)',
    order_id BIGINT UNSIGNED NOT NULL COMMENT 'References orders.id',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    -- Ensure one invoice-order combination is unique (can't link same invoice to same order twice)
    UNIQUE KEY unique_invoice_order (invoice_refno, order_id),
    
    -- Indexes for faster lookups
    INDEX idx_invoice_refno (invoice_refno),
    INDEX idx_order_id (order_id),
    
    -- Foreign key constraint for order_id
    CONSTRAINT fk_invoice_orders_order_id 
        FOREIGN KEY (order_id) 
        REFERENCES orders(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pivot table linking invoices to orders. Allows tracking which order an invoice was created from. Note: invoice_refno references artrans.REFNO but without foreign key constraint due to legacy table structure.';

-- Verification query (optional - run to check):
-- SELECT 
--     TABLE_NAME,
--     COLUMN_NAME,
--     COLUMN_TYPE,
--     CHARACTER_SET_NAME,
--     COLLATION_NAME
-- FROM 
--     INFORMATION_SCHEMA.COLUMNS 
-- WHERE 
--     TABLE_SCHEMA = DATABASE()
--     AND COLUMN_NAME IN ('REFNO', 'invoice_refno', 'id', 'order_id')
--     AND TABLE_NAME IN ('artrans', 'invoice_orders', 'orders');

