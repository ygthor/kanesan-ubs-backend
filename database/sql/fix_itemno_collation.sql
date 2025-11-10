-- Fix collation mismatch between icitem.ITEMNO and item_transactions.ITEMNO
-- This ensures both columns use the same collation for proper joins

-- Check current collations
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('icitem', 'item_transactions')
AND COLUMN_NAME = 'ITEMNO';

-- Option 1: Make item_transactions.ITEMNO match icitem.ITEMNO
-- First, check what collation icitem.ITEMNO uses
-- Then run the appropriate ALTER TABLE command below

-- If icitem.ITEMNO uses utf8mb4_unicode_ci:
-- ALTER TABLE item_transactions MODIFY COLUMN ITEMNO VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- If icitem.ITEMNO uses utf8mb4_general_ci:
-- ALTER TABLE item_transactions MODIFY COLUMN ITEMNO VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- Option 2: Make icitem.ITEMNO match item_transactions.ITEMNO
-- If item_transactions.ITEMNO uses utf8mb4_unicode_ci:
-- ALTER TABLE icitem MODIFY COLUMN ITEMNO VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- If item_transactions.ITEMNO uses utf8mb4_general_ci:
-- ALTER TABLE icitem MODIFY COLUMN ITEMNO VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- Recommended: Use utf8mb4_unicode_ci for both (better sorting/comparison)
-- ALTER TABLE item_transactions MODIFY COLUMN ITEMNO VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
-- ALTER TABLE icitem MODIFY COLUMN ITEMNO VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

