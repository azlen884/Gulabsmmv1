<?php
/**
 * RoseSMM - Database Connection and Core Helpers
 * Real MySQL / MariaDB PDO Connection
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Database configuration
$customConfig = __DIR__ . '/db_config.php';
if (file_exists($customConfig)) {
    require_once $customConfig;
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'smm_panel');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');

/**
 * Get PDO Database Connection
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Attempt socket connection if 127.0.0.1 fails
            try {
                $dsnSocket = "mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsnSocket, DB_USER, DB_PASS, $options);
            } catch (PDOException $e2) {
                die("MySQL Connection Error: " . $e2->getMessage() . "<br>Please run the installer at <a href='/install'>/install</a>");
            }
        }
    }
    return $pdo;
}

/**
 * Get setting value from database
 */
function get_setting($key, $default = '') {
    try {
        $stmt = getDB()->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Update or insert setting value
 */
function set_setting($key, $value) {
    $stmt = getDB()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current logged in user
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['username']);
            return null;
        }
    }
    return $user;
}

/**
 * Get active user currency
 */
function get_user_currency() {
    if (isset($_SESSION['user_currency'])) {
        return $_SESSION['user_currency'];
    }
    $u = current_user();
    if ($u && !empty($u['currency'])) {
        $_SESSION['user_currency'] = $u['currency'];
        return $u['currency'];
    }
    return 'USD';
}

/**
 * Get all active currencies
 */
function get_currencies() {
    static $currencies = null;
    if ($currencies === null) {
        $stmt = getDB()->query("SELECT * FROM currencies WHERE status = 'active' ORDER BY is_default DESC, id ASC");
        $currencies = $stmt->fetchAll();
    }
    return $currencies;
}

/**
 * Convert and format currency
 */
function format_price($amountUSD, $targetCurrency = null) {
    if ($targetCurrency === null) {
        $targetCurrency = get_user_currency();
    }
    $currencies = get_currencies();
    $currMap = [];
    foreach ($currencies as $c) {
        $currMap[$c['code']] = $c;
    }

    $symbol = '$';
    $rate = 1.0;

    // Rates in database are relative to INR (default INR = 1.0)
    // USD rate is e.g. 0.011765 (1 INR = 0.011765 USD => 1 USD = 85 INR)
    $usdRate = isset($currMap['USD']) ? (float)$currMap['USD']['rate'] : 0.011765;
    $targetRate = isset($currMap[$targetCurrency]) ? (float)$currMap[$targetCurrency]['rate'] : 1.0;
    $targetSymbol = isset($currMap[$targetCurrency]) ? $currMap[$targetCurrency]['symbol'] : '$';

    if ($targetCurrency === 'USD') {
        $converted = (float)$amountUSD;
        $symbol = '$';
    } elseif ($targetCurrency === 'INR') {
        // Convert USD to INR
        $converted = (float)$amountUSD / ($usdRate > 0 ? $usdRate : 0.011765);
        $symbol = '₹';
    } else {
        // Convert USD -> INR -> Target
        $amountINR = (float)$amountUSD / ($usdRate > 0 ? $usdRate : 0.011765);
        $converted = $amountINR * $targetRate;
        $symbol = $targetSymbol;
    }

    return $symbol . number_format($converted, 2);
}

/**
 * Sanitize output
 */
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}
