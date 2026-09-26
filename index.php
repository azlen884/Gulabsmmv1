<?php
/**
 * RoseSMM - Core Front Controller and Router
 * Handles all clean URLs on Apache, Nginx, LiteSpeed, and PHP CLI
 */

// Start output buffering to prevent headers already sent issues across all routes
if (ob_get_level() === 0) {
    ob_start();
}

// Determine requested URI
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($rawUri, PHP_URL_PATH);

// Normalize script directory base path (supports both root and subfolder like /rsmm)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
if (preg_match('#/(index|router)\.php$#', $scriptName)) {
    $basePath = rtrim($scriptDir, '/');
} else {
    $basePath = '';
}

$uri = $requestPath;
if (!empty($basePath) && $basePath !== '/' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Ensure leading slash
$uri = '/' . ltrim($uri, '/');

// Strip /index.php if present at the start of path
if (strpos($uri, '/index.php') === 0) {
    $uri = substr($uri, strlen('/index.php'));
    $uri = '/' . ltrim($uri, '/');
}

require_once __DIR__ . '/config/database.php';

// Normalize trailing slashes (except root)
$cleanUri = rtrim($uri, '/');
if (empty($cleanUri)) {
    $cleanUri = '/';
}

// Server-Side Maintenance Mode Enforcement
// When enabled by admin, all public and user routes show maintenance page.
// Admin users (and admin login) remain accessible server-side with no public shortcuts.
if (is_maintenance_mode()) {
    $isAdminPath = (strpos($cleanUri, '/admin') === 0);
    $isCronPath = ($cleanUri === '/cron' || $cleanUri === '/cron.php');
    $isWebhookPath = ($cleanUri === '/payment/webhook');

    if (!$isAdminPath && !$isCronPath && !$isWebhookPath && !is_admin()) {
        if (strpos($cleanUri, '/api/') === 0 || strpos($cleanUri, '/payment/') === 0) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: 300');
            echo json_encode([
                'status' => 'maintenance',
                'message' => 'The system is currently undergoing scheduled maintenance. Please check back shortly.'
            ]);
            exit;
        }

        require __DIR__ . '/views/public/maintenance.php';
        exit;
    }
}

// Clean Route Map
$routes = [
    '/' => __DIR__ . '/views/public/landing.php',
    '/index.php' => __DIR__ . '/views/public/landing.php',
    '/login' => __DIR__ . '/views/public/login.php',
    '/login.php' => __DIR__ . '/views/public/login.php',
    '/register' => __DIR__ . '/views/public/register.php',
    '/register.php' => __DIR__ . '/views/public/register.php',
    '/logout' => __DIR__ . '/views/public/logout.php',
    '/logout.php' => __DIR__ . '/views/public/logout.php',
    '/install' => __DIR__ . '/install/index.php',

    // User Portal
    '/dashboard' => __DIR__ . '/views/user/dashboard.php',
    '/order' => __DIR__ . '/views/user/order.php',
    '/orders' => __DIR__ . '/views/user/orders.php',
    '/mass-order' => __DIR__ . '/views/user/mass_order.php',
    '/massorder' => __DIR__ . '/views/user/mass_order.php',
    '/drip-feed' => __DIR__ . '/views/user/drip_feed.php',
    '/flash-sales' => __DIR__ . '/views/user/flash_sales.php',
    '/refills' => __DIR__ . '/views/user/refills.php',
    '/services' => __DIR__ . '/views/user/services.php',
    '/add-funds' => __DIR__ . '/views/user/add_funds.php',
    '/wallet' => __DIR__ . '/views/user/wallet.php',
    '/transactions' => __DIR__ . '/views/user/transactions.php',
    '/referrals' => __DIR__ . '/views/user/referrals.php',
    '/refer-earn' => __DIR__ . '/views/user/referrals.php',
    '/support' => __DIR__ . '/views/user/support.php',
    '/notifications' => __DIR__ . '/views/user/notifications.php',
    '/profile' => __DIR__ . '/views/user/profile.php',
    '/payment/verify' => __DIR__ . '/views/user/payment_verify.php',
    '/payment/cancel' => __DIR__ . '/views/user/payment_cancel.php',
    '/payment/checkout' => __DIR__ . '/views/user/checkout.php',

    // API Endpoints
    '/api/order/create' => __DIR__ . '/api/order/create.php',
    '/api/order/mass' => __DIR__ . '/api/order/mass.php',
    '/api/order/refill' => __DIR__ . '/api/order/refill.php',
    '/api/coupon/validate' => __DIR__ . '/api/coupon/validate.php',
    '/api/flash-sales/active' => __DIR__ . '/api/flash-sales/active.php',
    '/api/funds/add' => __DIR__ . '/api/funds/add.php',
    '/payment/initiate' => __DIR__ . '/api/payment/initiate.php',
    '/payment/webhook' => __DIR__ . '/api/payment/webhook.php',
    '/api/payment/status' => __DIR__ . '/api/payment/status.php',
    '/api/currency/switch' => __DIR__ . '/api/currency/switch.php',
    '/api/tickets/create' => __DIR__ . '/api/tickets/create.php',
    '/api/tickets/reply' => __DIR__ . '/api/tickets/reply.php',
    '/api/notifications/mark-read' => __DIR__ . '/api/notifications/mark-read.php',
    '/api/v2' => __DIR__ . '/api/v2.php',
    '/cron' => __DIR__ . '/cron.php',
    '/cron.php' => __DIR__ . '/cron.php',

    // Admin Console
    '/admin' => __DIR__ . '/views/admin/dashboard.php',
    '/admin/login' => __DIR__ . '/views/admin/login.php',
    '/admin/orders' => __DIR__ . '/views/admin/orders.php',
    '/admin/users' => __DIR__ . '/views/admin/users.php',
    '/admin/services' => __DIR__ . '/views/admin/services.php',
    '/admin/providers' => __DIR__ . '/views/admin/providers.php',
    '/admin/provider-services' => __DIR__ . '/views/admin/provider_services.php',
    '/admin/categories' => __DIR__ . '/views/admin/categories.php',
    '/admin/transactions' => __DIR__ . '/views/admin/transactions.php',
    '/admin/ticket-automation' => __DIR__ . '/views/admin/ticket_automation.php',
    '/admin/ticket-automation/create' => __DIR__ . '/views/admin/ticket_automation.php',
    '/admin/ticket_automation' => __DIR__ . '/views/admin/ticket_automation.php',
    '/admin/ticket_automation/create' => __DIR__ . '/views/admin/ticket_automation.php',
    '/admin/cron-jobs' => __DIR__ . '/views/admin/cron_jobs.php',
    '/admin/cron-job' => __DIR__ . '/views/admin/cron_jobs.php',
    '/admin/cronjob' => __DIR__ . '/views/admin/cron_jobs.php',
    '/admin/cronjobs' => __DIR__ . '/views/admin/cron_jobs.php',
    '/admin/refill' => __DIR__ . '/views/admin/refill.php',
    '/admin/refunds' => __DIR__ . '/views/admin/refunds.php',
    '/admin/drip-feed' => __DIR__ . '/views/admin/drip_feed.php',
    '/admin/coupons' => __DIR__ . '/views/admin/coupons.php',
    '/admin/flash-sales' => __DIR__ . '/views/admin/flash_sales.php',
    '/admin/referrals' => __DIR__ . '/views/admin/referrals.php',
    '/admin/refer-earn' => __DIR__ . '/views/admin/referrals.php',
    '/admin/payment-gateways' => __DIR__ . '/views/admin/payment_gateways.php',
    '/admin/currencies' => __DIR__ . '/views/admin/currencies.php',
    '/admin/sliders' => __DIR__ . '/views/admin/sliders.php',
    '/admin/testimonials' => __DIR__ . '/views/admin/testimonials.php',
    '/admin/tickets' => __DIR__ . '/views/admin/tickets.php',
    '/admin/notifications' => __DIR__ . '/views/admin/notifications.php',
    '/admin/settings' => __DIR__ . '/views/admin/settings.php',
    '/admin/theme' => __DIR__ . '/views/admin/theme.php',
];

// SMM Pro Independent Theme Dispatcher
if (get_active_theme() === 'smm_pro') {
    $smmProViews = [
        '/dashboard'     => __DIR__ . '/templates/smm-pro/views/dashboard.php',
        '/order'         => __DIR__ . '/templates/smm-pro/views/order.php',
        '/orders'        => __DIR__ . '/templates/smm-pro/views/orders.php',
        '/services'      => __DIR__ . '/templates/smm-pro/views/services.php',
        '/mass-order'    => __DIR__ . '/templates/smm-pro/views/mass_order.php',
        '/massorder'     => __DIR__ . '/templates/smm-pro/views/mass_order.php',
        '/drip-feed'     => __DIR__ . '/templates/smm-pro/views/drip_feed.php',
        '/refills'       => __DIR__ . '/templates/smm-pro/views/refills.php',
        '/add-funds'     => __DIR__ . '/templates/smm-pro/views/add_funds.php',
        '/wallet'        => __DIR__ . '/templates/smm-pro/views/wallet.php',
        '/transactions'  => __DIR__ . '/templates/smm-pro/views/wallet.php',
        '/support'       => __DIR__ . '/templates/smm-pro/views/support.php',
        '/profile'       => __DIR__ . '/templates/smm-pro/views/profile.php',
        '/notifications' => __DIR__ . '/templates/smm-pro/views/notifications.php',
        '/referrals'     => __DIR__ . '/templates/smm-pro/views/referrals.php',
        '/refer-earn'    => __DIR__ . '/templates/smm-pro/views/referrals.php',
    ];

    if (isset($smmProViews[$cleanUri]) && file_exists($smmProViews[$cleanUri])) {
        require $smmProViews[$cleanUri];
        exit;
    }
}

if (isset($routes[$cleanUri])) {
    require $routes[$cleanUri];
    exit;
}

// Check direct PHP file match
if (file_exists(__DIR__ . $cleanUri) && !is_dir(__DIR__ . $cleanUri) && substr($cleanUri, -4) === '.php') {
    require __DIR__ . $cleanUri;
    exit;
}
if (file_exists(__DIR__ . $cleanUri . '.php') && !is_dir(__DIR__ . $cleanUri . '.php')) {
    require __DIR__ . $cleanUri . '.php';
    exit;
}

// Check views/public/ direct matches
if (file_exists(__DIR__ . '/views/public' . $cleanUri . '.php')) {
    require __DIR__ . '/views/public' . $cleanUri . '.php';
    exit;
}

// 404 Fallback
http_response_code(404);
$siteName = get_site_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>404 Not Found - <?= e($siteName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            rose: {
              50: '#FFF0F3',
              100: '#FFE2E8',
              200: '#FCD3DC',
              500: '#FF3B69',
              600: '#E11D48',
            }
          }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <?php render_theme_head_tags(); ?>
</head>
<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex items-center justify-center p-4 <?= get_theme_body_class() ?>">
  <div class="max-w-md w-full text-center bg-white p-8 rounded-3xl border border-[#FCE4E8] shadow-sm">
    <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center">
      <i data-lucide="compass" class="w-7 h-7"></i>
    </div>
    <div class="text-4xl font-black text-rose-500 mb-2">404</div>
    <h1 class="text-lg font-bold text-slate-800 mb-2">Page Not Found</h1>
    <p class="text-xs text-slate-500 mb-6">The page or resource you requested could not be located on <?= e($siteName) ?>.</p>
    <a href="/" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-rose-500 text-white font-bold text-xs shadow-sm hover:bg-rose-600 transition-colors">
      <i data-lucide="arrow-left" class="w-4 h-4"></i>
      <span>Return to Home</span>
    </a>
  </div>
  <script>
    if (window.lucide) {
      lucide.createIcons();
    }
  </script>
</body>
</html>
