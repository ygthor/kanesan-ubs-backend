-- Debt query performance indexes (plain raw SQL)
-- Note: run once. If index already exists, MySQL will return duplicate key name error.

ALTER TABLE `orders`
    ADD INDEX `idx_orders_type_reference_no` (`type`, `reference_no`),
    ADD INDEX `idx_orders_type_credit_invoice_no` (`type`, `credit_invoice_no`),
    ADD INDEX `idx_orders_type_customer_order_date` (`type`, `customer_id`, `order_date`);

ALTER TABLE `order_items`
    ADD INDEX `idx_order_items_reference_no_amount` (`reference_no`, `amount`);

ALTER TABLE `receipt_orders`
    ADD INDEX `idx_receipt_orders_order_refno_receipt_id` (`order_refno`, `receipt_id`);

ALTER TABLE `customers`
    ADD INDEX `idx_customers_agent_no` (`agent_no`);
