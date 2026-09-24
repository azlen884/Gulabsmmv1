<?php
$pageTitle = 'API Providers - Admin Console';
$adminPage = 'providers';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';
$err = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_provider') {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['api_url'] ?? '');
        $key = trim($_POST['api_key'] ?? '');
        $currency = !empty($_POST['currency']) ? strtoupper(trim($_POST['currency'])) : 'USD';

        if (!empty($name) && !empty($url) && !empty($key)) {
            $stmt = $db->prepare("INSERT INTO providers (name, api_url, api_key, currency, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $url, $key, $currency]);
            $msg = "Provider API added successfully.";
        } else {
            $err = "Please fill in all required provider fields.";
        }
    } elseif ($action === 'check_balance') {
        $providerId = (int)$_POST['provider_id'];
        $pStmt = $db->prepare("SELECT * FROM providers WHERE id = ?");
        $pStmt->execute([$providerId]);
        $provider = $pStmt->fetch();

        if ($provider) {
            $response = null;
            $httpCode = 200;
            $urlParts = parse_url($provider['api_url']);
            $isLocal = in_array($urlParts['host'] ?? '', ['127.0.0.1', 'localhost']) || (($urlParts['host'] ?? '') === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));

            if ($isLocal && ($urlParts['path'] ?? '') === '/api/v2') {
                // Internal lookup for local API endpoint to prevent single-threaded server deadlock
                $uStmt = $db->prepare("SELECT balance, currency FROM users WHERE api_key = ?");
                $uStmt->execute([$provider['api_key']]);
                $apiUser = $uStmt->fetch();
                if ($apiUser) {
                    $response = json_encode([
                        'balance' => number_format((float)$apiUser['balance'], 4, '.', ''),
                        'currency' => $apiUser['currency'] ?: 'USD'
                    ]);
                } else {
                    $response = json_encode(['error' => 'Invalid API key']);
                }
            } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'key' => $provider['api_key'],
                    'action' => 'balance'
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }

            $data = json_decode($response, true);
            if ($data && isset($data['balance'])) {
                $newBal = (float)$data['balance'];
                // Detect provider currency if present in API response, otherwise preserve configured provider currency
                $provCurr = !empty($data['currency']) ? strtoupper(trim($data['currency'])) : (!empty($provider['currency']) ? strtoupper(trim($provider['currency'])) : 'USD');
                $db->prepare("UPDATE providers SET balance = ?, currency = ? WHERE id = ?")->execute([$newBal, $provCurr, $providerId]);

                $panelCurrency = get_user_currency();
                $convertedBal = format_price($newBal, $panelCurrency, $provCurr);
                $msg = "Provider balance updated: {$convertedBal} (Upstream provider balance: {$provCurr} " . number_format($newBal, 2) . ")";
            } else {
                $err = "API Response: " . ($response ?: "HTTP $httpCode connection failed");
            }
        }
    } elseif ($action === 'toggle_status') {
        $providerId = (int)$_POST['provider_id'];
        $curr = $_POST['status'];
        $next = $curr === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE providers SET status = ? WHERE id = ?")->execute([$next, $providerId]);
        $msg = "Provider status set to $next.";
    }
}

$providers = $db->query("SELECT * FROM providers ORDER BY id DESC")->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">API Providers</h1>
    <p class="text-xs text-slate-500 mt-1">Connect automated upstream SMM providers to route and fulfill orders via API.</p>
  </div>
  <button onclick="document.getElementById('new-provider-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2 cursor-pointer">
    <i data-lucide="plus-circle" class="w-4 h-4"></i>
    <span>Add New Provider</span>
  </button>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="mb-4 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    <span><?= e($err) ?></span>
  </div>
<?php endif; ?>

<!-- Provider Card List -->
<?php if (empty($providers)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <p class="text-xs text-slate-400">No providers configured yet. Click "Add New Provider" above.</p>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($providers as $p): ?>
      <?php
        $panelCurrency = get_user_currency();
        $provCurr = !empty($p['currency']) ? strtoupper(trim($p['currency'])) : 'USD';
        $convertedBalance = format_price($p['balance'], $panelCurrency, $provCurr);
      ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-base text-slate-800"><?= e($p['name']) ?></h3>
            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $p['status'] === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' ?>">
              <?= ucfirst($p['status']) ?>
            </span>
          </div>

          <div class="space-y-2 mb-4 text-xs">
            <div>
              <span class="text-slate-400 block text-[11px]">API URL:</span>
              <span class="font-mono text-slate-700 break-all"><?= e($p['api_url']) ?></span>
            </div>
            <div>
              <span class="text-slate-400 block text-[11px]">API Key:</span>
              <span class="font-mono text-slate-500 select-all">••••••••<?= substr($p['api_key'], -6) ?></span>
            </div>
          </div>

          <!-- Provider Balance with Automatic Currency Conversion -->
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between mb-4">
            <div>
              <div class="flex items-center gap-1.5 mb-1">
                <span class="text-[10px] text-slate-400 font-bold uppercase">Provider Balance</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-700 font-mono"><?= e($panelCurrency) ?></span>
              </div>
              <div class="text-xl font-black text-slate-900"><?= $convertedBalance ?></div>
              <div class="text-[11px] text-slate-400 font-medium mt-0.5">
                Upstream: <span class="font-mono font-semibold text-slate-600"><?= e($provCurr) ?> <?= number_format($p['balance'], 2) ?></span>
              </div>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="check_balance">
              <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
              <button type="submit" class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                <span>Sync Balance</span>
              </button>
            </form>
          </div>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
          <a href="/admin/provider-services?provider=<?= $p['id'] ?>" class="text-rose-600 font-bold hover:underline flex items-center gap-1">
            <i data-lucide="download" class="w-3.5 h-3.5"></i>
            <span>Import Services</span>
          </a>

          <form method="POST">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
            <input type="hidden" name="status" value="<?= $p['status'] ?>">
            <button type="submit" class="text-slate-400 hover:text-slate-600 text-xs font-semibold cursor-pointer">
              <?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Create Provider Modal -->
<div id="new-provider-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 border border-slate-200 shadow-2xl relative" onclick="event.stopPropagation()">
    <button onclick="document.getElementById('new-provider-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 cursor-pointer">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-1">Add API Provider</h2>
    <p class="text-xs text-slate-400 mb-5">Connect standard v2 API compatible SMM providers.</p>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create_provider">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Provider Name</label>
        <input type="text" name="name" required placeholder="e.g. ApexSMMApi" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">API Endpoint URL</label>
        <input type="url" name="api_url" required placeholder="https://provider.com/api/v2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">API Secret Key</label>
        <input type="text" name="api_key" required placeholder="Provider API Key" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Provider Currency</label>
        <select name="currency" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
          <option value="USD" selected>USD - United States Dollar ($)</option>
          <option value="INR">INR - Indian Rupee (₹)</option>
          <option value="EUR">EUR - Euro (€)</option>
          <option value="GBP">GBP - British Pound (£)</option>
        </select>
        <span class="text-[10px] text-slate-400 mt-1 block">The native account balance currency of this upstream provider.</span>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('new-provider-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500 cursor-pointer">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm cursor-pointer">Save Provider</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('new-provider-modal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    this.classList.add('hidden');
  }
});
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
