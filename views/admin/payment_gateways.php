<?php
require_once __DIR__ . '/../../config/database.php';

// Verify admin authentication
if (!is_admin()) {
    header("Location: /admin/login");
    exit;
}

$db = getDB();
$msg = '';
$err = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'toggle_status') {
            $gwId = (int)($_POST['gateway_id'] ?? 0);
            $currStatus = $_POST['status'] ?? 'inactive';
            $newStatus = $currStatus === 'active' ? 'inactive' : 'active';
            
            $db->prepare("UPDATE payment_gateways SET status = ? WHERE id = ?")->execute([$newStatus, $gwId]);
            $msg = "Payment gateway status updated to " . strtoupper($newStatus) . " in MySQL.";
        } elseif ($action === 'update_gateway') {
            $gwId = (int)($_POST['gateway_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $mode = in_array($_POST['mode'] ?? '', ['test', 'live']) ? $_POST['mode'] : 'test';
            $apiKey = trim($_POST['api_key'] ?? '');
            $secretKey = trim($_POST['secret_key'] ?? '');
            $webhookSecret = trim($_POST['webhook_secret'] ?? '');
            $merchantId = trim($_POST['merchant_id'] ?? '');
            $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $minAmount = max(0.5, (float)($_POST['min_amount'] ?? 5.0));
            $maxAmount = max($minAmount, (float)($_POST['max_amount'] ?? 5000.0));
            $feePercent = max(0, (float)($_POST['fee_percent'] ?? 0));
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
            $parameters = trim($_POST['parameters'] ?? '');

            if (empty($name)) {
                throw new Exception("Gateway name is required.");
            }

            // If secret key is left as masked '••••••••' and not changed, keep existing secret
            if ($secretKey === '••••••••' || empty($secretKey)) {
                $curSecret = $db->prepare("SELECT secret_key FROM payment_gateways WHERE id = ?");
                $curSecret->execute([$gwId]);
                $secretKey = $curSecret->fetchColumn() ?: '';
            }

            $stmt = $db->prepare("
                UPDATE payment_gateways 
                SET name = ?, mode = ?, api_key = ?, secret_key = ?, webhook_secret = ?, merchant_id = ?, currency = ?, min_amount = ?, max_amount = ?, fee_percent = ?, description = ?, parameters = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $mode, $apiKey, $secretKey, $webhookSecret, $merchantId, $currency, $minAmount, $maxAmount, $feePercent, $desc, $parameters, $status, $gwId]);
            $msg = "Gateway \"$name\" configuration saved successfully to MySQL.";
        } elseif ($action === 'create_gateway') {
            $code = strtolower(trim(preg_replace('/[^a-z0-9_]+/', '', $_POST['code'] ?? '')));
            $name = trim($_POST['name'] ?? '');
            $mode = in_array($_POST['mode'] ?? '', ['test', 'live']) ? $_POST['mode'] : 'test';
            $apiKey = trim($_POST['api_key'] ?? '');
            $secretKey = trim($_POST['secret_key'] ?? '');
            $webhookSecret = trim($_POST['webhook_secret'] ?? '');
            $merchantId = trim($_POST['merchant_id'] ?? '');
            $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $minAmount = max(0.5, (float)($_POST['min_amount'] ?? 5.0));
            $maxAmount = max($minAmount, (float)($_POST['max_amount'] ?? 5000.0));
            $feePercent = max(0, (float)($_POST['fee_percent'] ?? 0));
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'inactive';
            $parameters = trim($_POST['parameters'] ?? '');

            if (empty($code) || empty($name)) {
                throw new Exception("Gateway code and name are required.");
            }

            $stmt = $db->prepare("
                INSERT INTO payment_gateways (code, name, mode, api_key, secret_key, webhook_secret, merchant_id, currency, min_amount, max_amount, fee_percent, description, parameters, status, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 99)
            ");
            $stmt->execute([$code, $name, $mode, $apiKey, $secretKey, $webhookSecret, $merchantId, $currency, $minAmount, $maxAmount, $feePercent, $desc, $parameters, $status]);
            $msg = "New payment gateway \"$name\" successfully added to MySQL.";
        } elseif ($action === 'delete_gateway') {
            $gwId = (int)($_POST['gateway_id'] ?? 0);
            if ($gwId > 0) {
                $db->prepare("DELETE FROM payment_gateways WHERE id = ?")->execute([$gwId]);
                $msg = "Payment gateway permanently removed from MySQL.";
            }
        }
    } catch (Throwable $e) {
        $err = "Gateway error: " . $e->getMessage();
    }
}

// Fetch all gateways
$gateways = $db->query("SELECT * FROM payment_gateways ORDER BY sort_order ASC, id ASC")->fetchAll();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:3000';
$baseUrl = $protocol . $host;

$pageTitle = 'Payment Gateways - Admin Console';
$adminPage = 'payment-gateways';
require_once __DIR__ . '/../layouts/admin_header.php';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Payment Gateways Management</h1>
    <p class="text-xs text-slate-500 mt-1">Configure credentials, webhook endpoints, deposit thresholds, and enable/disable payment methods.</p>
  </div>
  <div class="flex items-center gap-2">
    <a href="/admin/transactions" class="px-4 py-2.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5">
      <i data-lucide="receipt" class="w-4 h-4 text-emerald-600"></i>
      <span>Payment Ledger</span>
    </a>
    <button onclick="document.getElementById('new-gateway-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
      <i data-lucide="plus-circle" class="w-4 h-4"></i>
      <span>Add Gateway</span>
    </button>
  </div>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 shrink-0 text-emerald-600"></i>
      <span><?= e($msg) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold text-sm">✕</button>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-600"></i>
      <span><?= e($err) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold text-sm">✕</button>
  </div>
<?php endif; ?>

<!-- Gateways Grid (Card-based UI, NO HTML TABLE) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
  <?php foreach ($gateways as $gw): 
    $isActive = $gw['status'] === 'active';
    $isTest = $gw['mode'] === 'test';
    $webhookUrl = $baseUrl . "/payment/webhook?gateway=" . urlencode($gw['code']);
  ?>
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between relative overflow-hidden">
      <div>
        <!-- Top bar: Logo, Name & Badges -->
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-sm <?= $isActive ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-slate-100 text-slate-400' ?>">
              <?php if ($gw['code'] === 'stripe'): ?>
                <i data-lucide="credit-card" class="w-6 h-6"></i>
              <?php elseif ($gw['code'] === 'paypal'): ?>
                <i data-lucide="wallet" class="w-6 h-6"></i>
              <?php elseif ($gw['code'] === 'razorpay'): ?>
                <i data-lucide="zap" class="w-6 h-6"></i>
              <?php elseif ($gw['code'] === 'bank_transfer'): ?>
                <i data-lucide="building" class="w-6 h-6"></i>
              <?php else: ?>
                <i data-lucide="coins" class="w-6 h-6"></i>
              <?php endif; ?>
            </div>
            <div>
              <h3 class="font-extrabold text-sm text-slate-900 leading-snug"><?= e($gw['name']) ?></h3>
              <div class="flex items-center gap-1.5 mt-0.5">
                <span class="font-mono text-[10px] text-slate-400 uppercase font-bold tracking-wider"><?= e($gw['code']) ?></span>
                <span class="text-slate-300">•</span>
                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded <?= $isTest ? 'bg-amber-50 text-amber-600 border border-amber-200' : 'bg-emerald-50 text-emerald-600 border border-emerald-200' ?>">
                  <?= strtoupper($gw['mode']) ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Status Indicator Pill -->
          <span class="px-2.5 py-1 rounded-full text-[11px] font-bold shrink-0 <?= $isActive ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-400' ?>">
            <?= $isActive ? 'ENABLED (ON)' : 'DISABLED (OFF)' ?>
          </span>
        </div>

        <p class="text-xs text-slate-500 mb-4 line-clamp-2 leading-relaxed">
          <?= e($gw['description'] ?: 'Accept instant digital deposits directly into customer wallets.') ?>
        </p>

        <!-- Gateway Details Grid -->
        <div class="grid grid-cols-3 gap-2 py-3 px-3 bg-slate-50 rounded-2xl text-xs mb-4">
          <div>
            <span class="text-slate-400 block text-[10px] font-semibold">Min Deposit:</span>
            <span class="font-bold text-slate-800 text-xs"><?= e($gw['currency']) ?> <?= number_format($gw['min_amount'], 2) ?></span>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] font-semibold">Max Deposit:</span>
            <span class="font-bold text-slate-800 text-xs"><?= e($gw['currency']) ?> <?= number_format($gw['max_amount'], 2) ?></span>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] font-semibold">Gateway Fee:</span>
            <span class="font-bold text-slate-800 text-xs"><?= number_format($gw['fee_percent'], 1) ?>%</span>
          </div>
        </div>

        <!-- Masked Credentials Status -->
        <div class="space-y-1.5 text-[11px] text-slate-500 mb-4">
          <div class="flex items-center justify-between">
            <span class="text-slate-400">API Key / Client ID:</span>
            <span class="font-mono text-slate-700 font-semibold truncate max-w-[160px]">
              <?= !empty($gw['api_key']) ? (substr($gw['api_key'], 0, 8) . '••••') : '<span class="text-amber-500">Not configured</span>' ?>
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Secret Key:</span>
            <span class="font-mono text-slate-700 font-semibold">
              <?= !empty($gw['secret_key']) ? '••••••••' : '<span class="text-amber-500">Not configured</span>' ?>
            </span>
          </div>
        </div>

        <!-- Webhook Helper Box -->
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-[11px] text-slate-500 mb-4">
          <span class="text-[10px] font-bold text-slate-400 block mb-1">Webhook URL (For Gateway Portal):</span>
          <div class="flex items-center justify-between gap-1 font-mono text-[10px] text-slate-700 bg-white px-2 py-1 rounded border border-slate-200">
            <span class="truncate"><?= e($webhookUrl) ?></span>
            <button type="button" onclick="navigator.clipboard.writeText('<?= $webhookUrl ?>'); alert('Webhook URL copied to clipboard!');" class="text-rose-500 hover:text-rose-600 font-bold shrink-0">
              Copy
            </button>
          </div>
        </div>
      </div>

      <!-- Action Controls -->
      <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
        <!-- ON / OFF Toggle Form -->
        <form method="POST">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="gateway_id" value="<?= $gw['id'] ?>">
          <input type="hidden" name="status" value="<?= $gw['status'] ?>">
          <button 
            type="submit" 
            class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 <?= $isActive ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm' : 'bg-slate-200 hover:bg-slate-300 text-slate-700' ?>"
            title="<?= $isActive ? 'Turn Gateway OFF' : 'Turn Gateway ON' ?>"
          >
            <i data-lucide="<?= $isActive ? 'power' : 'power-off' ?>" class="w-3.5 h-3.5"></i>
            <span><?= $isActive ? 'Turn OFF' : 'Turn ON' ?></span>
          </button>
        </form>

        <div class="flex items-center gap-1.5">
          <!-- Edit Gateway Button -->
          <button 
            type="button" 
            onclick='openEditGatewayModal(<?= json_encode([
              "id" => $gw["id"],
              "code" => $gw["code"],
              "name" => $gw["name"],
              "mode" => $gw["mode"],
              "api_key" => $gw["api_key"] ?? "",
              "secret_key" => !empty($gw["secret_key"]) ? "••••••••" : "",
              "webhook_secret" => $gw["webhook_secret"] ?? "",
              "merchant_id" => $gw["merchant_id"] ?? "",
              "currency" => $gw["currency"] ?? "USD",
              "min_amount" => (float)$gw["min_amount"],
              "max_amount" => (float)$gw["max_amount"],
              "fee_percent" => (float)$gw["fee_percent"],
              "description" => $gw["description"] ?? "",
              "parameters" => $gw["parameters"] ?? "",
              "status" => $gw["status"]
            ]) ?>)' 
            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1 transition-colors"
          >
            <i data-lucide="settings-2" class="w-3.5 h-3.5"></i>
            <span>Edit Credentials</span>
          </button>

          <!-- Delete Custom Gateway -->
          <?php if (!in_array($gw['code'], ['stripe', 'paypal'])): ?>
            <form method="POST" onsubmit="return confirm('Permanently delete this payment gateway configuration from MySQL?');">
              <input type="hidden" name="action" value="delete_gateway">
              <input type="hidden" name="gateway_id" value="<?= $gw['id'] ?>">
              <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" title="Delete Gateway">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Edit Gateway Modal -->
<div id="edit-gateway-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 border border-slate-200 shadow-2xl relative max-h-[90vh] overflow-y-auto">
    <button onclick="document.getElementById('edit-gateway-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <div class="flex items-center gap-2 mb-1">
      <span id="edit-gw-badge" class="px-2 py-0.5 rounded bg-rose-50 text-rose-600 font-mono text-[10px] font-bold uppercase"></span>
      <h2 class="text-lg font-bold text-slate-800">Edit Gateway Credentials</h2>
    </div>
    <p class="text-xs text-slate-400 mb-5">Configure real gateway API keys and processing parameters.</p>

    <form method="POST" id="edit-gateway-form" class="space-y-4">
      <input type="hidden" name="action" value="update_gateway">
      <input type="hidden" name="gateway_id" id="edit-gw-id">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Display Name</label>
          <input type="text" name="name" id="edit-gw-name" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Environment Mode</label>
          <select name="mode" id="edit-gw-mode" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
            <option value="test">Test / Sandbox</option>
            <option value="live">Live Production</option>
          </select>
        </div>
      </div>

      <!-- Credentials Section Dynamically Labeled -->
      <div id="field-api-key">
        <label id="lbl-api-key" class="block text-xs font-bold text-slate-700 mb-1">API Key / Publishable Key / Client ID</label>
        <input type="text" name="api_key" id="edit-gw-api-key" placeholder="pk_test_... or Client ID" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
      </div>

      <div id="field-secret-key">
        <label id="lbl-secret-key" class="block text-xs font-bold text-slate-700 mb-1">Secret Key / Client Secret</label>
        <input type="password" name="secret_key" id="edit-gw-secret-key" placeholder="•••••••• (leave unchanged to keep current)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
      </div>

      <div class="grid grid-cols-2 gap-3" id="row-merchant-webhook">
        <div id="field-merchant-id">
          <label id="lbl-merchant-id" class="block text-xs font-bold text-slate-700 mb-1">Merchant ID (Optional)</label>
          <input type="text" name="merchant_id" id="edit-gw-merchant-id" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
        </div>
        <div id="field-webhook-secret">
          <label id="lbl-webhook-secret" class="block text-xs font-bold text-slate-700 mb-1">Webhook Secret / ID</label>
          <input type="text" name="webhook_secret" id="edit-gw-webhook-secret" placeholder="whsec_..." class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
        </div>
      </div>

      <!-- Limits & Currency -->
      <div class="grid grid-cols-4 gap-2">
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Currency</label>
          <input type="text" name="currency" id="edit-gw-currency" required class="w-full px-2 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Min ($)</label>
          <input type="number" step="0.5" name="min_amount" id="edit-gw-min" required class="w-full px-2 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Max ($)</label>
          <input type="number" step="10" name="max_amount" id="edit-gw-max" required class="w-full px-2 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Fee (%)</label>
          <input type="number" step="0.1" name="fee_percent" id="edit-gw-fee" class="w-full px-2 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Description / Customer Guidance</label>
        <textarea name="description" id="edit-gw-desc" rows="2" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
      </div>

      <div id="field-parameters">
        <label class="block text-xs font-bold text-slate-700 mb-1">Bank / Manual Details or Extra Config</label>
        <textarea name="parameters" id="edit-gw-parameters" rows="2" placeholder="e.g. Bank: Chase | Account: 123456789 | Routing: 987654321" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono"></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Visibility Status</label>
        <select name="status" id="edit-gw-status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
          <option value="active">Active (ON - Visible on User Deposit Page)</option>
          <option value="inactive">Inactive (OFF - Hidden from Users)</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('edit-gateway-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Save Configuration to MySQL</button>
      </div>
    </form>
  </div>
</div>

<!-- Add New Gateway Modal -->
<div id="new-gateway-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 border border-slate-200 shadow-2xl relative max-h-[90vh] overflow-y-auto">
    <button onclick="document.getElementById('new-gateway-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-1">Add Payment Gateway</h2>
    <p class="text-xs text-slate-400 mb-5">Register a new payment provider in your MySQL system.</p>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create_gateway">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Gateway Identifier Code</label>
          <input type="text" name="code" required placeholder="e.g. coinbase, payeer" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Display Name</label>
          <input type="text" name="name" required placeholder="e.g. Coinbase Commerce" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">API Key / Client ID</label>
          <input type="text" name="api_key" placeholder="API Key" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Secret Key / Secret</label>
          <input type="password" name="secret_key" placeholder="Secret Key" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
        </div>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Currency</label>
          <input type="text" name="currency" value="USD" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Min Deposit</label>
          <input type="number" step="0.5" name="min_amount" value="5.00" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Max Deposit</label>
          <input type="number" step="10" name="max_amount" value="5000.00" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Customer Description</label>
        <textarea name="description" rows="2" placeholder="Instructions shown on payment selection..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Initial Status</label>
        <select name="status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
          <option value="inactive" selected>Inactive (OFF - Hidden until verified)</option>
          <option value="active">Active (ON - Immediate user availability)</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('new-gateway-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Add to MySQL</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditGatewayModal(gw) {
  document.getElementById('edit-gw-id').value = gw.id;
  document.getElementById('edit-gw-badge').textContent = gw.code;
  document.getElementById('edit-gw-name').value = gw.name;
  document.getElementById('edit-gw-mode').value = gw.mode;
  document.getElementById('edit-gw-api-key').value = gw.api_key || '';
  document.getElementById('edit-gw-secret-key').value = gw.secret_key || '';
  document.getElementById('edit-gw-webhook-secret').value = gw.webhook_secret || '';
  document.getElementById('edit-gw-merchant-id').value = gw.merchant_id || '';
  document.getElementById('edit-gw-currency').value = gw.currency || 'USD';
  document.getElementById('edit-gw-min').value = gw.min_amount;
  document.getElementById('edit-gw-max').value = gw.max_amount;
  document.getElementById('edit-gw-fee').value = gw.fee_percent;
  document.getElementById('edit-gw-desc').value = gw.description || '';
  document.getElementById('edit-gw-parameters').value = gw.parameters || '';
  document.getElementById('edit-gw-status').value = gw.status;

  // Tailor labels to gateway code
  const lblApi = document.getElementById('lbl-api-key');
  const lblSecret = document.getElementById('lbl-secret-key');

  if (gw.code === 'stripe') {
    lblApi.textContent = 'Stripe Publishable Key (pk_test_... or pk_live_...)';
    lblSecret.textContent = 'Stripe Secret Key (sk_test_... or sk_live_...)';
  } else if (gw.code === 'paypal') {
    lblApi.textContent = 'PayPal REST Client ID';
    lblSecret.textContent = 'PayPal REST Secret';
  } else if (gw.code === 'razorpay') {
    lblApi.textContent = 'Razorpay Key ID (rzp_test_... or rzp_live_...)';
    lblSecret.textContent = 'Razorpay Key Secret';
  } else {
    lblApi.textContent = 'API Key / Merchant ID';
    lblSecret.textContent = 'API Secret Key';
  }

  document.getElementById('edit-gateway-modal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
