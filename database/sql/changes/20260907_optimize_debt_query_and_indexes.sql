-- ============================================================================
-- Migration: 20260907_optimize_debt_query_and_indexes.sql
-- Description: Performance optimization for customer debts query (/api/debts)
--              and Review Debt page.
--
-- 1. Align character set & collation for join columns (orders.reference_no and receipt_orders.order_refno)
--    so joins do not need runtime COLLATE conversion, enabling standard B-Tree index lookup.
-- 2. Add covering indexes for receipt_orders payments aggregation and orders credit note aggregation.
-- 3. Add composite index for filtering invoices by type, customer_code, and order_date.
-- ============================================================================

-- Step 1: Ensure consistent charset and collation across reference keys
ALTER TABLE `orders` 
    MODIFY COLUMN `reference_no` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `receipt_orders` 
    MODIFY COLUMN `order_refno` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Step 2: Covering index for payments aggregation on receipt_orders
-- Allows index-only scan when aggregating SUM(amount_applied) grouped by order_refno with receipt_id
ALTER TABLE `receipt_orders`
    ADD INDEX `idx_ro_refno_amount_receipt` (`order_refno`, `amount_applied`, `receipt_id`);

-- Step 3: Covering index for credit notes aggregation on orders
-- Allows index-only scan when aggregating SUM(net_amount) WHERE type IN ('CN', 'CN2') GROUP BY credit_invoice_no
ALTER TABLE `orders`
    ADD INDEX `idx_orders_cn_aggregation` (`type`, `credit_invoice_no`, `net_amount`);

-- Step 4: Composite index for invoice filtering and ordering
-- Speeds up WHERE type = 'INV' AND customer_code = ... ORDER BY order_date DESC
ALTER TABLE `orders`
    ADD INDEX `idx_orders_inv_cust_code_date` (`type`, `customer_code`, `order_date`);

-- Step 5: Index for active receipts check in payment join (whereNull('deleted_at'))
ALTER TABLE `receipts`
    ADD INDEX `idx_receipts_id_deleted_at` (`id`, `deleted_at`);
