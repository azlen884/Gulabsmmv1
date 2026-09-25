<?php
/**
 * RoseSMM - Database Connection and Core Helpers
 * Real MySQL / MariaDB PDO Connection
 */

// Ensure output buffering is active so redirects can always send headers safely
if (ob_get_level() === 0) {
    ob_start();
}

// Start session if not already started (Web requests only; avoid session headers/cookies in CLI cron execution)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $isHttps ? 'None' : 'Lax'
    ]);
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
 * Get dynamic site name configured in settings
 */
function get_site_name($default = 'SMM Panel') {
    static $siteName = null;
    if ($siteName === null) {
        $siteName = get_setting('site_name', $default);
    }
    return !empty($siteName) ? $siteName : $default;
}

/**
 * Get dynamic site title configured in settings
 */
function get_site_title($default = '') {
    static $siteTitle = null;
    if ($siteTitle === null) {
        $name = get_site_name();
        $siteTitle = get_setting('site_title', $default ?: ($name . ' - Social Media Services'));
    }
    return !empty($siteTitle) ? $siteTitle : ($default ?: (get_site_name() . ' - Social Media Services'));
}

/**
 * Check if maintenance mode is enabled
 */
function is_maintenance_mode() {
    return (string)get_setting('maintenance_mode', '0') === '1';
}

/**
 * Update or insert setting value
 */
function set_setting($key, $value) {
    $stmt = getDB()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Validate and sanitize Telegram URL
 * Ensures URL is HTTPS and points strictly to trusted Telegram domains
 */
function validate_telegram_url(?string $url): string {
    if (empty($url)) {
        return '';
    }
    $trimmed = trim($url);
    if (!preg_match('#^https://#i', $trimmed)) {
        return '';
    }
    $parsed = parse_url($trimmed);
    if (!$parsed || empty($parsed['host']) || ($parsed['scheme'] ?? '') !== 'https') {
        return '';
    }
    $host = strtolower($parsed['host']);
    $allowedHosts = ['t.me', 'www.t.me', 'telegram.me', 'www.telegram.me', 'web.telegram.org'];
    if (!in_array($host, $allowedHosts, true)) {
        return '';
    }
    // Prevent unsafe characters
    if (preg_match('/[<>"\'`\s\\\]/', $trimmed)) {
        return '';
    }
    $sanitized = filter_var($trimmed, FILTER_SANITIZE_URL);
    return is_string($sanitized) ? $sanitized : '';
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
    return get_setting('currency_default', 'INR');
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

function get_active_currencies() {
    return get_currencies();
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

/**
 * =========================================================================
 * THEME MANAGEMENT SYSTEM (Admin-Only Controlled)
 * Allowed themes:
 * 1. 'default'        -> Existing Theme
 * 2. 'premium_red'    -> Premium Red + White
 * 3. 'premium_green'  -> Premium Green + White
 * 4. 'midnight_blue'  -> Ultra-Premium Midnight + Electric Blue
 * =========================================================================
 */

/**
 * Get active theme key with strict server-side fallback
 */
function get_active_theme() {
    $theme = get_setting('active_theme', 'default');
    $valid = ['default', 'premium_red', 'premium_green', 'midnight_blue', 'holographic_aura'];
    return in_array($theme, $valid, true) ? $theme : 'default';
}

/**
 * Get list of available themes
 */
function get_available_themes() {
    return [
        'default' => [
            'id' => 'default',
            'name' => 'Existing Theme',
            'description' => 'Original signature Rose & Pink palette with soft gradient accents.',
            'primary_color' => '#FF3B69',
            'secondary_color' => '#FFF0F3',
            'accent_color' => '#E11D48',
            'badge' => 'Classic Rose',
            'features' => ['Original Rose Palette', 'Pink Gradients', 'Default Layout']
        ],
        'premium_red' => [
            'id' => 'premium_red',
            'name' => 'Premium Red + White',
            'description' => 'Sophisticated crimson & ruby tones with tasteful glassmorphism, crisp white contrast, and modern red accents.',
            'primary_color' => '#DC2626',
            'secondary_color' => '#FEF2F2',
            'accent_color' => '#B91C1C',
            'badge' => 'Glassmorphic Luxury',
            'features' => ['Tasteful Glassmorphic Navbar & Cards', 'Rich Crimson & Ruby Tones', 'High-Contrast White Canvas']
        ],
        'premium_green' => [
            'id' => 'premium_green',
            'name' => 'Premium Green + White',
            'description' => 'Fresh, crisp emerald & jade shades paired with ultra-clean white surfaces, refined borders, and distinct visual identity.',
            'primary_color' => '#059669',
            'secondary_color' => '#ECFDF5',
            'accent_color' => '#047857',
            'badge' => 'Emerald Luxury',
            'features' => ['Crisp Emerald & Jade Accents', 'Clean Modern White Cards', 'Elevated Visual Hierarchy']
        ],
        'midnight_blue' => [
            'id' => 'midnight_blue',
            'name' => 'Ultra-Premium Midnight + Electric Blue',
            'description' => 'Ultra-premium midnight navy canvas paired with high-contrast electric cobalt blue, soft periwinkle highlights, and platinum accents.',
            'primary_color' => '#2563EB',
            'secondary_color' => '#080C15',
            'accent_color' => '#A5B4FC',
            'badge' => 'Midnight Luxury',
            'features' => ['Deep Midnight & Navy Canvas', 'Electric Cobalt Blue Buttons & Highlights', 'Refined Soft Periwinkle Accents']
        ],
        'holographic_aura' => [
            'id' => 'holographic_aura',
            'name' => 'Holographic Aura',
            'description' => 'Futuristic holographic aesthetic with soft cyan, aqua, and lavender gradients, translucent glowing cards, and polished SaaS elegance.',
            'primary_color' => '#06B6D4',
            'secondary_color' => '#F6F8FE',
            'accent_color' => '#A855F7',
            'badge' => 'Holographic Aura',
            'features' => ['Soft Holographic Iridescent Gradients', 'Translucent Glass Cards & Ambient Aura', 'Crisp High-Contrast Typography & Icons']
        ],
    ];
}

/**
 * Set active theme with strict server-side validation and security check
 * Only authenticated Admin is authorized to change the theme.
 */
function set_active_theme($themeKey) {
    if (!is_admin()) {
        return false;
    }
    $valid = ['default', 'premium_red', 'premium_green', 'midnight_blue', 'holographic_aura'];
    if (!in_array($themeKey, $valid, true)) {
        return false;
    }
    return set_setting('active_theme', $themeKey);
}

/**
 * Render active theme stylesheet link and meta tags in HTML <head>
 */
function render_theme_head_tags() {
    $decorCss = '/assets/css/theme-decorations.css';
    $decorPath = __DIR__ . '/..' . $decorCss;
    $decorVer = file_exists($decorPath) ? filemtime($decorPath) : time();
    echo '<link rel="stylesheet" id="app-theme-decorations-css" href="' . htmlspecialchars($decorCss . '?v=' . $decorVer, ENT_QUOTES, 'UTF-8') . '">' . "\n";

    $active = get_active_theme();
    $cssFile = '';
    if ($active === 'premium_red') {
        $cssFile = '/assets/css/theme-premium-red.css';
    } elseif ($active === 'premium_green') {
        $cssFile = '/assets/css/theme-premium-green.css';
    } elseif ($active === 'midnight_blue') {
        $cssFile = '/assets/css/theme-midnight-blue.css';
    } elseif ($active === 'holographic_aura') {
        $cssFile = '/assets/css/theme-holographic-aura.css';
    }

    if (!empty($cssFile)) {
        $fullPath = __DIR__ . '/..' . $cssFile;
        $ver = file_exists($fullPath) ? filemtime($fullPath) : time();
        echo '<link rel="stylesheet" id="app-active-theme-css" href="' . htmlspecialchars($cssFile . '?v=' . $ver, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}

/**
 * Return theme class for body tag
 */
function get_theme_body_class() {
    $active = get_active_theme();
    if ($active === 'premium_red') {
        return 'theme-premium-red';
    } elseif ($active === 'premium_green') {
        return 'theme-premium-green';
    } elseif ($active === 'midnight_blue') {
        return 'theme-midnight-blue';
    } elseif ($active === 'holographic_aura') {
        return 'theme-holographic-aura';
    }
    return 'theme-default';
}

