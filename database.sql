-- Gaming Platform
-- MySQL 5.7+ / MariaDB 10.3+

CREATE DATABASE IF NOT EXISTS `app_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `app_db`;

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `auth_token` VARCHAR(64) DEFAULT NULL,
    `fund_pin` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `bank` VARCHAR(30) DEFAULT NULL,
    `acc_name` VARCHAR(100) DEFAULT NULL,
    `acc_number` VARCHAR(50) DEFAULT NULL,
    `balance` BIGINT UNSIGNED DEFAULT 0,
    `role` VARCHAR(10) DEFAULT 'user',
    `status` VARCHAR(10) DEFAULT 'active',
    `ref_code` VARCHAR(20) NOT NULL UNIQUE,
    `referred_by` VARCHAR(20) DEFAULT NULL,
    `avatar` VARCHAR(10) DEFAULT 'm1',
    `display_id` VARCHAR(20) DEFAULT NULL,
    `display_name` VARCHAR(50) DEFAULT NULL,
    `vip_level` INT DEFAULT 0,
    `total_deposit` BIGINT UNSIGNED DEFAULT 0,
    `total_turnover` BIGINT UNSIGNED DEFAULT 0,
    `rebate_claimed_to` BIGINT UNSIGNED DEFAULT 0,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ref` (`ref_code`),
    INDEX `idx_referred` (`referred_by`)
) ENGINE=InnoDB;

CREATE TABLE `deposits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `tx_id` VARCHAR(50) UNIQUE,
    `method` VARCHAR(30) DEFAULT 'qris',
    `type` VARCHAR(30) DEFAULT 'qris',
    `nominal` BIGINT UNSIGNED NOT NULL,
    `pay_amount` BIGINT UNSIGNED DEFAULT 0,
    `bonus_id` INT UNSIGNED DEFAULT NULL,
    `bonus_amount` BIGINT UNSIGNED DEFAULT 0,
    `pay_url` TEXT DEFAULT NULL,
    `pay_data` TEXT DEFAULT NULL,
    `turnover_at_deposit` BIGINT DEFAULT 0,
    `turnover_met` TINYINT DEFAULT 0,
    `status` VARCHAR(20) DEFAULT 'pending',
    `paid_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `withdrawals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `bank_name` VARCHAR(50) NOT NULL,
    `acc_name` VARCHAR(100) NOT NULL,
    `acc_number` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'pending',
    
    `admin_note` TEXT DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(30) NOT NULL,
    `amount` BIGINT NOT NULL,
    `balance_before` BIGINT UNSIGNED DEFAULT 0,
    `balance_after` BIGINT UNSIGNED DEFAULT 0,
    `ref_id` VARCHAR(100) DEFAULT NULL,
    
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `bonuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `percentage` DECIMAL(5,2) DEFAULT 0,
    `max_amount` BIGINT UNSIGNED DEFAULT 0,
    `turnover_x` INT DEFAULT 1,
    `min_deposit` BIGINT UNSIGNED DEFAULT 0,
    `status` VARCHAR(10) DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `memos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(20) DEFAULT 'all',
    `to_user_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(200) NOT NULL,
    `body` TEXT NOT NULL,
    `is_read` TINYINT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `memo_reads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `memo_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `read_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_read` (`memo_id`,`user_id`),
    FOREIGN KEY (`memo_id`) REFERENCES `memos`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `banners` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `subtitle` VARCHAR(255) DEFAULT NULL,
    `tag` VARCHAR(50) DEFAULT NULL,
    `bg_color` VARCHAR(100) DEFAULT 'linear-gradient(135deg,#0a3d2e,#0d5a3a)',
    `image_url` VARCHAR(500) DEFAULT NULL,
    `link` VARCHAR(500) DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `status` VARCHAR(10) DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `promos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `terms` TEXT,
    `bg_color` VARCHAR(100) DEFAULT 'linear-gradient(135deg,#0a3d2e,#0d5a3a)',
    `image_url` VARCHAR(500) DEFAULT NULL,
    `status` VARCHAR(10) DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `vouchers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `nominal` BIGINT UNSIGNED NOT NULL,
    `used_by` INT UNSIGNED DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'available',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `providers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) DEFAULT NULL,
    `logo` TEXT DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `status` TINYINT DEFAULT 1,
    `game_count` INT DEFAULT 0,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `games` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider_code` VARCHAR(50) NOT NULL,
    `game_code` VARCHAR(100) NOT NULL,
    `game_name` VARCHAR(200) DEFAULT NULL,
    `game_type` VARCHAR(50) DEFAULT 'slot',
    `banner` TEXT DEFAULT NULL,
    `status` TINYINT DEFAULT 1,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_game` (`provider_code`, `game_code`)
) ENGINE=InnoDB;

CREATE TABLE `vip_claims` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `claim_type` VARCHAR(20) NOT NULL,
    `period` VARCHAR(20) NOT NULL,
    `vip_level` INT NOT NULL DEFAULT 0,
    `amount` BIGINT UNSIGNED DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_claim` (`user_id`, `claim_type`, `period`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `redeem_codes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `amount` BIGINT UNSIGNED DEFAULT 0,
    `max_uses` INT DEFAULT 0,
    `used_count` INT DEFAULT 0,
    `status` ENUM('active','inactive','expired') DEFAULT 'active',
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `redeem_usage` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`code_id`) REFERENCES `redeem_codes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `rebates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `week_start` DATE NOT NULL,
    `week_end` DATE NOT NULL,
    `total_bet` BIGINT DEFAULT 0,
    `rebate_pct` DECIMAL(5,2) DEFAULT 0,
    `rebate_amount` BIGINT DEFAULT 0,
    `status` VARCHAR(20) DEFAULT 'pending',
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_rebate` (`user_id`, `week_start`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `settings` (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB;

-- Default data
INSERT INTO `settings` (`key`,`value`) VALUES
('site_name',''),('logo_url',''),('favicon_url',''),
('color_primary','#d4a843'),('color_secondary','#0a2b1f'),
('meta_title','Situs Slot Online Terpercaya'),
('meta_desc','Platform gaming online terlengkap dengan 5000+ games'),
('meta_keywords','slot online, casino, pragmatic, pgsoft'),
('min_deposit','10000'),('min_withdraw','50000'),
('marquee_text','Selamat datang • Maintenance Sabtu 08:00-09:00 WIB • Hubungi LiveChat 24 jam'),
('rebate_pct','0.03'),('live_chat_url',''),('referral_pct','10');

INSERT INTO `bonuses` (`name`,`percentage`,`max_amount`,`turnover_x`) VALUES
('Bonus New Member 100%',100,1000000,8),
('Bonus Deposit Harian 10%',10,500000,5),
('Cashback Mingguan 15%',15,5000000,0),
('Bonus Rollingan 0.8%',0.8,0,0);

INSERT INTO `banners` (`title`,`subtitle`,`tag`,`bg_color`,`sort_order`) VALUES
('BONUS NEW MEMBER 100%','Bonus deposit pertama hingga Rp 1.000.000','PROMO','linear-gradient(135deg,#0a3d2e,#0f5a3a,#14a085)',1),
('CASHBACK MINGGUAN 15%','Tanpa syarat turnover','CASHBACK','linear-gradient(135deg,#0d3728,#133d2d,#1abc9c)',2),
('TURNAMEN SLOT Rp 50 JUTA','Hadiah total puluhan juta','TURNAMEN','linear-gradient(135deg,#071e17,#0a2b1f,#d4a843)',3);

-- Default admin account (password: admin)
INSERT INTO `users` (`username`,`password`,`email`,`phone`,`bank`,`acc_name`,`acc_num`,`balance`,`role`,`ref_code`) VALUES
('admin','$2y$10$8KzQx5w5Y5H5W5Z5X5Z5XuY5H5W5Z5X5Z5XuY5H5W5Z5X5Z5Xu','admin@local','080000000000','BCA','Admin','9999999999',99999000,'admin','ADMIN001');
-- Default test account (password: test)
INSERT INTO `users` (`username`,`password`,`email`,`phone`,`bank`,`acc_name`,`acc_num`,`balance`,`role`,`ref_code`) VALUES
('test','$2y$10$test_hash_placeholder_replace_on_setup','test@local','081234567890','BCA','Test Player','1234567890',1250000,'user','TEST001');

CREATE TABLE IF NOT EXISTS `ref_claims` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `tier` INT NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ref_claim` (`user_id`, `tier`)
) ENGINE=InnoDB;

-- Lucky Spin
CREATE TABLE IF NOT EXISTS `spin_prizes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `label` VARCHAR(50) NOT NULL,
  `amount` BIGINT UNSIGNED DEFAULT 0,
  `probability` DECIMAL(6,3) DEFAULT 0,
  `color` VARCHAR(20) DEFAULT '#1abc9c',
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `spin_tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `used` TINYINT DEFAULT 0,
  `deposit_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `spin_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `prize_id` INT UNSIGNED DEFAULT NULL,
  `label` VARCHAR(50),
  `amount` BIGINT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `spin_prizes` (`id`,`label`,`amount`,`probability`,`color`,`sort_order`) VALUES
(1,'Rp 5.000.000',5000000,0.010,'#f59e0b',1),
(2,'Rp 2.000.000',2000000,0.020,'#ef4444',2),
(3,'Rp 1.000.000',1000000,0.050,'#8b5cf6',3),
(4,'Rp 500.000',500000,0.100,'#3b82f6',4),
(5,'Rp 250.000',250000,0.200,'#06b6d4',5),
(6,'Rp 100.000',100000,0.500,'#10b981',6),
(7,'Rp 10.000',10000,5.000,'#1abc9c',7),
(8,'Rp 5.000',5000,15.120,'#0d9488',8),
(9,'Zonk',0,15.800,'#6b7280',9),
(10,'Zonk',0,15.800,'#4b5563',10),
(11,'Zonk',0,15.800,'#374151',11),
(12,'Zonk',0,15.800,'#6b7280',12),
(13,'Zonk',0,15.800,'#4b5563',13);
