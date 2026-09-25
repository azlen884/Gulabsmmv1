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
  `referrer_id` INT NULL DEFAULT NULL,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `referral_code` VARCHAR(32) NULL DEFAULT NULL UNIQUE,
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_referrer_id` (`referrer_id`)
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
  `refill_enabled` TINYINT(1) DEFAULT 0,
  `refill_days` INT DEFAULT 30,
  `refill_limit` INT DEFAULT 5,
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

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `gateway` VARCHAR(50) NOT NULL,
  `internal_payment_id` VARCHAR(100) NOT NULL UNIQUE,
  `gateway_order_id` VARCHAR(191) DEFAULT NULL,
  `gateway_payment_id` VARCHAR(191) DEFAULT NULL,
  `amount` DECIMAL(12, 4) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` ENUM('CREATED','PENDING','SUCCESS','FAILED','CANCELLED','EXPIRED','REJECTED') NOT NULL DEFAULT 'CREATED',
  `verification_status` VARCHAR(50) NOT NULL DEFAULT 'unverified',
  `gateway_response` LONGTEXT DEFAULT NULL,
  `failure_reason` TEXT DEFAULT NULL,
  `is_credited` TINYINT(1) NOT NULL DEFAULT 0,
  `wallet_transaction_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_gateway` (`gateway`),
  KEY `idx_gateway_order_id` (`gateway_order_id`),
  KEY `idx_gateway_payment_id` (`gateway_payment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `provider_id` INT DEFAULT NULL,
  `link` VARCHAR(500) NOT NULL,
  `quantity` INT NOT NULL,
  `charge` DECIMAL(12, 4) NOT NULL,
  `start_count` INT DEFAULT 0,
  `remains` INT DEFAULT 0,
  `status` ENUM('pending', 'processing', 'in_progress', 'completed', 'partial', 'canceled') DEFAULT 'pending',
  `provider_order_id` VARCHAR(100) DEFAULT NULL,
  `is_dripfeed` TINYINT(1) DEFAULT 0,
  `dripfeed_id` INT DEFAULT NULL,
  `refill_status` ENUM('none', 'eligible', 'pending', 'processing', 'completed', 'rejected', 'failed') DEFAULT 'none',
  `refill_count` INT DEFAULT 0,
  `last_refill_at` DATETIME DEFAULT NULL,
  `refund_status` ENUM('none', 'pending', 'refunded', 'partial_refunded', 'failed') DEFAULT 'none',
  `refunded_amount` DECIMAL(12, 4) DEFAULT 0.0000,
  `coupon_id` INT DEFAULT NULL,
  `discount_amount` DECIMAL(12, 4) DEFAULT 0.0000,
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

DROP TABLE IF EXISTS `referral_transactions`;
CREATE TABLE `referral_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `referrer_id` INT NOT NULL,
  `referred_id` INT NOT NULL,
  `source_type` ENUM('order', 'deposit') NOT NULL,
  `source_id` VARCHAR(100) NOT NULL,
  `base_amount` DECIMAL(12, 4) NOT NULL,
  `commission_percent` DECIMAL(5, 2) NOT NULL,
  `commission_amount` DECIMAL(12, 4) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` ENUM('completed', 'reversed', 'pending') NOT NULL DEFAULT 'completed',
  `wallet_transaction_id` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ref_referrer` (`referrer_id`),
  KEY `idx_ref_referred` (`referred_id`),
  KEY `idx_ref_created` (`created_at`),
  UNIQUE KEY `uk_ref_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `role` VARCHAR(100) DEFAULT 'Verified Client',
  `avatar` VARCHAR(255) DEFAULT '',
  `content` TEXT NOT NULL,
  `rating` INT DEFAULT 5,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `sort_order` INT DEFAULT 0,
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
('referral_system_enabled', '1'),
('referral_commission_percent', '5.00'),
('referral_commission_event', 'order'),
('referral_bonus_percent', '5'),
('deposit_bonus_percent', '10'),
('contact_email', 'support@rosesmm.com'),
('ticket_system', '1'),
('maintenance_mode', '0'),
('active_theme', 'default'),
('installed', '1');

-- Seed Currencies (INR default as required by specification, plus USD, EUR, GBP)
INSERT INTO `currencies` (`code`, `name`, `symbol`, `rate`, `is_default`, `status`) VALUES
('INR', 'Indian Rupee', '₹', 1.000000, 1, 'active'),
('USD', 'US Dollar', '$', 0.011765, 0, 'active'),
('EUR', 'Euro', '€', 0.010870, 0, 'active'),
('GBP', 'British Pound', '£', 0.009345, 0, 'active');

-- Seed Payment Gateways (Razorpay, Cashfree, PhonePe, PayU, Stripe, PayPal, Cryptomus, Bank Transfer)
INSERT INTO `payment_gateways` (`id`, `code`, `name`, `description`, `status`, `mode`, `api_key`, `secret_key`, `webhook_secret`, `merchant_id`, `currency`, `min_amount`, `max_amount`, `fee_percent`, `parameters`, `sort_order`) VALUES
(1, 'razorpay', 'Razorpay (Cards / UPI / NetBanking)', 'Fast and secure payment via UPI, Debit/Credit Card, NetBanking (INR)', 'inactive', 'test', '', '', '', '', 'INR', 100.00, 100000.00, 0.00, NULL, 1),
(2, 'cashfree', 'Cashfree Payments', 'Pay securely with UPI, Credit/Debit Cards, NetBanking, and Wallets via Cashfree', 'inactive', 'test', '', '', '', '', 'INR', 10.00, 100000.00, 0.00, '', 2),
(3, 'phonepe', 'PhonePe Payment Gateway', 'Fast and secure UPI, Card, and NetBanking payments via official PhonePe PG', 'inactive', 'test', '1', '', '', '', 'INR', 10.00, 100000.00, 0.00, '{\"salt_index\":\"1\"}', 3),
(4, 'payu', 'PayU', 'Reliable UPI, Cards, NetBanking, and PayLater checkout via PayU India', 'inactive', 'test', '', '', '', '', 'INR', 10.00, 100000.00, 0.00, '', 4),
(5, 'stripe', 'Stripe (Credit / Debit Card)', 'Pay securely with Visa, Mastercard, Amex, Apple Pay, Google Pay', 'active', 'test', 'pk_test_sample', 'sk_test_sample', '', '', 'USD', 5.00, 5000.00, 0.00, NULL, 5),
(6, 'paypal', 'PayPal Checkout', 'Pay with PayPal balance, connected credit card, or bank account', 'inactive', 'test', 'client_id_sample', 'client_secret_sample', '', '', 'USD', 5.00, 5000.00, 0.00, NULL, 6),
(7, 'cryptomus', 'Cryptomus (Crypto USDT/BTC)', 'Instant cryptocurrency deposit via USDT (TRC20/BEP20), BTC, ETH', 'inactive', 'live', '', '', '', '', 'USD', 10.00, 10000.00, 0.00, NULL, 7),
(8, 'bank_transfer', 'Bank Wire / Manual Transfer', 'Direct bank deposit with payment proof reference check', 'active', 'live', '', '', '', '', 'USD', 20.00, 10000.00, 0.00, NULL, 8);

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

-- =========================================================================
-- 8 SMM PANEL ADVANCED AUTOMATION & SALES FEATURES SCHEMA
-- =========================================================================

-- 1. Services Enhancements for Refill
ALTER TABLE `services` ADD COLUMN IF NOT EXISTS `refill_enabled` TINYINT(1) DEFAULT 0 AFTER `dripfeed`;
ALTER TABLE `services` ADD COLUMN IF NOT EXISTS `refill_days` INT DEFAULT 30 AFTER `refill_enabled`;
ALTER TABLE `services` ADD COLUMN IF NOT EXISTS `refill_limit` INT DEFAULT 5 AFTER `refill_days`;

-- 2. Orders Enhancements for Refill, Refund, Drip-Feed, Coupon
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `provider_id` INT DEFAULT NULL AFTER `service_id`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `is_dripfeed` TINYINT(1) DEFAULT 0 AFTER `provider_order_id`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `dripfeed_id` INT DEFAULT NULL AFTER `is_dripfeed`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `refill_status` ENUM('none', 'eligible', 'pending', 'processing', 'completed', 'rejected', 'failed') DEFAULT 'none' AFTER `dripfeed_id`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `refill_count` INT DEFAULT 0 AFTER `refill_status`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `last_refill_at` DATETIME DEFAULT NULL AFTER `refill_count`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `refund_status` ENUM('none', 'pending', 'refunded', 'partial_refunded', 'failed') DEFAULT 'none' AFTER `last_refill_at`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `refunded_amount` DECIMAL(12, 4) DEFAULT 0.0000 AFTER `refund_status`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `coupon_id` INT DEFAULT NULL AFTER `refunded_amount`;
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(12, 4) DEFAULT 0.0000 AFTER `coupon_id`;

-- 3. Ticket Automation Rules
CREATE TABLE IF NOT EXISTS `ticket_automation_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `trigger_event` ENUM('ticket_created', 'ticket_replied', 'status_changed') DEFAULT 'ticket_created',
  `condition_match_type` ENUM('all', 'any') DEFAULT 'all',
  `keyword_contains` VARCHAR(255) DEFAULT '',
  `priority_filter` VARCHAR(50) DEFAULT 'all',
  `category_filter` VARCHAR(50) DEFAULT 'all',
  `action_auto_reply` TINYINT(1) DEFAULT 0,
  `reply_message` TEXT DEFAULT NULL,
  `action_change_status` VARCHAR(50) DEFAULT NULL,
  `action_change_priority` VARCHAR(50) DEFAULT NULL,
  `rule_priority` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Ticket Automation Logs
CREATE TABLE IF NOT EXISTS `ticket_automation_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rule_id` INT DEFAULT NULL,
  `ticket_id` INT NOT NULL,
  `trigger_event` VARCHAR(50) NOT NULL,
  `action_taken` TEXT NOT NULL,
  `status` ENUM('success', 'failed', 'skipped') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_rule` (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Cron Jobs
CREATE TABLE IF NOT EXISTS `cron_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `task_key` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `interval_minutes` INT DEFAULT 5,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `last_run_at` DATETIME DEFAULT NULL,
  `next_run_at` DATETIME DEFAULT NULL,
  `last_status` ENUM('idle', 'running', 'success', 'failed') DEFAULT 'idle',
  `last_error` TEXT DEFAULT NULL,
  `execution_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Cron Execution Logs
CREATE TABLE IF NOT EXISTS `cron_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cron_job_id` INT DEFAULT NULL,
  `task_key` VARCHAR(50) NOT NULL,
  `status` ENUM('success', 'failed') NOT NULL,
  `output` TEXT DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `duration_ms` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_task_key` (`task_key`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Auto Refill Requests
CREATE TABLE IF NOT EXISTS `refill_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `provider_id` INT DEFAULT NULL,
  `provider_order_id` VARCHAR(100) DEFAULT NULL,
  `provider_refill_id` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'processing', 'completed', 'rejected', 'failed') DEFAULT 'pending',
  `refill_type` ENUM('auto', 'manual') DEFAULT 'auto',
  `attempts` INT DEFAULT 1,
  `provider_response` TEXT DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_order_id` (`order_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Auto Refund Records
CREATE TABLE IF NOT EXISTS `refund_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(12, 4) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `reason` VARCHAR(255) NOT NULL,
  `refund_type` ENUM('auto', 'manual') DEFAULT 'auto',
  `status` ENUM('completed', 'failed') DEFAULT 'completed',
  `wallet_transaction_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_order` (`order_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Drip-Feed Orders
CREATE TABLE IF NOT EXISTS `drip_feed_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `link` VARCHAR(500) NOT NULL,
  `total_quantity` INT NOT NULL,
  `runs` INT NOT NULL,
  `interval_minutes` INT NOT NULL,
  `quantity_per_run` INT NOT NULL,
  `current_run` INT DEFAULT 0,
  `status` ENUM('active', 'paused', 'completed', 'canceled') DEFAULT 'active',
  `total_charge` DECIMAL(12, 4) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Drip-Feed Batches
CREATE TABLE IF NOT EXISTS `drip_feed_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `drip_feed_id` INT NOT NULL,
  `run_number` INT NOT NULL,
  `quantity` INT NOT NULL,
  `order_id` INT DEFAULT NULL,
  `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  `scheduled_at` DATETIME NOT NULL,
  `executed_at` DATETIME DEFAULT NULL,
  `provider_order_id` VARCHAR(100) DEFAULT NULL,
  `response` TEXT DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_drip_feed` (`drip_feed_id`),
  KEY `idx_scheduled` (`scheduled_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Mass Order Batches
CREATE TABLE IF NOT EXISTS `mass_order_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `total_orders` INT NOT NULL,
  `successful_orders` INT DEFAULT 0,
  `failed_orders` INT DEFAULT 0,
  `total_charge` DECIMAL(12, 4) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `raw_input` TEXT NOT NULL,
  `results_summary` LONGTEXT DEFAULT NULL,
  `status` ENUM('completed', 'partial', 'failed') DEFAULT 'completed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Coupons System
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT '',
  `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` DECIMAL(10, 4) NOT NULL,
  `min_order_amount` DECIMAL(10, 4) DEFAULT 0.0000,
  `max_discount` DECIMAL(10, 4) DEFAULT NULL,
  `total_usage_limit` INT DEFAULT 0,
  `per_user_limit` INT DEFAULT 1,
  `used_count` INT DEFAULT 0,
  `service_ids` TEXT DEFAULT NULL,
  `category_ids` TEXT DEFAULT NULL,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `starts_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_code` (`code`),
  KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Coupon Usages
CREATE TABLE IF NOT EXISTS `coupon_usages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `coupon_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `order_id` INT DEFAULT NULL,
  `discount_amount` DECIMAL(12, 4) NOT NULL,
  `original_amount` DECIMAL(12, 4) NOT NULL,
  `final_amount` DECIMAL(12, 4) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_coupon` (`coupon_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Flash Sales System
CREATE TABLE IF NOT EXISTS `flash_sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `banner_text` VARCHAR(255) DEFAULT '⚡ FLASH SALE - SPECIAL LIMITED TIME DISCOUNT! ⚡',
  `discount_type` ENUM('percentage', 'fixed_discount', 'fixed_price') DEFAULT 'percentage',
  `discount_value` DECIMAL(10, 4) NOT NULL,
  `applies_to` ENUM('all', 'category', 'service') DEFAULT 'service',
  `target_ids` TEXT DEFAULT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `sale_limit` INT DEFAULT 0,
  `sales_count` INT DEFAULT 0,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `badge_text` VARCHAR(50) DEFAULT 'FLASH SALE',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_starts_ends` (`starts_at`, `ends_at`, `is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Settings for 8 Features
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('ticket_automation_enabled', '1'),
('cron_automation_enabled', '1'),
('auto_refill_enabled', '1'),
('auto_refund_enabled', '1'),
('auto_refund_statuses', 'canceled,partial'),
('dripfeed_enabled', '1'),
('mass_order_enabled', '1'),
('coupons_enabled', '1'),
('flash_sales_enabled', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Seed Default Scheduled Cron Jobs
INSERT INTO `cron_jobs` (`name`, `task_key`, `description`, `interval_minutes`, `is_enabled`, `next_run_at`, `last_status`) VALUES
('Auto Refill Engine', 'auto_refill', 'Submits real refill requests to SMM API providers for eligible dropped orders', 5, 1, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'idle'),
('Auto Refund Processor', 'auto_refund', 'Automatically credits user wallets for canceled and partial provider orders', 5, 1, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'idle'),
('Drip-Feed Batch Runner', 'drip_feed', 'Dispatches scheduled batches for multi-run drip-feed orders to providers', 2, 1, DATE_ADD(NOW(), INTERVAL 2 MINUTE), 'idle'),
('Flash Sale Engine', 'flash_sale', 'Manages live flash sale statuses, limits, and pricing activations in real-time', 1, 1, DATE_ADD(NOW(), INTERVAL 1 MINUTE), 'idle'),
('Ticket Automation Sweep', 'ticket_automation', 'Evaluates ticket conditions, triggers auto-replies, and enforces SLA status updates', 5, 1, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'idle'),
('Order Status Sync Engine', 'order_status', 'Automatically synchronizes real-time order delivery status, start count, and remains with upstream SMM API providers', 1, 1, DATE_ADD(NOW(), INTERVAL 1 MINUTE), 'idle')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Default Ticket Automation Rules
INSERT INTO `ticket_automation_rules` (`name`, `is_enabled`, `trigger_event`, `condition_match_type`, `keyword_contains`, `priority_filter`, `category_filter`, `action_auto_reply`, `reply_message`, `action_change_status`, `action_change_priority`, `rule_priority`) VALUES
('Urgent Priority Fast Response', 1, 'ticket_created', 'all', '', 'high', 'all', 1, 'Hello! Your high priority ticket has been escalated to our senior technical response team. We are actively reviewing your case.', 'answered', 'high', 1),
('Drop & Refill Fast Help', 1, 'ticket_created', 'any', 'drop,refill,fell,decrease', 'all', 'all', 1, 'Hi there! If you are inquiring about a drop on your order, please make sure your account is public. Eligible refill services can be refilled automatically through the Auto Refill system or Order History tab.', 'answered', NULL, 2)
ON DUPLICATE KEY UPDATE `reply_message` = VALUES(`reply_message`);

-- Seed Default Coupons
INSERT INTO `coupons` (`code`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount`, `total_usage_limit`, `per_user_limit`, `used_count`, `is_enabled`, `starts_at`, `expires_at`) VALUES
('WELCOME10', 'Welcome 10% Discount on any order over $1.00', 'percentage', 10.0000, 1.0000, 15.0000, 500, 2, 0, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('ROSE2OFF', 'Flat $2.00 Off on orders above $5.00', 'fixed', 2.0000, 5.0000, 2.0000, 200, 1, 0, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY))
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Seed Default Flash Sale
INSERT INTO `flash_sales` (`title`, `description`, `banner_text`, `discount_type`, `discount_value`, `applies_to`, `target_ids`, `starts_at`, `ends_at`, `is_enabled`, `badge_text`) VALUES
('Weekend Engagement Flash Sale', 'Get a massive 15% instant discount across all Instagram & YouTube services!', '⚡ FLASH SALE: Extra 15% OFF Instagram & YouTube services! Limited time only! ⚡', 'percentage', 15.0000, 'category', '1,2', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 7 DAY), 1, '15% OFF')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 15. Telegram Group Join Popup Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('telegram_popup_enabled', '0'),
('telegram_group_url', ''),
('telegram_popup_title', 'Stay Connected With Us'),
('telegram_popup_message', 'Join our official Telegram community for important updates, announcements, offers and latest news.')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 16. Admin Notice Popup Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('notice_popup_enabled', '0'),
('notice_popup_title', 'Important Notice'),
('notice_popup_message', 'Scheduled maintenance will be carried out tonight.')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);



