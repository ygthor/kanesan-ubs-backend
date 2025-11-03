-- SQL for linking receipts to invoices (debts)
-- This allows one receipt to pay for multiple invoices and one invoice to be paid by multiple receipts
-- 
-- Usage:
-- When creating a receipt from a debt (invoice), you can link the receipt to one or more invoices
-- by inserting records into this pivot table. The amount_applied field tracks how much of the
-- receipt payment is applied to each invoice.
--
-- Example:
-- INSERT INTO receipt_invoices (receipt_id, invoice_refno, amount_applied, created_at, updated_at)
-- VALUES (1, 'INV-2025-001', 1000.00, NOW(), NOW());

-- Create pivot table to link receipts to invoices
CREATE TABLE IF NOT EXISTS receipt_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id BIGINT UNSIGNED NOT NULL,
    invoice_refno VARCHAR(255) NOT NULL COMMENT 'References artrans.REFNO (invoice reference number/salesNo)',
    amount_applied DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Amount from this receipt applied to this invoice',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    
    -- Ensure one receipt-invoice combination is unique (can't apply same receipt to same invoice twice)
    UNIQUE KEY unique_receipt_invoice (receipt_id, invoice_refno),
    
    -- Indexes for faster lookups
    INDEX idx_receipt_id (receipt_id),
    INDEX idx_invoice_refno (invoice_refno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pivot table linking receipts to invoices. Allows many-to-many relationship. Note: invoice_refno references artrans.REFNO but without foreign key constraint.';

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
--     AND COLUMN_NAME IN ('REFNO', 'invoice_refno')
--     AND TABLE_NAME IN ('artrans', 'receipt_invoices');

