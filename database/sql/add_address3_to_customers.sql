-- Add address3 column to customers table
-- Run this SQL in phpMyAdmin or your MySQL client

ALTER TABLE `customers` 
ADD COLUMN `address3` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Street address line 3' 
AFTER `address2`;

