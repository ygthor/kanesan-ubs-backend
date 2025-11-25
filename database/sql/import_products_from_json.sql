-- Import script for products.json data
-- This script should be run after creating product_groups and products tables
-- 
-- ⚠️ RECOMMENDED APPROACH: Use the Laravel Seeder instead of this SQL file
-- 
-- The ProductSeeder.php already exists at: backend/database/seeders/ProductSeeder.php
-- 
-- To import data, simply run:
--   php artisan db:seed --class=ProductSeeder
-- 
-- The seeder will:
--   1. Read db/products.json
--   2. Extract unique product groups and insert into product_groups table
--   3. Insert all products with proper product_group_id references
--   4. Handle duplicates and validation automatically
--
-- ============================================================================
-- 
-- ALTERNATIVE: Manual SQL Import (if you prefer SQL over Laravel seeder)
-- 
-- Step 1: Insert product groups (extract unique GROUP values from products.json)
-- Example groups from the JSON:
INSERT INTO `product_groups` (`name`, `description`, `CREATED_ON`, `updated_at`) VALUES
('JAYASAKI- MATA', 'JAYASAKI MATA product group', NOW(), NOW()),
('BHAVANI', 'BHAVANI product group', NOW(), NOW()),
('AKS', 'AKS product group', NOW(), NOW()),
('TAUCU OPAH NUR', 'TAUCU OPAH NUR product group', NOW(), NOW()),
('KBS GENERAL', 'KBS GENERAL product group', NOW(), NOW()),
('KBS FOOD', 'KBS FOOD product group', NOW(), NOW()),
('KBS STORE', 'KBS STORE product group', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Step 2: Insert products (map CODE and DESCRIPTION to products table)
-- Example products - you'll need to replace product_group_id with actual IDs from step 1
-- This is just a template showing the structure

-- For JAYASAKI- MATA group (assuming product_group_id = 1):
INSERT INTO `products` (`code`, `description`, `product_group_id`, `is_active`, `CREATED_ON`, `updated_at`) VALUES
('A100', '100GM S/KARI AYAM MATA', (SELECT id FROM product_groups WHERE name = 'JAYASAKI- MATA' LIMIT 1), 1, NOW(), NOW()),
('A1K', '1KG S/KARI AYAM MATA', (SELECT id FROM product_groups WHERE name = 'JAYASAKI- MATA' LIMIT 1), 1, NOW(), NOW()),
('A1KRP', '1KG SERBUK AYAM RP', (SELECT id FROM product_groups WHERE name = 'JAYASAKI- MATA' LIMIT 1), 1, NOW(), NOW())
-- ... continue for all products (you'll need to generate this for all 200+ products)
ON DUPLICATE KEY UPDATE 
    `description` = VALUES(`description`),
    `product_group_id` = VALUES(`product_group_id`);

-- NOTE: This SQL approach requires manually generating INSERT statements for all products.
-- The Laravel seeder (ProductSeeder.php) is much easier and handles everything automatically.

