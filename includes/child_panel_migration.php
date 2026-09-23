<?php
/**
 * Child Panel System Database Migration
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$db->exec("
CREATE TABLE IF NOT EXISTS `child_panels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan` enum('basic','advanced') NOT NULL DEFAULT 'basic',
  `domain` varchar(191) NOT NULL,
  `panel_name` varchar(150) NOT NULL,
  `admin_username` varchar(100) NOT NULL,
  `admin_email` varchar(191) NOT NULL,
  `admin_password_hash` varchar(255) NOT NULL,
  `status` enum('pending_payment','pending_approval','approved','active','suspended','rejected','cancelled') NOT NULL DEFAULT 'pending_approval',
  `payment_status` enum('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_amount` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `payment_currency` varchar(10) NOT NULL DEFAULT 'INR',
  `payment_method` varchar(100) NOT NULL DEFAULT 'wallet_balance',
  `payment_ref` varchar(191) DEFAULT NULL,
  `dns_status` enum('pending_dns','verifying','dns_connected','verification_failed') NOT NULL DEFAULT 'pending_dns',
  `dns_last_checked` datetime DEFAULT NULL,
  `dns_details` text DEFAULT NULL,
  `ssl_status` enum('ssl_pending','ssl_active','ssl_failed') NOT NULL DEFAULT 'ssl_pending',
  `ssl_last_checked` datetime DEFAULT NULL,
  `ssl_details` text DEFAULT NULL,
  `nameserver_1` varchar(191) DEFAULT NULL,
  `nameserver_2` varchar(191) DEFAULT NULL,
  `branding_logo` varchar(255) DEFAULT NULL,
  `branding_favicon` varchar(255) DEFAULT NULL,
  `theme` varchar(50) NOT NULL DEFAULT 'default',
  `support_email` varchar(191) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `currency_symbol` varchar(10) NOT NULL DEFAULT '₹',
  `price_margin_percent` decimal(5,2) NOT NULL DEFAULT 15.00,
  `external_api_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_child_panel_domain` (`domain`),
  KEY `idx_child_panel_user` (`user_id`),
  KEY `idx_child_panel_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
CREATE TABLE IF NOT EXISTS `child_panel_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_panel_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `api_url` varchar(255) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `balance` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cpp_panel` (`child_panel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
CREATE TABLE IF NOT EXISTS `child_panel_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_panel_id` int(11) NOT NULL,
  `source_type` enum('parent','external') NOT NULL DEFAULT 'parent',
  `external_provider_id` int(11) DEFAULT NULL,
  `external_service_id` varchar(50) DEFAULT NULL,
  `parent_service_id` int(11) DEFAULT NULL,
  `category_name` varchar(150) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `original_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `selling_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `margin_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `margin_value` decimal(10,4) NOT NULL DEFAULT 20.0000,
  `min_quantity` int(11) NOT NULL DEFAULT 10,
  `max_quantity` int(11) NOT NULL DEFAULT 100000,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cps_panel` (`child_panel_id`),
  KEY `idx_cps_provider` (`external_provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
CREATE TABLE IF NOT EXISTS `child_panel_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_panel_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `balance` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cpu_panel_user` (`child_panel_id`, `username`),
  KEY `idx_cpu_panel` (`child_panel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
CREATE TABLE IF NOT EXISTS `child_panel_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_panel_id` int(11) NOT NULL,
  `child_panel_user_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `source_type` enum('parent','external') NOT NULL,
  `parent_order_id` int(11) DEFAULT NULL,
  `external_order_id` varchar(100) DEFAULT NULL,
  `link` text NOT NULL,
  `quantity` int(11) NOT NULL,
  `charge` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `status` enum('pending','in_progress','completed','partial','canceled','refunded') NOT NULL DEFAULT 'pending',
  `api_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cpo_panel` (`child_panel_id`),
  KEY `idx_cpo_user` (`child_panel_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$defaultSettings = [
    'child_panel_system_enabled' => '1',
    'child_panel_ns1' => 'ns1.rosesmm.com',
    'child_panel_ns2' => 'ns2.rosesmm.com',
    'child_panel_server_ip' => '127.0.0.1',
    'child_panel_basic_price' => '1499.00',
    'child_panel_advanced_price' => '3499.00',
    'child_panel_currency' => 'INR'
];

foreach ($defaultSettings as $k => $v) {
    $stmt = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$k, $v]);
}

echo "Child Panel migration completed successfully.\n";
