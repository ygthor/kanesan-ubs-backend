-- Fix collation mismatch between customers.customer_code and artrans.CUSTNO
-- This will make the JOIN operations work properly

-- Option 1: Update artrans.CUSTNO to match customers.customer_code collation
ALTER TABLE artrans MODIFY COLUMN CUSTNO VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Option 2: Update customers.customer_code to match artrans.CUSTNO collation (if preferred)
-- ALTER TABLE customers MODIFY COLUMN customer_code VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Verify the collations after running the above command
-- SELECT 
--     TABLE_NAME,
--     COLUMN_NAME,
--     CHARACTER_SET_NAME,
--     COLLATION_NAME
-- FROM 
--     INFORMATION_SCHEMA.COLUMNS 
-- WHERE 
--     TABLE_SCHEMA = DATABASE()
--     AND COLUMN_NAME IN ('customer_code', 'CUSTNO')
--     AND TABLE_NAME IN ('customers', 'artrans');
