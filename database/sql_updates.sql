-- SQL Updates for Order Items and Customers Tables
-- Run this directly in phpMyAdmin
-- Date: 2025-10-21

-- ==================================================
-- ORDER ITEMS TABLE UPDATES
-- ==================================================

-- Add item_group column to order_items table
ALTER TABLE `order_items` 
ADD COLUMN `item_group` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Item group type: Cash Sales, Invoice, etc.' 
AFTER `trade_return_is_good`;

-- Verify the change
-- SELECT * FROM order_items LIMIT 1;

-- ==================================================
-- CUSTOMERS TABLE UPDATES
-- ==================================================

-- Add company_name2 column to customers table
ALTER TABLE `customers` 
ADD COLUMN `company_name2` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Company name line 2' 
AFTER `company_name`;

-- Verify the change
-- SELECT company_name, company_name2 FROM customers LIMIT 1;

