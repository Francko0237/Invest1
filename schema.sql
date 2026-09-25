-- BijouxInvest — Schema SQL (compatible InfinityFree — pas de CREATE DATABASE)
-- Importer dans la base : if0_41582591_invest_db via phpMyAdmin

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Table users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `telephone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `solde` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `code_parrain` VARCHAR(20) NOT NULL,
  `parrain_id` INT DEFAULT NULL,
  `role` VARCHAR(10) NOT NULL DEFAULT 'user',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_telephone` (`telephone`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_code_parrain` (`code_parrain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table plans
CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `prix` DECIMAL(15, 2) NOT NULL,
  `pourcentage` DECIMAL(5, 2) NOT NULL,
  `duree_valeur` INT NOT NULL,
  `duree_type` VARCHAR(10) NOT NULL,
  `statut` TINYINT(1) NOT NULL DEFAULT 1,
  `niveau` VARCHAR(50) DEFAULT 'BRONZE',
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table investments
CREATE TABLE IF NOT EXISTS `investments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `plan_id` INT NOT NULL,
  `montant` DECIMAL(15, 2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  CONSTRAINT `fk_inv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table withdrawals
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `montant` DECIMAL(15, 2) NOT NULL,
  `telephone_retrait` VARCHAR(20) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_wd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table referral_bonus
CREATE TABLE IF NOT EXISTS `referral_bonus` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `from_user_id` INT NOT NULL,
  `level` INT NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rb_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rb_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table deposits (Dépôts manuels)
CREATE TABLE IF NOT EXISTS `deposits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `montant` DECIMAL(15, 2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `sender_phone` VARCHAR(20) NOT NULL,
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `screenshot` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `admin_note` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_dep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(50) NOT NULL UNIQUE,
  `key_value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed default settings
INSERT INTO `settings` (`key_name`, `key_value`) VALUES
('ref_level_1_percent', '10.00'),
('ref_level_2_percent', '5.00'),
('ref_level_3_percent', '2.00'),
('site_theme', 'dark'),
('depot_minimum', '500.00'),
('retrait_minimum', '500.00'),
('momo_number', '670000000'),
('momo_name', 'BijouxInvest MTN'),
('om_number', '690000000'),
('om_name', 'BijouxInvest Orange')
ON DUPLICATE KEY UPDATE `key_value` = VALUES(`key_value`);

-- Seed default plans
INSERT INTO `plans` (`id`, `nom`, `prix`, `pourcentage`, `duree_valeur`, `duree_type`, `statut`, `niveau`, `description`, `image`) VALUES
(1, 'Diamant Bronze', 100.00, 15.00, 7, 'jours', 1, 'BRONZE', 'Plan débutant — 15% de gain en 7 jours', 'assets/images/bijou_bronze.png'),
(2, 'Diamant Argent', 500.00, 25.00, 14, 'jours', 1, 'ARGENT', 'Plan intermédiaire — 25% de gain en 14 jours', 'assets/images/bijou_argent.png'),
(3, 'Diamant Or', 1000.00, 40.00, 1, 'mois', 1, 'OR', 'Plan avancé — 40% de gain en 1 mois', 'assets/images/bijou_or.png'),
(4, 'Diamant Platine', 5000.00, 80.00, 2, 'mois', 1, 'PLATINE', 'Plan premium — 80% de gain en 2 mois', 'assets/images/bijou_platine.png'),
(5, 'Diamant Royal', 10000.00, 150.00, 3, 'mois', 1, 'DIAMANT', 'Plan élite — 150% de gain en 3 mois', 'assets/images/bijou_royal.png')
ON DUPLICATE KEY UPDATE `nom` = VALUES(`nom`), `prix` = VALUES(`prix`), `pourcentage` = VALUES(`pourcentage`),
`duree_valeur` = VALUES(`duree_valeur`), `duree_type` = VALUES(`duree_type`), `statut` = VALUES(`statut`),
`niveau` = VALUES(`niveau`), `description` = VALUES(`description`), `image` = VALUES(`image`);

-- Admin user (hash bcrypt de "admin123" — généré en PHP, pas bash)
-- Téléphone: +237666666666 | Email: admin@invest.com | Pass: admin123
INSERT INTO `users` (`id`, `nom`, `telephone`, `email`, `password`, `solde`, `code_parrain`, `role`) VALUES
(1, 'Administrateur', '+237666666666', 'admin@invest.com', '$2y$10$ikEjbPN2Me9Ux1k6Z5/A7Ox3A4m0re7DJAafIbQgU6azNfUPjFA7u', 0.00, 'ADMIN001', 'admin')
ON DUPLICATE KEY UPDATE `telephone` = VALUES(`telephone`), `password` = VALUES(`password`), `role` = VALUES(`role`);
