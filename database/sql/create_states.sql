-- Create states table
CREATE TABLE IF NOT EXISTS `states` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'State name',
  `code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'State code/abbreviation',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `states_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Malaysia states data
INSERT INTO `states` (`name`, `code`, `created_at`, `updated_at`) VALUES
('Johor', 'JHR', NOW(), NOW()),
('Kedah', 'KDH', NOW(), NOW()),
('Kelantan', 'KTN', NOW(), NOW()),
('Malacca', 'MLK', NOW(), NOW()),
('Negeri Sembilan', 'NSN', NOW(), NOW()),
('Pahang', 'PHG', NOW(), NOW()),
('Perak', 'PRK', NOW(), NOW()),
('Perlis', 'PLS', NOW(), NOW()),
('Penang', 'PNG', NOW(), NOW()),
('Sabah', 'SBH', NOW(), NOW()),
('Sarawak', 'SWK', NOW(), NOW()),
('Selangor', 'SGR', NOW(), NOW()),
('Terengganu', 'TRG', NOW(), NOW()),
('Kuala Lumpur', 'KUL', NOW(), NOW()),
('Labuan', 'LBN', NOW(), NOW()),
('Putrajaya', 'PJY', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
  `code` = VALUES(`code`),
  `updated_at` = NOW();

