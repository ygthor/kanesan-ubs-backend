-- Check current transaction_type enum values
-- Run this to verify if the new types have been added

SHOW COLUMNS FROM `item_transactions` WHERE Field = 'transaction_type';

-- Expected output should show:
-- ENUM('in', 'out', 'adjustment', 'invoice_sale', 'invoice_return')

