-- Table to store additional details for artrans_items
-- This table is separate from artrans_items because artrans_items is used for UBS sync
-- and cannot be modified

CREATE TABLE IF NOT EXISTS `artrans_items_detail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `artrans_item_id` INT(11) NOT NULL COMMENT 'Foreign key to artrans_items.id',
  `is_trade_return` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this item is a trade return',
  `return_status` ENUM('good', 'bad') NULL DEFAULT NULL COMMENT 'Status of the return: good or bad (required if is_trade_return = 1)',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT(11) NULL DEFAULT NULL,
  `updated_by` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_artrans_item_id` (`artrans_item_id`),
  KEY `idx_artrans_item_id` (`artrans_item_id`),
  KEY `idx_is_trade_return` (`is_trade_return`),
  CONSTRAINT `fk_artrans_items_detail_item` 
    FOREIGN KEY (`artrans_item_id`) 
    REFERENCES `artrans_items` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional details for artrans_items (trade return information)';

