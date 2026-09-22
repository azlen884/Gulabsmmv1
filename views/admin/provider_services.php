<?php
$pageTitle = 'Provider Services - Admin Console';
$adminPage = 'provider-services';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$providers = $db->query("SELECT * FROM providers ORDER BY id ASC")->fetchAll();

$providerId = isset($_GET['provider']) ? (int)$_GET['provider'] : ($providers[0]['id'] ?? 0);
$selectedProvider = null;
$fetchedServices = [];
$msg = '';
$err = '';

if ($providerId > 0) {
    $pStmt = $db->prepare("SELECT * FROM providers WHERE id = ?");
    $pStmt->execute([$providerId]);
    $selectedProvider = $pStmt->fetch();
}

// Handle Import of a service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_service') {
    $srvName = trim($_POST['name']);
    $srvRate = (float)$_POST['rate'];
    $srvMin = (int)$_POST['min'];
    $srvMax = (int)$_POST['max'];
    $srvCatName = trim($_POST['category_name'] ?: 'Imported Services');
    $providerSrvId = (int)$_POST['provider_service_id'];

    // Check or create category
    $cStmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
    $cStmt->execute([$srvCatName]);
    $catId = $cStmt->fetchColumn();

    if (!$catId) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $srvCatName)));
        $db->prepare("INSERT INTO categories (name, slug, status) VALUES (?, ?, 'active')")->execute([$srvCatName, $slug]);
        $catId = $db->lastInsertId();
    }

    // Insert service with profit margin (e.g. +30% markup by default)
    $markup = 1.30;
    $retailRate = round($srvRate * $markup, 4);

    $insStmt = $db->prepare("
        INSERT INTO services (category_id, provider_id, provider_service_id, name, type, rate, min, max, status)
        VALUES (?, ?, ?, ?, 'Default', ?, ?, ?, 'active')
    ");
    $insStmt->execute([$catId, $providerId, $providerSrvId, $srvName, $retailRate, $srvMin, $srvMax]);
    $msg = "Imported \"$srvName\" with rate $$retailRate (provider cost $$srvRate).";
}

// If fetching services from provider API
if ($selectedProvider && isset($_GET['fetch']) && $_GET['fetch'] == '1') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $selectedProvider['api_url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'key' => $selectedProvider['api_key'],
        'action' => 'services'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if (is_array($data)) {
        $fetchedServices = $data;
        $msg = "Successfully retrieved " . count($fetchedServices) . " services from " . e($selectedProvider['name']);
    } else {
        $err = "Failed to retrieve services: " . ($response ?: "HTTP $httpCode error");
    }
}
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Provider Service Importer</h1>
    <p class="text-xs text-slate-500 mt-1">Directly fetch and import services with automatic markup pricing from your providers.</p>
  </div>
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

<!-- Provider Selection & Fetch Button -->
<div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-200 shadow-sm mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
  <form method="GET" class="flex items-center gap-3">
    <label class="text-xs font-bold text-slate-700 whitespace-nowrap">Choose Provider:</label>
    <select name="provider" onchange="this.form.submit()" class="px-4 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-800">
      <?php foreach ($providers as $prov): ?>
        <option value="<?= $prov['id'] ?>" <?= $providerId === (int)$prov['id'] ? 'selected' : '' ?>><?= e($prov['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($selectedProvider): ?>
    <a href="/admin/provider-services?provider=<?= $selectedProvider['id'] ?>&fetch=1" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center justify-center gap-2">
      <i data-lucide="cloud-download" class="w-4 h-4"></i>
      <span>Fetch Live Services from API</span>
    </a>
  <?php endif; ?>
</div>

<!-- Services Card List (NO TABLE UI!) -->
<?php if (empty($fetchedServices)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
      <i data-lucide="download" class="w-6 h-6"></i>
    </div>
    <div class="font-bold text-sm text-slate-700">No Services Loaded Yet</div>
    <p class="text-xs text-slate-400 mt-1">Select a provider above and click "Fetch Live Services from API" to view and import remote services.</p>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($fetchedServices as $fs): ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold text-xs">
              <?= e($fs['category'] ?? 'Default') ?>
            </span>
            <span class="text-xs font-mono text-slate-400">API ID: #<?= $fs['service'] ?? 'N/A' ?></span>
          </div>

          <h3 class="font-bold text-sm text-slate-800 mb-3"><?= e($fs['name'] ?? 'Unnamed Service') ?></h3>

          <div class="grid grid-cols-3 gap-2 py-3 border-y border-slate-100 text-xs mb-4">
            <div>
              <span class="text-slate-400 block text-[10px]">Provider Cost:</span>
              <span class="font-extrabold text-slate-900 text-sm">$<?= number_format((float)($fs['rate'] ?? 0), 4) ?></span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Min:</span>
              <span class="font-bold text-slate-700"><?= number_format((int)($fs['min'] ?? 10)) ?></span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Max:</span>
              <span class="font-bold text-slate-700"><?= number_format((int)($fs['max'] ?? 10000)) ?></span>
            </div>
          </div>
        </div>

        <form method="POST" class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <input type="hidden" name="action" value="import_service">
          <input type="hidden" name="provider_service_id" value="<?= e($fs['service'] ?? 0) ?>">
          <input type="hidden" name="name" value="<?= e($fs['name'] ?? '') ?>">
          <input type="hidden" name="rate" value="<?= e($fs['rate'] ?? 0) ?>">
          <input type="hidden" name="min" value="<?= e($fs['min'] ?? 10) ?>">
          <input type="hidden" name="max" value="<?= e($fs['max'] ?? 10000) ?>">
          <input type="hidden" name="category_name" value="<?= e($fs['category'] ?? 'Imported Services') ?>">

          <span class="text-xs text-slate-400 font-semibold">+30% retail markup</span>
          <button type="submit" class="px-4 py-1.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1.5">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span>Import Service</span>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
