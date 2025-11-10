-- Verify stock transactions for a specific item
-- Replace 'ITEMNO_HERE' with the actual item number

-- Check all transactions for an item
SELECT 
    id,
    ITEMNO,
    transaction_type,
    quantity,
    stock_before,
    stock_after,
    reference_type,
    reference_id,
    notes,
    CREATED_ON
FROM item_transactions
WHERE ITEMNO = 'ITEMNO_HERE'
ORDER BY CREATED_ON DESC;

-- Calculate current stock from transactions
SELECT 
    ITEMNO,
    SUM(quantity) as calculated_stock,
    COUNT(*) as transaction_count
FROM item_transactions
WHERE ITEMNO = 'ITEMNO_HERE'
GROUP BY ITEMNO;

-- Compare with icitem.QTY (fixed collation issue)
-- This query handles collation mismatch by using BINARY comparison
SELECT 
    i.ITEMNO,
    i.DESP,
    i.QTY as icitem_qty,
    COALESCE(SUM(t.quantity), 0) as calculated_stock_from_transactions
FROM icitem i
LEFT JOIN item_transactions t ON BINARY i.ITEMNO = BINARY t.ITEMNO
WHERE i.ITEMNO = 'ITEMNO_HERE'
GROUP BY i.ITEMNO, i.DESP, i.QTY;

-- Alternative query using COLLATE (if you know the target collation)
-- SELECT 
--     i.ITEMNO,
--     i.DESP,
--     i.QTY as icitem_qty,
--     COALESCE(SUM(t.quantity), 0) as calculated_stock_from_transactions
-- FROM icitem i
-- LEFT JOIN item_transactions t ON i.ITEMNO COLLATE utf8mb4_unicode_ci = t.ITEMNO COLLATE utf8mb4_unicode_ci
-- WHERE i.ITEMNO = 'ITEMNO_HERE'
-- GROUP BY i.ITEMNO, i.DESP, i.QTY;

