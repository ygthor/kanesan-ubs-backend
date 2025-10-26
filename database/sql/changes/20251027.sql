-- Add cheque_date column to receipts table
-- This column stores the date when a cheque was issued (for CHEQUE payment type)
ALTER TABLE receipts ADD COLUMN cheque_date TIMESTAMP NULL AFTER cheque_type;
