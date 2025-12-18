-- SQL script to update existing customer_type values to uppercase
-- Run this script to update all existing customer records

-- STEP 1: Check current values BEFORE update
SELECT 'BEFORE UPDATE - Current customer_type values:' as info;
SELECT DISTINCT customer_type, COUNT(*) as count
FROM customers
WHERE customer_type IS NOT NULL
GROUP BY customer_type
ORDER BY customer_type;

-- STEP 2: Update all customer_type values to uppercase using CASE statement
-- This explicitly handles each known value to ensure proper conversion
UPDATE customers 
SET customer_type = CASE 
  WHEN LOWER(TRIM(customer_type)) = 'creditor' THEN 'CREDITOR'
  WHEN LOWER(TRIM(customer_type)) = 'cash sales' THEN 'CASH SALES'
  WHEN LOWER(TRIM(customer_type)) = 'cash' THEN 'CASH'
  ELSE UPPER(TRIM(customer_type))
END
WHERE customer_type IS NOT NULL;

-- STEP 3: Verify the updates - Check what we have AFTER update
SELECT 'AFTER UPDATE - Updated customer_type values:' as info;
SELECT DISTINCT customer_type, COUNT(*) as count
FROM customers
WHERE customer_type IS NOT NULL
GROUP BY customer_type
ORDER BY customer_type;

-- STEP 4: Check if any lowercase values still exist (should return 0 rows)
SELECT 'Check for remaining lowercase values (should be empty):' as info;
SELECT customer_type, COUNT(*) as count
FROM customers
WHERE customer_type IS NOT NULL
  AND customer_type != UPPER(customer_type)
GROUP BY customer_type;

-- ============================================================================
-- ALTERNATIVE METHOD (if the above doesn't work):
-- Use this simpler approach if the CASE statement has issues
-- ============================================================================
-- UPDATE customers 
-- SET customer_type = UPPER(TRIM(customer_type))
-- WHERE customer_type IS NOT NULL
--   AND customer_type COLLATE utf8mb4_bin != UPPER(TRIM(customer_type)) COLLATE utf8mb4_bin;
--
-- Note: The COLLATE utf8mb4_bin forces case-sensitive comparison
-- Adjust the collation name based on your database charset (could be utf8_bin, latin1_bin, etc.)
