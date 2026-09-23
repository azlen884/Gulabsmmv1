<?php
/**
 * RoseSMM - Multi-Tenant Provisioned Child Panel Application
 * Renders the live branded SMM panel on the customer's custom domain with full currency conversion support
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ChildPanelHelper.php';

$db = getDB();

// 1. Resolve Tenant
$tenant = ChildPanelHelper::resolveCurrentTenant();

if (!$tenant) {
    // If accessing child panel preview directly with query parameter
    $previewId = (int)($_GET['child_panel'] ?? 0);
    if ($previewId > 0) {
        $stmt = $db->prepare("SELECT * FROM child_panels WHERE id = ?");
        $stmt->execute([$previewId]);
        $tenant = $stmt->fetch();
    }
}

if (!$tenant) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Domain Not Configured</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-100 flex items-center justify-center min-h-screen p-4 text-center"><div class="bg-white p-8 rounded-3xl max-w-md shadow-sm border border-slate-200"><h1 class="text-xl font-black text-slate-800">Domain Not Configured</h1><p class="text-xs text-slate-500 mt-2">This domain is not yet mapped to an active Child Panel. Please point your nameservers and verify domain status in the management console.</p></div></body></html>';
    exit;
}

// 2. Check Activation Status
if ($tenant['status'] === 'suspended') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Panel Suspended</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-100 flex items-center justify-center min-h-screen p-4 text-center"><div class="bg-white p-8 rounded-3xl max-w-md shadow-sm border border-slate-200"><h1 class="text-xl font-black text-rose-600">Child Panel Suspended</h1><p class="text-xs text-slate-500 mt-2">This panel has been suspended by administration. Please contact support to reactivate your service.</p></div></body></html>';
    exit;
}

if ($tenant['status'] === 'pending_approval') {
    echo '<!DOCTYPE html><html><head><title>Pending Setup - ' . htmlspecialchars($tenant['panel_name']) . '</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-50 flex items-center justify-center min-h-screen p-4 text-center"><div class="bg-white p-8 rounded-3xl max-w-lg shadow-sm border border-slate-200"><div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-3 font-bold text-lg">⏳</div><h1 class="text-xl font-black text-slate-800">' . htmlspecialchars($tenant['panel_name']) . '</h1><p class="text-xs text-slate-500 mt-2">This Child Panel is currently undergoing domain nameserver verification and administrative provisioning. Please check back shortly.</p></div></body></html>';
    exit;
}

// Active currency resolution
$userCurrency = get_user_currency();
$currencies = get_currencies();
$currencyInfo = get_currency_info($userCurrency);
$currSymbol = $currencyInfo['symbol'] ?? '$';

// Session scoping for Child Panel user authentication
$cpSessionKey = 'cp_user_' . $tenant['id'];
$cpUser = $_SESSION[$cpSessionKey] ?? null;

$action = $_POST['action'] ?? $_GET['action'] ?? 'home';
$msg = '';
$err = '';

// Handle Tenant Customer Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    $regUser = trim($_POST['username'] ?? '');
    $regEmail = trim($_POST['email'] ?? '');
    $regPass = $_POST['password'] ?? '';

    if (strlen($regUser) < 3 || !filter_var($regEmail, FILTER_VALIDATE_EMAIL) || strlen($regPass) < 6) {
        $err = "Please provide valid username, email and password (min 6 chars).";
    } else {
        $chk = $db->prepare("SELECT id FROM child_panel_users WHERE child_panel_id = ? AND (username = ? OR email = ?)");
        $chk->execute([$tenant['id'], $regUser, $regEmail]);
        if ($chk->fetch()) {
            $err = "Username or email is already registered on this panel.";
        } else {
            $hash = password_hash($regPass, PASSWORD_BCRYPT);
            // $1.00 USD welcome bonus converted to selected currency
            $bonusAmount = convert_price(1.00, 'USD', $userCurrency);
            $ins = $db->prepare("
                INSERT INTO child_panel_users (child_panel_id, username, email, password, balance, currency, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $ins->execute([$tenant['id'], $regUser, $regEmail, $hash, $bonusAmount, $userCurrency]);
            $newUid = $db->lastInsertId();

            $_SESSION[$cpSessionKey] = [
                'id' => $newUid,
                'username' => $regUser,
                'email' => $regEmail,
                'balance' => (float)$bonusAmount
            ];
            $cpUser = $_SESSION[$cpSessionKey];
            $msg = "Account created successfully! Welcome bonus " . format_price(1.00, $userCurrency, 'USD') . " credited.";
            $action = 'dashboard';
        }
    }
}

// Handle Tenant Customer Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $logUser = trim($_POST['username'] ?? '');
    $logPass = $_POST['password'] ?? '';

    $uStmt = $db->prepare("SELECT * FROM child_panel_users WHERE child_panel_id = ? AND (username = ? OR email = ?)");
    $uStmt->execute([$tenant['id'], $logUser, $logUser]);
    $foundUser = $uStmt->fetch();

    if ($foundUser && password_verify($logPass, $foundUser['password'])) {
        $_SESSION[$cpSessionKey] = [
            'id' => $foundUser['id'],
            'username' => $foundUser['username'],
            'email' => $foundUser['email'],
            'balance' => (float)$foundUser['balance']
        ];
        $cpUser = $_SESSION[$cpSessionKey];
        $msg = "Logged in successfully.";
        $action = 'dashboard';
    } else {
        $err = "Invalid username or password.";
    }
}

// Logout
if ($action === 'logout') {
    unset($_SESSION[$cpSessionKey]);
    $cpUser = null;
    $msg = "Logged out successfully.";
    $action = 'home';
}

// Handle Order Placement on Child Panel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'place_order') {
    if (!$cpUser) {
        $err = "Please log in to place an order.";
    } else {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $sourceType = $_POST['source_type'] ?? 'parent';
        $link = trim($_POST['link'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);

        if (empty($link) || $quantity <= 0) {
            $err = "Please enter a valid target link and quantity.";
        } else {
            // Find service and calculate cost & retail charge
            $serviceName = '';
            $unitRate = 0.0;
            $parentSrvId = null;
            $parentOrderId = null;
            $extProvId = null;
            $extSrvId = null;

            if ($sourceType === 'external' && $tenant['plan'] === 'advanced') {
                $extStmt = $db->prepare("SELECT * FROM child_panel_services WHERE id = ? AND child_panel_id = ? AND status = 'active'");
                $extStmt->execute([$serviceId, $tenant['id']]);
                $srv = $extStmt->fetch();
                if ($srv) {
                    $serviceName = $srv['name'];
                    $retailUSD = (float)$srv['selling_rate'];
                    // Converted selling rate in user's active currency
                    $unitRate = convert_price($retailUSD, 'USD', $userCurrency);
                    $costRate = (float)$srv['original_rate']; // wholesale cost in USD
                    $extProvId = $srv['external_provider_id'];
                    $extSrvId = $srv['external_service_id'];
                }
            } else {
                // Parent service
                $pStmt = $db->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
                $pStmt->execute([$serviceId]);
                $srv = $pStmt->fetch();
                if ($srv) {
                    $serviceName = $srv['name'];
                    $baseRateUSD = convert_price((float)$srv['rate'], $srv['currency'] ?? 'USD', 'USD');
                    $marginPct = (float)$tenant['price_margin_percent'];
                    $retailUSD = round($baseRateUSD * (1 + ($marginPct / 100)), 4);
                    // Server-side calculation uses the exact configured currency exchange rate
                    $unitRate = convert_price($retailUSD, 'USD', $userCurrency);
                    $costRate = $baseRateUSD; // wholesale cost in USD
                    $parentSrvId = $srv['id'];
                }
            }

            if (!$serviceName || $unitRate <= 0) {
                $err = "Selected service is currently unavailable.";
            } else {
                $charge = round(($unitRate / 1000) * $quantity, 4);
                $cost = round(($costRate / 1000) * $quantity, 4);

                // Fetch current user balance
                $ubStmt = $db->prepare("SELECT balance FROM child_panel_users WHERE id = ?");
                $ubStmt->execute([$cpUser['id']]);
                $currentBal = (float)$ubStmt->fetchColumn();

                if ($currentBal < $charge) {
                    $err = "Insufficient balance on your panel account. Order cost: " . $currSymbol . number_format($charge, 2) . ", your balance: " . $currSymbol . number_format($currentBal, 2);
                } else {
                    $newBal = $currentBal - $charge;
                    $db->prepare("UPDATE child_panel_users SET balance = ? WHERE id = ?")->execute([$newBal, $cpUser['id']]);
                    $_SESSION[$cpSessionKey]['balance'] = $newBal;
                    $cpUser['balance'] = $newBal;

                    // Forward to external provider or main panel
                    $apiResp = 'Placed on Child Panel';
                    $extOrderId = null;

                    if ($sourceType === 'external' && $extProvId) {
                        $provStmt = $db->prepare("SELECT * FROM child_panel_providers WHERE id = ?");
                        $provStmt->execute([$extProvId]);
                        $prov = $provStmt->fetch();
                        if ($prov) {
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $prov['api_url']);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                                'key' => $prov['api_key'],
                                'action' => 'add',
                                'service' => $extSrvId,
                                'link' => $link,
                                'quantity' => $quantity
                            ]));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            $fwd = curl_exec($ch);
                            curl_close($ch);
                            $fwdData = json_decode($fwd, true);
                            if (!empty($fwdData['order'])) {
                                $extOrderId = (string)$fwdData['order'];
                                $apiResp = "Forwarded to Provider API (Order #$extOrderId)";
                            }
                        }
                    } elseif ($sourceType === 'parent' && $parentSrvId) {
                        // Check if child panel owner has balance on parent platform
                        $ownerStmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
                        $ownerStmt->execute([$tenant['user_id']]);
                        $ownerBal = (float)$ownerStmt->fetchColumn();

                        if ($ownerBal >= $cost) {
                            // Deduct wholesale cost from Child Panel owner
                            $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$cost, $tenant['user_id']]);
                            // Insert into main platform orders table
                            $insParent = $db->prepare("
                                INSERT INTO orders (user_id, service_id, link, quantity, charge, status, created_at)
                                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                            ");
                            $insParent->execute([$tenant['user_id'], $parentSrvId, $link, $quantity, $cost]);
                            $parentOrderId = $db->lastInsertId();
                            $apiResp = "Routed to Main Platform (Order #$parentOrderId)";
                        } else {
                            $apiResp = "Pending fulfillment: Child Panel owner has insufficient platform balance.";
                        }
                    }

                    // Record Child Panel Order
                    $ordIns = $db->prepare("
                        INSERT INTO child_panel_orders (
                            child_panel_id, child_panel_user_id, service_id, source_type,
                            parent_order_id, external_order_id, link, quantity, charge, cost,
                            status, api_response
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?,
                            'pending', ?
                        )
                    ");
                    $ordIns->execute([
                        $tenant['id'], $cpUser['id'], $serviceId, $sourceType,
                        $parentOrderId, $extOrderId, $link, $quantity, $charge, $cost,
                        $apiResp
                    ]);

                    $msg = "Order successfully placed for $serviceName! Order ID: #" . $db->lastInsertId();
                    $action = 'orders';
                }
            }
        }
    }
}

// Fetch Catalog Services for this Child Panel
$parentServices = $db->query("
    SELECT s.*, c.name as category_name 
    FROM services s 
    LEFT JOIN categories c ON s.category_id = c.id 
    WHERE s.status = 'active' 
    ORDER BY c.sort_order ASC, s.sort_order ASC
")->fetchAll();

$importedServices = [];
if ($tenant['plan'] === 'advanced') {
    $impStmt = $db->prepare("SELECT * FROM child_panel_services WHERE child_panel_id = ? AND status = 'active' ORDER BY category_name ASC");
    $impStmt->execute([$tenant['id']]);
    $importedServices = $impStmt->fetchAll();
}

// Fetch User Orders if logged in
$myOrders = [];
if ($cpUser) {
    $ordStmt = $db->prepare("SELECT * FROM child_panel_orders WHERE child_panel_id = ? AND child_panel_user_id = ? ORDER BY id DESC LIMIT 50");
    $ordStmt->execute([$tenant['id'], $cpUser['id']]);
    $myOrders = $ordStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($tenant['panel_name']) ?> - Social Media Marketing Services</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

  <!-- TOP NOTIFICATION BANNER IF PREVIEW -->
  <?php if (!empty($_GET['child_panel'])): ?>
    <div class="bg-slate-900 text-slate-200 text-xs py-2 px-4 text-center font-bold flex items-center justify-center gap-2">
      <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
      <span>Previewing Child Panel: <strong><?= e($tenant['domain']) ?></strong> (<?= ucfirst($tenant['plan']) ?> Plan)</span>
      <a href="/child-panel?id=<?= $tenant['id'] ?>" class="ml-2 text-rose-400 underline hover:text-rose-300">Return to Admin Setup</a>
    </div>
  <?php endif; ?>

  <!-- HEADER NAVBAR -->
  <header class="bg-white border-b border-slate-200/80 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="?action=home<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="flex items-center gap-2 font-black text-lg text-slate-900 tracking-tight">
          <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-rose-500 to-pink-500 text-white flex items-center justify-center font-black shadow-sm">
            <?= strtoupper(substr($tenant['panel_name'], 0, 1)) ?>
          </div>
          <span><?= e($tenant['panel_name']) ?></span>
        </a>
      </div>

      <nav class="hidden md:flex items-center gap-5 text-xs font-bold text-slate-600">
        <a href="?action=services<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="hover:text-rose-600 transition-colors">Services</a>
        <?php if ($cpUser): ?>
          <a href="?action=order<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="hover:text-rose-600 transition-colors">New Order</a>
          <a href="?action=orders<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="hover:text-rose-600 transition-colors">Order History</a>
        <?php endif; ?>
      </nav>

      <div class="flex items-center gap-3">
        <!-- Live Currency Selector -->
        <div class="relative" id="cp-currency-container">
          <button type="button" onclick="document.getElementById('cp-currency-dropdown').classList.toggle('hidden')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors bg-white shadow-xs">
            <span><?= $userCurrency === 'INR' ? '🇮🇳' : ($userCurrency === 'USD' ? '🇺🇸' : ($userCurrency === 'EUR' ? '🇪🇺' : '🇬🇧')) ?></span>
            <span><?= e($userCurrency) ?></span>
            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
          </button>
          <div id="cp-currency-dropdown" class="hidden absolute right-0 mt-2 w-36 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-50">
            <?php foreach ($currencies as $c): ?>
              <button type="button" onclick="switchChildPanelCurrency('<?= e($c['code']) ?>')" class="w-full text-left px-3.5 py-2 text-xs flex items-center justify-between hover:bg-rose-50 transition-colors <?= $userCurrency === $c['code'] ? 'text-rose-600 font-bold bg-rose-50/70' : 'text-slate-700' ?>">
                <span><?= e($c['name']) ?></span>
                <span class="font-mono text-[11px] text-slate-400"><?= e($c['symbol']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($cpUser): ?>
          <div class="px-3.5 py-1.5 rounded-full bg-rose-50 border border-rose-200 text-xs font-bold text-slate-700">
            Balance: <span class="text-rose-600 font-black"><?= $currSymbol ?><?= number_format($cpUser['balance'], 2) ?></span>
          </div>
          <span class="text-xs font-bold text-slate-700 hidden sm:inline"><?= e($cpUser['username']) ?></span>
          <a href="?action=logout<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-500 hover:text-rose-600 hover:bg-slate-100 transition-colors">
            Logout
          </a>
        <?php else: ?>
          <button onclick="document.getElementById('login-modal').classList.remove('hidden')" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 transition-colors">
            Login
          </button>
          <button onclick="document.getElementById('register-modal').classList.remove('hidden')" class="px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold shadow-sm transition-colors">
            Sign Up
          </button>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- MAIN CONTAINER -->
  <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex-1 w-full space-y-6">

    <?php if ($msg): ?>
      <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-bold flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
        <span><?= e($msg) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm font-bold flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
        <span><?= e($err) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!$cpUser && $action === 'home'): ?>
      <!-- HERO LANDING -->
      <div class="py-12 sm:py-16 text-center max-w-3xl mx-auto space-y-6">
        <span class="px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-100 text-rose-700 border border-rose-200">
          Fast & High Quality SMM Services
        </span>
        <h1 class="text-3xl sm:text-5xl font-black text-slate-900 tracking-tight leading-tight">
          Supercharge Your Social Presence with <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-500 to-pink-600"><?= e($tenant['panel_name']) ?></span>
        </h1>
        <p class="text-sm sm:text-base text-slate-500 max-w-xl mx-auto leading-relaxed">
          The premier platform for social media marketing, followers, likes, views, and instant automated engagement.
        </p>
        <div class="flex items-center justify-center gap-3 pt-2">
          <button onclick="document.getElementById('register-modal').classList.remove('hidden')" class="px-6 py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-black text-sm shadow-md shadow-rose-500/20 transition-all flex items-center gap-2">
            <span>Get Started</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
          <a href="?action=services<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="px-6 py-3 rounded-2xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-800 font-bold text-sm shadow-xs transition-colors">
            View Price List
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- SERVICES & NEW ORDER PANEL -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- New Order Card -->
      <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-base font-black text-slate-800 tracking-tight mb-4 flex items-center gap-2">
          <i data-lucide="shopping-cart" class="w-4 h-4 text-rose-500"></i>
          <span>Place New Order</span>
        </h2>

        <?php if (!$cpUser): ?>
          <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200 text-center space-y-3">
            <p class="text-xs text-slate-500">Please create an account or log in to submit automated orders.</p>
            <button onclick="document.getElementById('login-modal').classList.remove('hidden')" class="w-full py-2.5 rounded-xl bg-rose-500 text-white font-bold text-xs">
              Log In to Order
            </button>
          </div>
        <?php else: ?>
          <form method="POST" action="?action=place_order<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="space-y-4">
            <input type="hidden" name="action" value="place_order">

            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Select Service</label>
              <select name="service_id" id="service-select" onchange="updatePrice()" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold outline-none bg-white">
                <optgroup label="Main Services">
                  <?php foreach ($parentServices as $ps): ?>
                    <?php 
                      $baseUSD = convert_price((float)$ps['rate'], $ps['currency'] ?? 'USD', 'USD');
                      $marginPct = (float)$tenant['price_margin_percent'];
                      $retailUSD = $baseUSD * (1 + ($marginPct / 100));
                      $convertedRetail = convert_price($retailUSD, 'USD', $userCurrency);
                      $formattedRetail = format_price($retailUSD, $userCurrency, 'USD');
                    ?>
                    <option 
                      value="<?= $ps['id'] ?>" 
                      data-source="parent" 
                      data-rate="<?= $convertedRetail ?>"
                      data-symbol="<?= e($currSymbol) ?>"
                    >
                      <?= e($ps['name']) ?> - <?= $formattedRetail ?> / 1k
                    </option>
                  <?php endforeach; ?>
                </optgroup>

                <?php if (!empty($importedServices)): ?>
                  <optgroup label="Exclusive Imported Services">
                    <?php foreach ($importedServices as $is): ?>
                      <?php
                        $extUSD = (float)$is['selling_rate'];
                        $convertedExt = convert_price($extUSD, 'USD', $userCurrency);
                        $formattedExt = format_price($extUSD, $userCurrency, 'USD');
                      ?>
                      <option 
                        value="<?= $is['id'] ?>" 
                        data-source="external" 
                        data-rate="<?= $convertedExt ?>"
                        data-symbol="<?= e($currSymbol) ?>"
                      >
                        <?= e($is['name']) ?> - <?= $formattedExt ?> / 1k
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
              <input type="hidden" name="source_type" id="source-type" value="parent">
            </div>

            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Target Link / URL</label>
              <input type="url" name="link" required placeholder="https://instagram.com/p/..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs outline-none">
            </div>

            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Quantity</label>
              <input type="number" name="quantity" id="order-qty" value="1000" min="10" step="10" oninput="updatePrice()" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold outline-none">
            </div>

            <div class="p-3.5 rounded-2xl bg-rose-50/70 border border-rose-100 flex items-center justify-between text-xs">
              <span class="text-slate-500 font-medium">Estimated Charge:</span>
              <span class="font-mono font-black text-rose-600 text-base" id="calc-charge"><?= e($currSymbol) ?>0.00</span>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-xs transition-colors flex items-center justify-center gap-1.5">
              <i data-lucide="check" class="w-4 h-4"></i>
              <span>Submit Order</span>
            </button>
          </form>

          <script>
          function updatePrice() {
            const sel = document.getElementById('service-select');
            if (!sel || !sel.options[sel.selectedIndex]) return;
            const opt = sel.options[sel.selectedIndex];
            const rate = parseFloat(opt.getAttribute('data-rate')) || 0;
            const symbol = opt.getAttribute('data-symbol') || <?= json_encode($currSymbol) ?>;
            const src = opt.getAttribute('data-source') || 'parent';
            const srcInput = document.getElementById('source-type');
            if (srcInput) srcInput.value = src;
            const qtyInput = document.getElementById('order-qty');
            const qty = parseInt(qtyInput ? qtyInput.value : 0) || 0;
            const total = (rate / 1000) * qty;
            const calcEl = document.getElementById('calc-charge');
            if (calcEl) {
              calcEl.innerText = symbol + total.toFixed(2);
            }
          }
          document.addEventListener('DOMContentLoaded', updatePrice);
          </script>
        <?php endif; ?>
      </div>

      <!-- Services & Orders List -->
      <div class="lg:col-span-2 space-y-6">
        
        <?php if ($cpUser && !empty($myOrders)): ?>
          <!-- Recent Orders Card -->
          <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm">
            <h3 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2">
              <i data-lucide="clipboard-list" class="w-4 h-4 text-rose-500"></i>
              <span>Your Recent Orders</span>
            </h3>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold">
                    <th class="pb-2">ID</th>
                    <th class="pb-2">Qty</th>
                    <th class="pb-2">Charge</th>
                    <th class="pb-2">Status</th>
                    <th class="pb-2">Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($myOrders as $o): ?>
                    <tr>
                      <td class="py-2.5 font-mono font-bold text-slate-600">#<?= $o['id'] ?></td>
                      <td class="py-2.5 font-bold"><?= number_format($o['quantity']) ?></td>
                      <td class="py-2.5 font-mono font-bold text-slate-800"><?= $currSymbol ?><?= number_format($o['charge'], 2) ?></td>
                      <td class="py-2.5">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                          <?= ucfirst($o['status']) ?>
                        </span>
                      </td>
                      <td class="py-2.5 text-slate-400"><?= date('M d, H:i', strtotime($o['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- Services Price Catalog -->
        <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-black text-slate-800 tracking-tight flex items-center gap-2">
              <i data-lucide="layers" class="w-4 h-4 text-rose-500"></i>
              <span>Catalog Services (<?= count($parentServices) + count($importedServices) ?>)</span>
            </h3>
            <span class="text-xs text-slate-400 font-medium">Instant automated fulfillment (<?= e($userCurrency) ?>)</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-wider">
                  <th class="pb-2.5">Service</th>
                  <th class="pb-2.5">Category</th>
                  <th class="pb-2.5">Rate / 1k</th>
                  <th class="pb-2.5">Min / Max</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <?php foreach ($parentServices as $ps): ?>
                  <?php 
                    $baseUSD = convert_price((float)$ps['rate'], $ps['currency'] ?? 'USD', 'USD');
                    $marginPct = (float)$tenant['price_margin_percent'];
                    $retailUSD = $baseUSD * (1 + ($marginPct / 100));
                  ?>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-2.5 font-bold text-slate-800 max-w-xs truncate"><?= e($ps['name']) ?></td>
                    <td class="py-2.5 text-slate-500"><?= e($ps['category_name']) ?></td>
                    <td class="py-2.5 font-mono font-bold text-rose-600"><?= format_price($retailUSD, $userCurrency, 'USD') ?></td>
                    <td class="py-2.5 font-mono text-slate-400"><?= $ps['min_quantity'] ?> - <?= number_format($ps['max_quantity']) ?></td>
                  </tr>
                <?php endforeach; ?>

                <?php foreach ($importedServices as $is): ?>
                  <?php
                    $extUSD = (float)$is['selling_rate'];
                  ?>
                  <tr class="hover:bg-slate-50 transition-colors bg-purple-50/20">
                    <td class="py-2.5 font-bold text-slate-800 max-w-xs truncate">
                      <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 font-bold uppercase mr-1">Pro</span>
                      <?= e($is['name']) ?>
                    </td>
                    <td class="py-2.5 text-slate-500"><?= e($is['category_name']) ?></td>
                    <td class="py-2.5 font-mono font-bold text-purple-700"><?= format_price($extUSD, $userCurrency, 'USD') ?></td>
                    <td class="py-2.5 font-mono text-slate-400"><?= $is['min_quantity'] ?> - <?= number_format($is['max_quantity']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

    </div>

  </main>

  <!-- FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center text-xs text-slate-400">
    <div class="max-w-6xl mx-auto px-4">
      <p>© <?= date('Y') ?> <?= e($tenant['panel_name']) ?>. All rights reserved.</p>
      <?php if (!empty($tenant['support_email'])): ?>
        <p class="mt-1">Support: <a href="mailto:<?= e($tenant['support_email']) ?>" class="text-rose-500 font-bold"><?= e($tenant['support_email']) ?></a></p>
      <?php endif; ?>
    </div>
  </footer>

  <!-- AUTH MODALS -->
  <div id="login-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-sm w-full shadow-xl">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
        <h3 class="text-base font-black text-slate-800">Login to <?= e($tenant['panel_name']) ?></h3>
        <button onclick="document.getElementById('login-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <form method="POST" action="?action=login<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="space-y-3 text-xs">
        <input type="hidden" name="action" value="login">
        <div>
          <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Username or Email</label>
          <input type="text" name="username" required class="w-full px-3 py-2 rounded-xl border border-slate-200 outline-none focus:border-rose-500">
        </div>
        <div>
          <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Password</label>
          <input type="password" name="password" required class="w-full px-3 py-2 rounded-xl border border-slate-200 outline-none focus:border-rose-500">
        </div>
        <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold transition-colors">
          Sign In
        </button>
      </form>
    </div>
  </div>

  <div id="register-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-sm w-full shadow-xl">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
        <h3 class="text-base font-black text-slate-800">Register on <?= e($tenant['panel_name']) ?></h3>
        <button onclick="document.getElementById('register-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <form method="POST" action="?action=register<?= !empty($_GET['child_panel']) ? '&child_panel=' . (int)$_GET['child_panel'] : '' ?>" class="space-y-3 text-xs">
        <input type="hidden" name="action" value="register">
        <div>
          <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Choose Username</label>
          <input type="text" name="username" required class="w-full px-3 py-2 rounded-xl border border-slate-200 outline-none focus:border-rose-500">
        </div>
        <div>
          <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Email Address</label>
          <input type="email" name="email" required class="w-full px-3 py-2 rounded-xl border border-slate-200 outline-none focus:border-rose-500">
        </div>
        <div>
          <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Password</label>
          <input type="password" name="password" required minlength="6" class="w-full px-3 py-2 rounded-xl border border-slate-200 outline-none focus:border-rose-500">
        </div>
        <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold transition-colors">
          Create Account
        </button>
      </form>
    </div>
  </div>

  <script>
    lucide.createIcons();

    function switchChildPanelCurrency(curr) {
      fetch('/api/currency/switch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ currency: curr })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.location.reload();
        }
      })
      .catch(err => console.error(err));
    }

    // Close currency dropdown when clicked outside
    document.addEventListener('click', function(e) {
      const container = document.getElementById('cp-currency-container');
      const dropdown = document.getElementById('cp-currency-dropdown');
      if (container && dropdown && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
      }
    });
  </script>
</body>
</html>
