-- RoseSMM Panel Database Schema (MySQL 5.7+ / 8.0+ / MariaDB)

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `currencies`;
CREATE TABLE `currencies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(10) NOT NULL UNIQUE,
  `name` VARCHAR(50) NOT NULL,
  `symbol` VARCHAR(10) NOT NULL,
  `rate` DECIMAL(12, 6) NOT NULL DEFAULT 1.000000,
  `is_default` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) DEFAULT '',
  `role` ENUM('user', 'admin') DEFAULT 'user',
  `balance` DECIMAL(12, 4) DEFAULT 0.0000,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `api_key` VARCHAR(64) DEFAULT '',
  `avatar` VARCHAR(255) DEFAULT '',
  `is_verified` TINYINT(1) DEFAULT 1,
  `status` ENUM('active', 'suspended', 'banned') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(100) DEFAULT 'instagram',
  `sort_order` INT DEFAULT 0,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `providers`;
CREATE TABLE `providers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `api_url` VARCHAR(255) NOT NULL,
  `api_key` VARCHAR(255) NOT NULL,
  `balance` DECIMAL(12, 4) DEFAULT 0.0000,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `provider_id` INT DEFAULT NULL,
  `provider_service_id` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `type` ENUM('default', 'custom_comments', 'subscriptions', 'package') DEFAULT 'default',
  `rate` DECIMAL(10, 4) NOT NULL,
  `original_rate` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
  `margin_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  `margin_value` DECIMAL(10, 4) NOT NULL DEFAULT 30.0000,
  `min_quantity` INT NOT NULL DEFAULT 100,
  `max_quantity` INT NOT NULL DEFAULT 100000,
  `dripfeed` TINYINT(1) DEFAULT 0,
  `badge` VARCHAR(50) DEFAULT '',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE `payment_gateways` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
  `mode` ENUM('test', 'live') NOT NULL DEFAULT 'test',
  `api_key` VARCHAR(255) NULL,
  `secret_key` VARCHAR(255) NULL,
  `webhook_secret` VARCHAR(255) NULL,
  `merchant_id` VARCHAR(255) NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `min_amount` DECIMAL(10, 2) NOT NULL DEFAULT 5.00,
  `max_amount` DECIMAL(10, 2) NOT NULL DEFAULT 5000.00,
  `fee_percent` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `parameters` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `link` VARCHAR(500) NOT NULL,
  `quantity` INT NOT NULL,
  `charge` DECIMAL(12, 4) NOT NULL,
  `start_count` INT DEFAULT 0,
  `remains` INT DEFAULT 0,
  `status` ENUM('pending', 'processing', 'in_progress', 'completed', 'partial', 'canceled') DEFAULT 'pending',
  `provider_order_id` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('deposit', 'order', 'refund', 'bonus') NOT NULL,
  `amount` DECIMAL(12, 4) NOT NULL,
  `charge` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `payment_method` VARCHAR(100) DEFAULT 'manual',
  `gateway_code` VARCHAR(50) DEFAULT NULL,
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `gateway_response` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sliders`;
CREATE TABLE `sliders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `tagline` VARCHAR(255) DEFAULT '',
  `subtitle` TEXT,
  `image_url` VARCHAR(500) DEFAULT '',
  `button_text` VARCHAR(100) DEFAULT 'Explore Services',
  `button_url` VARCHAR(255) DEFAULT '/services',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `status` ENUM('open', 'answered', 'closed') DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ticket_messages`;
CREATE TABLE `ticket_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `is_admin` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `type` VARCHAR(50) DEFAULT 'system',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tournaments`;
CREATE TABLE `tournaments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `game` VARCHAR(100) NOT NULL,
  `prize_pool` DECIMAL(10, 2) DEFAULT 0.00,
  `entry_fee` DECIMAL(10, 2) DEFAULT 0.00,
  `max_teams` INT DEFAULT 16,
  `registered_teams` INT DEFAULT 0,
  `status` ENUM('upcoming', 'live', 'completed') DEFAULT 'upcoming',
  `start_date` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `matches`;
CREATE TABLE `matches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tournament_id` INT NOT NULL,
  `team1_name` VARCHAR(150) NOT NULL,
  `team2_name` VARCHAR(150) NOT NULL,
  `score1` INT DEFAULT 0,
  `score2` INT DEFAULT 0,
  `status` ENUM('scheduled', 'live', 'completed') DEFAULT 'scheduled',
  `match_time` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `teams`;
CREATE TABLE `teams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `leader_id` INT NOT NULL,
  `members_count` INT DEFAULT 1,
  `wins` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'RoseSMM'),
('site_title', 'RoseSMM - Social Media Services'),
('site_description', 'Get real engagement and boost your online presence with our premium SMM services.'),
('currency_default', 'INR'),
('currency_symbol', '₹'),
('referral_bonus_percent', '5'),
('deposit_bonus_percent', '10'),
('contact_email', 'support@rosesmm.com'),
('ticket_system', '1'),
('maintenance_mode', '0'),
('installed', '1');

-- Seed Currencies (INR default as required by specification, plus USD, EUR, GBP)
INSERT INTO `currencies` (`code`, `name`, `symbol`, `rate`, `is_default`, `status`) VALUES
('INR', 'Indian Rupee', '₹', 1.000000, 1, 'active'),
('USD', 'US Dollar', '$', 0.011765, 0, 'active'),
('EUR', 'Euro', '€', 0.010870, 0, 'active'),
('GBP', 'British Pound', '£', 0.009345, 0, 'active');

-- Seed Admin User (Password: admin123)
-- Hash generated via password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `balance`, `currency`, `api_key`, `is_verified`, `status`) VALUES
('admin', 'admin@rosesmm.com', '$2y$10$9ckQsWdoRTJQI2UUvk6bRupIlmMCLVGuOMnKjqcVp38gW2JonU8D6', 'Administrator', 'admin', 50000.0000, 'USD', 'rose_adm_89f7a93e502b4d99c7b12', 1, 'active');

-- Seed Default User from Screenshot: John Doe (@johndoe, balance $24.58)
-- Password: password123
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `balance`, `currency`, `api_key`, `is_verified`, `status`) VALUES
('johndoe', 'johndoe@example.com', '$2y$10$JeXi/.1WpxILt5Y8KS9NPebZRcNQxpuNGWoPhnD4Ub3b6aDmy/IHq', 'John Doe', 'user', 24.5800, 'USD', 'rose_usr_31a4c889f02e64b77d29e', 1, 'active');

-- Seed Categories
INSERT INTO `categories` (`name`, `slug`, `icon`, `sort_order`, `status`) VALUES
('Instagram', 'instagram', 'instagram', 1, 'active'),
('YouTube', 'youtube', 'youtube', 2, 'active'),
('TikTok', 'tiktok', 'tiktok', 3, 'active'),
('Facebook', 'facebook', 'facebook', 4, 'active'),
('Twitter / X', 'twitter', 'twitter', 5, 'active'),
('Telegram', 'telegram', 'telegram', 6, 'active'),
('LinkedIn', 'linkedin', 'linkedin', 7, 'active');

-- Seed Provider
INSERT INTO `providers` (`name`, `api_url`, `api_key`, `balance`, `currency`, `status`) VALUES
('Primary SMM Hub', 'https://api.smmprovider.com/v2', 'smm_live_key_90284718364718', 1845.5000, 'USD', 'active');

-- Seed Popular Services matching screenshot exactly
INSERT INTO `services` (`category_id`, `provider_id`, `provider_service_id`, `name`, `description`, `type`, `rate`, `min_quantity`, `max_quantity`, `badge`, `status`, `sort_order`) VALUES
(1, 1, '101', 'Instagram Followers - Real & Active', 'High quality, non-drop real active followers. Instant delivery start within 0-15 minutes.', 'default', 2.5000, 100, 100000, 'Best Seller', 'active', 1),
(2, 1, '102', 'YouTube Views - High Retention (Non-Drop)', 'Worldwide real views with 80%+ watch duration. Safe for monetization.', 'default', 1.2000, 500, 1000000, '', 'active', 2),
(3, 1, '103', 'TikTok Likes - Instant Delivery', 'Fast likes from real profiles. Instant start, high engagement boost.', 'default', 0.8000, 100, 500000, '', 'active', 3),
(5, 1, '104', 'Twitter Followers - Organic Profiles', 'Fast delivery Twitter/X followers. Clean profiles with avatars and bios.', 'default', 1.0000, 100, 50000, '', 'active', 4),
(6, 1, '105', 'Telegram Members - Channel & Group', 'Stable channel members, 0% drop rate, permanent guarantee.', 'default', 2.0000, 100, 100000, '', 'active', 5),
(7, 1, '106', 'LinkedIn Followers - Corporate & Professional', 'Targeted professional network followers for your company page or profile.', 'default', 1.8000, 100, 50000, '', 'active', 6),
(4, 1, '107', 'Facebook Page Likes & Followers', 'Page followers and likes with genuine profiles. Super stable delivery.', 'default', 3.5000, 100, 50000, '', 'active', 7),
(1, 1, '108', 'Instagram Likes - Fast High Quality', 'Instant likes on latest or specified posts. Real profile accounts.', 'default', 0.9000, 50, 100000, '', 'active', 8);

-- Seed Orders matching screenshot recently ordered
INSERT INTO `orders` (`user_id`, `service_id`, `link`, `quantity`, `charge`, `start_count`, `remains`, `status`, `provider_order_id`, `created_at`) VALUES
(2, 1, 'https://instagram.com/user123', 500, 1.2500, 1420, 0, 'completed', 'PRV-8821', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 2, 'https://youtube.com/watch?v=channelname', 1000, 1.2000, 8900, 0, 'completed', 'PRV-8820', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(2, 3, 'https://tiktok.com/@username/video/1', 2500, 2.0000, 310, 800, 'pending', 'PRV-8819', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(2, 7, 'https://facebook.com/page123', 1000, 3.5000, 5600, 0, 'completed', 'PRV-8818', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(2, 4, 'https://twitter.com/user456/status/1', 500, 0.7500, 840, 0, 'completed', 'PRV-8817', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(2, 1, 'https://instagram.com/brand_store', 2000, 5.0000, 11200, 0, 'completed', 'PRV-8816', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 8, 'https://instagram.com/p/Cxyz123', 1500, 1.3500, 450, 0, 'completed', 'PRV-8815', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 6, 'https://linkedin.com/in/exec_profile', 500, 0.9000, 2100, 0, 'completed', 'PRV-8814', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 3, 'https://tiktok.com/@growth_hub/video/99', 5000, 4.0000, 1200, 5000, 'pending', 'PRV-8813', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 5, 'https://t.me/cryptosignals_official', 1000, 2.0000, 3400, 1000, 'pending', 'PRV-8812', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 2, 'https://youtube.com/watch?v=podcast_ep12', 3000, 3.6000, 15000, 0, 'completed', 'PRV-8811', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 1, 'https://instagram.com/summer_vibe', 1000, 2.5000, 800, 1000, 'pending', 'PRV-8810', DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Seed Transactions
INSERT INTO `transactions` (`user_id`, `type`, `amount`, `charge`, `currency`, `payment_method`, `transaction_id`, `status`, `created_at`) VALUES
(2, 'deposit', 100.0000, 0.0000, 'USD', 'Stripe / Credit Card', 'TXN-STRIPE-891024', 'completed', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 'bonus', 10.0000, 0.0000, 'USD', 'Deposit Bonus (10%)', 'BONUS-DEP-10', 'completed', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 'deposit', 150.0000, 0.0000, 'USD', 'PayPal', 'TXN-PAYPAL-441098', 'completed', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 'bonus', 15.0000, 0.0000, 'USD', 'Deposit Bonus (10%)', 'BONUS-DEP-15', 'completed', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 'order', 1.2500, 0.0000, 'USD', 'Wallet', 'ORD-CHG-101', 'completed', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'order', 1.2000, 0.0000, 'USD', 'Wallet', 'ORD-CHG-102', 'completed', DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- Seed Slider matching screenshot hero banner
INSERT INTO `sliders` (`title`, `tagline`, `subtitle`, `image_url`, `button_text`, `button_url`, `status`, `sort_order`) VALUES
('Grow Your Social Media', 'Fast • Secure • Reliable', 'Get real engagement and boost your online presence with our premium SMM services.', '/assets/images/banner-hero.png', 'Explore Services →', '/services', 'active', 1),
('Instant Boost For All Platforms', 'Instant Delivery • 24/7 Support', 'Boost your followers, likes, views and comments with automated high speed delivery.', '/assets/images/banner-hero.png', 'Order Now →', '/order', 'active', 2);

-- Seed Notifications (3 unread matching bell badge '3' in screenshot!)
INSERT INTO `notifications` (`user_id`, `title`, `message`, `is_read`, `type`, `created_at`) VALUES
(2, 'Deposit Successful', 'Your deposit of $150.00 via PayPal was confirmed. 10% bonus ($15.00) has been added to your wallet!', 0, 'wallet', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'Order Completed', 'Order #1 (500 Instagram Followers) has been successfully delivered.', 0, 'order', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'Weekend 10% Deposit Bonus', 'Use code ROSE10 for an additional 10% bonus on all UPI and card deposits this weekend!', 0, 'promo', DATE_SUB(NOW(), INTERVAL 5 HOUR));

-- Seed Tickets
INSERT INTO `tickets` (`user_id`, `subject`, `priority`, `status`, `created_at`) VALUES
(2, 'Speed inquiry for YouTube Views', 'medium', 'open', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'Payment query regarding crypto confirmation', 'high', 'answered', DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `message`, `is_admin`, `created_at`) VALUES
(1, 2, 'Hi, could you let me know how fast the high retention YouTube views kick in after ordering?', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 'I deposited via USDT-TRC20, transaction hash is TX99014.', 0, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 1, 'Hello John! We have verified your transaction and credited $50.00 + $5.00 bonus to your account balance.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Seed Tournaments, Matches, Teams
INSERT INTO `tournaments` (`title`, `game`, `prize_pool`, `entry_fee`, `max_teams`, `registered_teams`, `status`, `start_date`) VALUES
('RoseSMM Creators Cup 2026', 'BGMI / PUBG Mobile', 5000.00, 10.00, 32, 24, 'upcoming', DATE_ADD(NOW(), INTERVAL 5 DAY)),
('Valorant Influencer Showdown', 'Valorant', 7500.00, 25.00, 16, 16, 'live', NOW());

INSERT INTO `teams` (`name`, `leader_id`, `members_count`, `wins`) VALUES
('Rose Warriors', 2, 5, 8),
('Alpha Strikers', 1, 5, 12);

INSERT INTO `matches` (`tournament_id`, `team1_name`, `team2_name`, `score1`, `score2`, `status`, `match_time`) VALUES
(2, 'Rose Warriors', 'Alpha Strikers', 2, 1, 'live', NOW()),
(1, 'Cyber Knights', 'Phantom Squad', 0, 0, 'scheduled', DATE_ADD(NOW(), INTERVAL 5 DAY));
