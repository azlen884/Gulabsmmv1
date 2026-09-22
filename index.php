<?php
/**
 * RoseSMM - Core Front Controller and Router
 * Handles all clean URLs on Apache, Nginx, LiteSpeed, and PHP CLI
 */

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

// Normalize trailing slashes (except root)
$cleanUri = rtrim($uri, '/');
if (empty($cleanUri)) {
    $cleanUri = '/';
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
    '/services' => __DIR__ . '/views/user/services.php',
    '/add-funds' => __DIR__ . '/views/user/add_funds.php',
    '/wallet' => __DIR__ . '/views/user/wallet.php',
    '/transactions' => __DIR__ . '/views/user/transactions.php',
    '/support' => __DIR__ . '/views/user/support.php',
    '/notifications' => __DIR__ . '/views/user/notifications.php',
    '/profile' => __DIR__ . '/views/user/profile.php',
    '/payment/verify' => __DIR__ . '/views/user/payment_verify.php',
    '/payment/cancel' => __DIR__ . '/views/user/payment_cancel.php',
    '/payment/checkout' => __DIR__ . '/views/user/checkout.php',

    // API Endpoints
    '/api/order/create' => __DIR__ . '/api/order/create.php',
    '/api/funds/add' => __DIR__ . '/api/funds/add.php',
    '/payment/initiate' => __DIR__ . '/api/payment/initiate.php',
    '/payment/webhook' => __DIR__ . '/api/payment/webhook.php',
    '/api/payment/status' => __DIR__ . '/api/payment/status.php',
    '/api/currency/switch' => __DIR__ . '/api/currency/switch.php',
    '/api/tickets/create' => __DIR__ . '/api/tickets/create.php',
    '/api/tickets/reply' => __DIR__ . '/api/tickets/reply.php',
    '/api/v2' => __DIR__ . '/api/v2.php',

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
    '/admin/payment-gateways' => __DIR__ . '/views/admin/payment_gateways.php',
    '/admin/currencies' => __DIR__ . '/views/admin/currencies.php',
    '/admin/sliders' => __DIR__ . '/views/admin/sliders.php',
    '/admin/tickets' => __DIR__ . '/views/admin/tickets.php',
    '/admin/notifications' => __DIR__ . '/views/admin/notifications.php',
    '/admin/settings' => __DIR__ . '/views/admin/settings.php',
    '/admin/theme' => __DIR__ . '/views/admin/theme.php',
];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>404 Not Found - RoseSMM</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">
  <div class="max-w-md w-full text-center bg-white p-8 rounded-3xl border border-[#FCE4E8] shadow-sm">
    <div class="text-4xl font-black text-rose-500 mb-2">404</div>
    <h1 class="text-lg font-bold text-slate-800 mb-2">Page Not Found</h1>
    <p class="text-xs text-slate-500 mb-6">The page or resource you requested could not be located.</p>
    <a href="/" class="px-5 py-2.5 rounded-full bg-rose-500 text-white font-bold text-xs shadow-sm hover:bg-rose-600 transition-colors">
      Return to Home
    </a>
  </div>
</body>
</html>
