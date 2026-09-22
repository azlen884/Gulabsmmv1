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
            // If 127.0.0.1 fails, try localhost fallback
            $connected = false;
            if (DB_HOST === '127.0.0.1') {
                try {
                    $dsnLocal = "mysql:host=localhost;port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                    $pdo = new PDO($dsnLocal, DB_USER, DB_PASS, $options);
                    $connected = true;
                } catch (PDOException $eLocal) {}
            }

            // Check standard Unix socket paths if available
            if (!$connected) {
                $possibleSockets = ['/run/mysqld/mysqld.sock', '/var/run/mysqld/mysqld.sock', '/var/lib/mysql/mysql.sock', '/tmp/mysql.sock'];
                foreach ($possibleSockets as $socket) {
                    if (file_exists($socket)) {
                        try {
                            $dsnSocket = "mysql:unix_socket=$socket;dbname=" . DB_NAME . ";charset=utf8mb4";
                            $pdo = new PDO($dsnSocket, DB_USER, DB_PASS, $options);
                            $connected = true;
                            break;
                        } catch (PDOException $eSocket) {}
                    }
                }
            }

            if (!$connected) {
                // Return clean diagnostic output rather than a blank white page
                http_response_code(500);
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Database Connection Error - RoseSMM</title>';
                echo '<script src="https://cdn.tailwindcss.com"></script></head>';
                echo '<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">';
                echo '<div class="max-w-md w-full bg-white p-8 rounded-3xl border border-[#FCE4E8] shadow-sm text-center">';
                echo '<div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 border border-rose-100 flex items-center justify-center mx-auto mb-4 font-bold text-xl">!</div>';
                echo '<h1 class="text-xl font-bold text-slate-900 mb-2">Database Connection Error</h1>';
                echo '<p class="text-xs text-rose-600 bg-rose-50 p-3 rounded-xl border border-rose-100 mb-6 font-mono text-left break-words">' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p class="text-xs text-slate-500 mb-6">Please verify your database credentials in <code>config/db_config.php</code> or run the system installer.</p>';
                echo '<a href="/install" class="inline-block px-6 py-2.5 rounded-full bg-rose-500 text-white font-bold text-xs shadow-sm hover:bg-rose-600 transition-colors">Run Installer</a>';
                echo '</div></body></html>';
                exit;
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
    if (isset($_SESSION['user_currency']) && !empty($_SESSION['user_currency'])) {
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
 * Get all active currencies from MySQL
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
 * Get single currency data by code (cached)
 */
function get_currency_info($code) {
    $code = strtoupper(trim((string)$code));
    $currencies = get_currencies();
    foreach ($currencies as $c) {
        if (strtoupper($c['code']) === $code) {
            return $c;
        }
    }
    return [
        'code' => $code ?: 'USD',
        'symbol' => ($code === 'INR' ? '₹' : ($code === 'EUR' ? '€' : ($code === 'GBP' ? '£' : '$'))),
        'rate' => ($code === 'INR' ? 83.5 : 1.0),
        'name' => $code
    ];
}

/**
 * Get real configured exchange rate from base_currency to target_currency
 * Exchange rates in MySQL currencies table are defined per $1 USD base.
 * Formula: exchange_rate(from -> to) = (rate_to / rate_from)
 */
function get_exchange_rate($fromCurrency = 'USD', $toCurrency = null) {
    if ($toCurrency === null) {
        $toCurrency = get_user_currency();
    }
    $fromCode = strtoupper(trim((string)$fromCurrency)) ?: 'USD';
    $toCode = strtoupper(trim((string)$toCurrency)) ?: 'USD';

    if ($fromCode === $toCode) {
        return 1.0;
    }

    $fromInfo = get_currency_info($fromCode);
    $toInfo = get_currency_info($toCode);

    $fromRate = (float)($fromInfo['rate'] ?? 1.0);
    $toRate = (float)($toInfo['rate'] ?? 1.0);

    if ($fromRate <= 0) $fromRate = 1.0;
    if ($toRate <= 0) $toRate = 1.0;

    return $toRate / $fromRate;
}

/**
 * Calculate converted price float value
 * converted_price = base_price * exchange_rate(base_currency -> user_currency)
 */
function convert_price($amount, $fromCurrency = 'USD', $toCurrency = null) {
    $rate = get_exchange_rate($fromCurrency, $toCurrency);
    return round((float)$amount * $rate, 4);
}

/**
 * Convert and format currency for display
 * Preserves the service's base price and applies the configured exchange rate dynamically
 */
function format_price($amount, $targetCurrency = null, $fromCurrency = 'USD') {
    if ($targetCurrency === null) {
        $targetCurrency = get_user_currency();
    }
    $curr = get_currency_info($targetCurrency);
    $symbol = $curr['symbol'] ?? '$';
    $converted = convert_price($amount, $fromCurrency, $targetCurrency);

    // Format with 2 decimals (or 4 if less than 0.01 and greater than 0)
    $decimals = ($converted < 0.01 && $converted > 0) ? 4 : 2;
    return $symbol . number_format($converted, $decimals);
}

/**
 * Sanitize output
 */
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}
