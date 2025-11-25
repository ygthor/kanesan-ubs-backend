-- Create product_group table to store product groups
-- This replaces the GROUP field from the old icitem table

CREATE TABLE IF NOT EXISTS `product_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Product group name (e.g., JAYASAKI- MATA, BHAVANI, AKS)',
  `description` TEXT NULL DEFAULT NULL COMMENT 'Optional description for the product group',
  `CREATED_BY` VARCHAR(255) NULL DEFAULT NULL,
  `UPDATED_BY` VARCHAR(255) NULL DEFAULT NULL,
  `CREATED_ON` TIMESTAMP NULL DEFAULT NULL,
  `UPDATED_ON` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_groups_name` (`name`),
  INDEX `idx_product_groups_created_on` (`CREATED_ON`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product groups to categorize products';

-- Create product table to store product codes and descriptions
-- This replaces the old icitem and product tables

CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL COMMENT 'Product code (e.g., A100, A1K, PBBT)',
  `description` TEXT NULL DEFAULT NULL COMMENT 'Product description',
  `group_name` VARCHAR(255) NOT NULL COMMENT 'Product group name (e.g., JAYASAKI- MATA, BHAVANI, AKS) - stored directly for UBS compatibility',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether the product is active',
  `CREATED_BY` VARCHAR(255) NULL DEFAULT NULL,
  `UPDATED_BY` VARCHAR(255) NULL DEFAULT NULL,
  `CREATED_ON` TIMESTAMP NULL DEFAULT NULL,
  `UPDATED_ON` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_code` (`code`),
  INDEX `idx_products_group_name` (`group_name`),
  INDEX `idx_products_code` (`code`),
  INDEX `idx_products_active` (`is_active`),
  INDEX `idx_products_created_on` (`CREATED_ON`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Products with code and description, using group_name for UBS compatibility';

