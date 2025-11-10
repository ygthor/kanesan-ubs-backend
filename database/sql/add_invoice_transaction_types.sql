-- Add invoice_sale and invoice_return transaction types to item_transactions table
-- This updates the transaction_type enum to include new invoice-related types

ALTER TABLE `item_transactions` 
MODIFY COLUMN `transaction_type` 
ENUM('in', 'out', 'adjustment', 'invoice_sale', 'invoice_return') 
NOT NULL 
COMMENT 'Type of transaction: in (stock in), out (stock out), adjustment (manual adjustment), invoice_sale (stock out for invoice), invoice_return (stock return for credit note/refund)';

