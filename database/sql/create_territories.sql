-- Create territories table
CREATE TABLE IF NOT EXISTS `territories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `area` VARCHAR(255) NOT NULL COMMENT 'Area code/abbreviation',
  `description` VARCHAR(255) NOT NULL COMMENT 'Full description of the territory',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `territories_area_unique` (`area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert territory data
INSERT INTO `territories` (`area`, `description`, `created_at`, `updated_at`) VALUES
('AYER TAWAR', 'AYER TAWAR', NOW(), NOW()),
('BAGAN SERAI', 'BAGAN SERAI', NOW(), NOW()),
('BRUAS', 'BRUAS', NOW(), NOW()),
('CAMERON HIGL', 'CAMERON HIGHLAND', NOW(), NOW()),
('GERIK', 'GERIK', NOW(), NOW()),
('IPOH', 'IPOH', NOW(), NOW()),
('K.KANGSAR', 'K.KANGSAR', NOW(), NOW()),
('KAMPAR', 'KAMPAR', NOW(), NOW()),
('KAMUNTING', 'KAMUNTING', NOW(), NOW()),
('LANGKAP', 'LANGKAP', NOW(), NOW()),
('LUMUT', 'LUMUT', NOW(), NOW()),
('MANJUNG', 'MANJUNG', NOW(), NOW()),
('P.REMIS', 'P.REMIS', NOW(), NOW()),
('PANGKOR', 'PANGKOR', NOW(), NOW()),
('PARIT BUNTAR', 'PARIT BUNTAR', NOW(), NOW()),
('S.SIPUT', 'S.SIPUT', NOW(), NOW()),
('SABAK BERNAM', 'SABAK BERNAM', NOW(), NOW()),
('SELAMA', 'SELAMA', NOW(), NOW()),
('SEMANGGOL', 'SEMANGGOL', NOW(), NOW()),
('SG PETANI', 'SG PETANI', NOW(), NOW()),
('SIMPANG', 'SIMPANG', NOW(), NOW()),
('SITIAWAN', 'SITIAWAN', NOW(), NOW()),
('SRI ISKANDAR', 'SRI ISKANDAR', NOW(), NOW()),
('TAIPING', 'TAIPING', NOW(), NOW()),
('TAPAH', 'TAPAH', NOW(), NOW()),
('TELUK INTAN', 'TELUK INTAN', NOW(), NOW()),
('TG. MALIM', 'TG. MALIM', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
  `description` = VALUES(`description`),
  `updated_at` = NOW();

