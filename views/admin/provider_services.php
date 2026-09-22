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

$providers = $db->query("SELECT * FROM providers ORDER BY id ASC")->fetchAll();
$providerId = isset($_GET['provider']) ? (int)$_GET['provider'] : ($providers[0]['id'] ?? 0);
$selectedProvider = null;
$fetchedServices = [];

if ($providerId > 0) {
    $pStmt = $db->prepare("SELECT * FROM providers WHERE id = ?");
    $pStmt->execute([$providerId]);
    $selectedProvider = $pStmt->fetch();
}

// Handle Import of a service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_service') {
    try {
        $srvName = trim($_POST['name'] ?? '');
        $originalRate = (float)($_POST['original_rate'] ?? 0);
        $marginType = in_array($_POST['margin_type'] ?? '', ['percentage', 'fixed']) ? $_POST['margin_type'] : 'percentage';
        $marginVal = (float)($_POST['margin_value'] ?? 30.0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        
        // Calculate selling price
        if ($marginType === 'percentage') {
            $retailRate = round($originalRate * (1 + ($marginVal / 100.0)), 4);
        } else {
            $retailRate = round($originalRate + $marginVal, 4);
        }

        // Allow explicit override if provided and valid
        if (isset($_POST['selling_rate']) && (float)$_POST['selling_rate'] > 0) {
            $retailRate = round((float)$_POST['selling_rate'], 4);
        }

        $srvMin = max(1, (int)($_POST['min_quantity'] ?? 10));
        $srvMax = max($srvMin, (int)($_POST['max_quantity'] ?? 10000));
        $srvCatName = trim($_POST['category_name'] ?? 'Imported Services') ?: 'Imported Services';
        $providerSrvId = trim($_POST['provider_service_id'] ?? '');
        $targetProviderId = (int)($_POST['provider_id'] ?? $providerId);

        if (empty($srvName)) {
            throw new Exception("Service name cannot be empty.");
        }

        // Check or create category
        $cStmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
        $cStmt->execute([$srvCatName]);
        $catId = $cStmt->fetchColumn();

        if (!$catId) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $srvCatName)));
            if (empty($slug)) $slug = 'cat-' . time();
            $db->prepare("INSERT INTO categories (name, slug, status) VALUES (?, ?, 'active')")->execute([$srvCatName, $slug]);
            $catId = $db->lastInsertId();
        }

        // Check if service already exists from this provider
        $chkStmt = $db->prepare("SELECT id FROM services WHERE provider_id = ? AND provider_service_id = ? LIMIT 1");
        $chkStmt->execute([$targetProviderId, $providerSrvId]);
        $existingId = $chkStmt->fetchColumn();

        if ($existingId) {
            // Update existing service
            $updStmt = $db->prepare("
                UPDATE services 
                SET category_id = ?, name = ?, rate = ?, original_rate = ?, margin_type = ?, margin_value = ?, min_quantity = ?, max_quantity = ?, status = ?
                WHERE id = ?
            ");
            $updStmt->execute([$catId, $srvName, $retailRate, $originalRate, $marginType, $marginVal, $srvMin, $srvMax, $status, $existingId]);
            $msg = "Updated existing service \"$srvName\" (ID: #$existingId) with new rate $$retailRate (Margin: " . ($marginType === 'percentage' ? "$marginVal%" : "+$$marginVal") . ").";
        } else {
            // Insert new service with proper column names
            $insStmt = $db->prepare("
                INSERT INTO services (category_id, provider_id, provider_service_id, name, type, rate, original_rate, margin_type, margin_value, min_quantity, max_quantity, description, status)
                VALUES (?, ?, ?, ?, 'default', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insStmt->execute([
                $catId,
                $targetProviderId,
                $providerSrvId,
                $srvName,
                $retailRate,
                $originalRate,
                $marginType,
                $marginVal,
                $srvMin,
                $srvMax,
                "Direct imported from provider. High delivery speed.",
                $status
            ]);
            $newId = $db->lastInsertId();
            $msg = "Successfully imported \"$srvName\" (ID: #$newId) with selling rate $$retailRate (Provider cost $$originalRate, Margin: " . ($marginType === 'percentage' ? "$marginVal%" : "+$$marginVal") . ").";
        }
    } catch (Throwable $e) {
        $err = "Import failed: " . $e->getMessage();
    }
}

// Handle fetching services from provider API
if ($selectedProvider && isset($_GET['fetch']) && $_GET['fetch'] == '1') {
    if (empty($selectedProvider['api_url']) || empty($selectedProvider['api_key'])) {
        $err = "Provider \"{$selectedProvider['name']}\" does not have an API URL or API Key configured. Please edit the provider in Providers settings first.";
    } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $selectedProvider['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'key' => $selectedProvider['api_key'],
            'action' => 'services'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RoseSMM-Core/2.0');
        
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!empty($curlErr)) {
            $err = "cURL Connection Error to {$selectedProvider['name']} ({$selectedProvider['api_url']}): $curlErr";
        } elseif ($httpCode >= 400) {
            $err = "Provider API server returned HTTP status $httpCode. Response: " . htmlspecialchars(substr($response, 0, 250));
        } else {
            $data = json_decode($response, true);
            if (isset($data['error'])) {
                $err = "Provider returned API error: " . (is_string($data['error']) ? $data['error'] : json_encode($data['error']));
            } elseif (!is_array($data)) {
                $err = "Invalid JSON response from provider API: " . htmlspecialchars(substr($response, 0, 250));
            } else {
                $fetchedServices = $data;
                if (empty($fetchedServices)) {
                    $msg = "Connected to {$selectedProvider['name']} successfully, but 0 services were returned.";
                } else {
                    $msg = "Successfully retrieved " . count($fetchedServices) . " services from " . $selectedProvider['name'] . ". You can now configure your profit margin and import them.";
                }
            }
        }
    }
}

$pageTitle = 'Provider Services - Admin Console';
$adminPage = 'provider-services';
require_once __DIR__ . '/../layouts/admin_header.php';
$allCategories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Provider Service Importer</h1>
    <p class="text-xs text-slate-500 mt-1">Directly fetch and import services with customizable profit margins into MySQL.</p>
  </div>
  <a href="/admin/services" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5">
    <i data-lucide="layers" class="w-4 h-4"></i>
    <span>View Catalog Services</span>
  </a>
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

<!-- Provider Selection & Global Margin Controls -->
<div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm mb-6 space-y-4">
  <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
    <form method="GET" class="flex flex-wrap items-center gap-3">
      <label class="text-xs font-bold text-slate-700 whitespace-nowrap">Target Provider:</label>
      <select name="provider" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-800 focus:outline-none focus:border-rose-500">
        <?php foreach ($providers as $prov): ?>
          <option value="<?= $prov['id'] ?>" <?= $providerId === (int)$prov['id'] ? 'selected' : '' ?>>
            <?= e($prov['name']) ?> (<?= e($prov['status']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if ($selectedProvider): ?>
      <div class="flex items-center gap-3">
        <a href="/admin/provider-services?provider=<?= $selectedProvider['id'] ?>&fetch=1" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center justify-center gap-2">
          <i data-lucide="cloud-download" class="w-4 h-4"></i>
          <span>Fetch Live Services from API</span>
        </a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($fetchedServices)): ?>
    <!-- Global Batch Margin Quick Setter -->
    <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs bg-slate-50 p-4 rounded-2xl">
      <div class="flex items-center gap-2">
        <i data-lucide="sliders" class="w-4 h-4 text-rose-500"></i>
        <span class="font-bold text-slate-800">Batch Margin Preset:</span>
        <span class="text-slate-500">Quickly apply markup to all services on this page</span>
      </div>
      <div class="flex items-center gap-2">
        <select id="global-margin-type" class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl font-bold text-slate-700">
          <option value="percentage">Percentage Markup (%)</option>
          <option value="fixed">Fixed Markup ($)</option>
        </select>
        <input type="number" id="global-margin-val" value="30" step="0.5" class="w-20 px-3 py-1.5 bg-white border border-slate-200 rounded-xl font-bold text-slate-800 text-center">
        <button type="button" onclick="applyGlobalMargin()" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold transition-colors">
          Apply to All
        </button>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Services Card List (NO TABLE UI!) -->
<?php if (empty($fetchedServices)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-3">
      <i data-lucide="download-cloud" class="w-6 h-6"></i>
    </div>
    <div class="font-bold text-sm text-slate-800">Ready to Import Services</div>
    <p class="text-xs text-slate-400 mt-1 mb-4 leading-relaxed">
      Choose a connected provider from the selector above and click "Fetch Live Services from API". Each service will allow you to manually customize margin, selling price, and category before saving directly into MySQL.
    </p>
    <?php if ($selectedProvider): ?>
      <a href="/admin/provider-services?provider=<?= $selectedProvider['id'] ?>&fetch=1" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        <span>Fetch Services Now</span>
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($fetchedServices as $idx => $fs): 
        $cost = (float)($fs['rate'] ?? 0);
        $defaultMargin = 30.0;
        $defaultSelling = round($cost * (1 + $defaultMargin / 100.0), 4);
        $minQty = (int)($fs['min'] ?? 10);
        $maxQty = (int)($fs['max'] ?? 10000);
        $cardId = "srv-card-" . $idx;
    ?>
      <div id="<?= $cardId ?>" class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold text-xs truncate max-w-[200px]">
              <?= e($fs['category'] ?? 'General') ?>
            </span>
            <span class="text-xs font-mono text-slate-400">Provider Service ID: #<?= e($fs['service'] ?? 'N/A') ?></span>
          </div>

          <h3 class="font-bold text-sm text-slate-800 mb-2 leading-snug"><?= e($fs['name'] ?? 'Unnamed Service') ?></h3>

          <div class="grid grid-cols-3 gap-2 py-2.5 px-3 bg-slate-50 rounded-2xl text-xs mb-4">
            <div>
              <span class="text-slate-400 block text-[10px] font-semibold">Provider Cost:</span>
              <span class="font-extrabold text-slate-900 text-sm">$<span class="card-cost"><?= number_format($cost, 4) ?></span></span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px] font-semibold">Min Qty:</span>
              <span class="font-bold text-slate-700"><?= number_format($minQty) ?></span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px] font-semibold">Max Qty:</span>
              <span class="font-bold text-slate-700"><?= number_format($maxQty) ?></span>
            </div>
          </div>
        </div>

        <!-- Editable Margin & Pricing Form -->
        <form method="POST" action="/admin/provider-services?provider=<?= $providerId ?>" class="pt-3 border-t border-slate-100 space-y-3">
          <input type="hidden" name="action" value="import_service">
          <input type="hidden" name="provider_id" value="<?= $providerId ?>">
          <input type="hidden" name="provider_service_id" value="<?= e($fs['service'] ?? 0) ?>">
          <input type="hidden" name="name" value="<?= e($fs['name'] ?? '') ?>">
          <input type="hidden" name="original_rate" value="<?= $cost ?>">
          <input type="hidden" name="min_quantity" value="<?= $minQty ?>">
          <input type="hidden" name="max_quantity" value="<?= $maxQty ?>">
          <input type="hidden" name="category_name" value="<?= e($fs['category'] ?? 'Imported Services') ?>">

          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <label class="block text-[10px] font-bold text-slate-500 mb-1">Margin Markup</label>
              <div class="flex items-center gap-1">
                <select name="margin_type" onchange="recalculateCard('<?= $cardId ?>')" class="margin-type-sel px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                  <option value="percentage">%</option>
                  <option value="fixed">$</option>
                </select>
                <input 
                  type="number" 
                  step="0.1" 
                  name="margin_value" 
                  value="<?= $defaultMargin ?>" 
                  oninput="recalculateCard('<?= $cardId ?>')" 
                  class="margin-val-inp w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800"
                >
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-bold text-slate-500 mb-1">Final Selling Price ($/1k)</label>
              <div class="relative">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-xs">$</span>
                <input 
                  type="number" 
                  step="0.0001" 
                  name="selling_rate" 
                  value="<?= $defaultSelling ?>" 
                  class="selling-rate-inp w-full pl-6 pr-2.5 py-1.5 bg-rose-50/50 border border-rose-200 rounded-xl text-xs font-black text-rose-600 focus:outline-none focus:border-rose-500"
                >
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between gap-3 pt-1">
            <div class="flex items-center gap-2">
              <label class="text-[10px] font-bold text-slate-400">Status:</label>
              <select name="status" class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-700">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <button type="submit" class="px-5 py-2 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
              <i data-lucide="plus" class="w-3.5 h-3.5"></i>
              <span>Import Service</span>
            </button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
function recalculateCard(cardId) {
  const card = document.getElementById(cardId);
  if (!card) return;
  const cost = parseFloat(card.querySelector('.card-cost').textContent) || 0;
  const marginType = card.querySelector('.margin-type-sel').value;
  const marginVal = parseFloat(card.querySelector('.margin-val-inp').value) || 0;
  const sellingInp = card.querySelector('.selling-rate-inp');

  let sellingPrice = cost;
  if (marginType === 'percentage') {
    sellingPrice = cost * (1 + (marginVal / 100));
  } else {
    sellingPrice = cost + marginVal;
  }
  sellingInp.value = sellingPrice.toFixed(4);
}

function applyGlobalMargin() {
  const gType = document.getElementById('global-margin-type').value;
  const gVal = parseFloat(document.getElementById('global-margin-val').value) || 0;

  document.querySelectorAll('.margin-type-sel').forEach(sel => {
    sel.value = gType;
  });
  document.querySelectorAll('.margin-val-inp').forEach(inp => {
    inp.value = gVal;
  });

  document.querySelectorAll('[id^="srv-card-"]').forEach(card => {
    recalculateCard(card.id);
  });
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
