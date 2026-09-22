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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_service') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'default';
            $originalRate = (float)($_POST['original_rate'] ?? 0);
            $marginType = in_array($_POST['margin_type'] ?? '', ['percentage', 'fixed']) ? $_POST['margin_type'] : 'percentage';
            $marginVal = (float)($_POST['margin_value'] ?? 30.0);
            
            // Calculate selling rate
            if ($marginType === 'percentage') {
                $calcRate = round($originalRate * (1 + ($marginVal / 100.0)), 4);
            } else {
                $calcRate = round($originalRate + $marginVal, 4);
            }

            $rate = (isset($_POST['rate']) && (float)$_POST['rate'] > 0) ? round((float)$_POST['rate'], 4) : $calcRate;
            if ($originalRate <= 0 && $rate > 0) {
                $originalRate = round($rate / 1.3, 4);
            }

            $min = max(1, (int)($_POST['min_quantity'] ?? 10));
            $max = max($min, (int)($_POST['max_quantity'] ?? 100000));
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if ($catId > 0 && !empty($name) && $rate > 0) {
                $stmt = $db->prepare("
                    INSERT INTO services (category_id, name, type, rate, original_rate, margin_type, margin_value, min_quantity, max_quantity, description, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$catId, $name, $type, $rate, $originalRate, $marginType, $marginVal, $min, $max, $desc, $status]);
                $msg = "New service \"$name\" created successfully with selling price $$rate.";
            } else {
                $err = "Please provide a valid service name, category, and rate.";
            }
        } elseif ($action === 'update_service') {
            $svcId = (int)($_POST['service_id'] ?? 0);
            $catId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $originalRate = (float)($_POST['original_rate'] ?? 0);
            $marginType = in_array($_POST['margin_type'] ?? '', ['percentage', 'fixed']) ? $_POST['margin_type'] : 'percentage';
            $marginVal = (float)($_POST['margin_value'] ?? 30.0);
            
            // Calculate selling rate
            if ($marginType === 'percentage') {
                $calcRate = round($originalRate * (1 + ($marginVal / 100.0)), 4);
            } else {
                $calcRate = round($originalRate + $marginVal, 4);
            }

            $rate = (isset($_POST['rate']) && (float)$_POST['rate'] > 0) ? round((float)$_POST['rate'], 4) : $calcRate;
            $min = max(1, (int)($_POST['min_quantity'] ?? 10));
            $max = max($min, (int)($_POST['max_quantity'] ?? 100000));
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if ($svcId > 0 && $catId > 0 && !empty($name) && $rate > 0) {
                $stmt = $db->prepare("
                    UPDATE services 
                    SET category_id = ?, name = ?, rate = ?, original_rate = ?, margin_type = ?, margin_value = ?, min_quantity = ?, max_quantity = ?, description = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$catId, $name, $rate, $originalRate, $marginType, $marginVal, $min, $max, $desc, $status, $svcId]);
                $msg = "Service #$svcId (\"$name\") updated successfully. Final rate: $$rate.";
            } else {
                $err = "Invalid service update parameters.";
            }
        } elseif ($action === 'toggle_service') {
            $svcId = (int)($_POST['service_id'] ?? 0);
            $currStatus = $_POST['status'] ?? 'active';
            $newStatus = $currStatus === 'active' ? 'inactive' : 'active';
            $db->prepare("UPDATE services SET status = ? WHERE id = ?")->execute([$newStatus, $svcId]);
            $msg = "Service #$svcId status toggled to " . strtoupper($newStatus) . ".";
        } elseif ($action === 'update_margin') {
            $svcId = (int)($_POST['service_id'] ?? 0);
            $origRate = (float)($_POST['original_rate'] ?? 0);
            $marginType = in_array($_POST['margin_type'] ?? '', ['percentage', 'fixed']) ? $_POST['margin_type'] : 'percentage';
            $marginVal = (float)($_POST['margin_value'] ?? 0);
            
            if ($marginType === 'percentage') {
                $newRate = round($origRate * (1 + ($marginVal / 100.0)), 4);
            } else {
                $newRate = round($origRate + $marginVal, 4);
            }

            if ($newRate <= 0 && $origRate > 0) $newRate = $origRate;

            $db->prepare("
                UPDATE services 
                SET original_rate = ?, margin_type = ?, margin_value = ?, rate = ?
                WHERE id = ?
            ")->execute([$origRate, $marginType, $marginVal, $newRate, $svcId]);
            $msg = "Service #$svcId margin updated! New retail selling price is $$newRate.";
        } elseif ($action === 'delete_service') {
            $svcId = (int)($_POST['service_id'] ?? 0);
            if ($svcId > 0) {
                $db->prepare("DELETE FROM services WHERE id = ?")->execute([$svcId]);
                $msg = "Service #$svcId permanently deleted from MySQL.";
            }
        }
    } catch (Throwable $e) {
        $err = "Operation failed: " . $e->getMessage();
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
$catFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT s.*, c.name AS category_name, p.name AS provider_name
    FROM services s 
    JOIN categories c ON s.category_id = c.id 
    LEFT JOIN providers p ON s.provider_id = p.id
    WHERE 1=1
";
$params = [];

if ($catFilter > 0) {
    $sql .= " AND s.category_id = ?";
    $params[] = $catFilter;
}

if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.id = ?)";
    $params[] = "%$search%";
    $params[] = is_numeric($search) ? (int)$search : 0;
}

$sql .= " ORDER BY s.sort_order ASC, s.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

$pageTitle = 'Services Manager - Admin Console';
$adminPage = 'services';
require_once __DIR__ . '/../layouts/admin_header.php';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Services Directory</h1>
    <p class="text-xs text-slate-500 mt-1">Manage provider costs, profit margins, retail selling prices, and visibility.</p>
  </div>
  <div class="flex items-center gap-2">
    <a href="/admin/provider-services" class="px-4 py-2.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5">
      <i data-lucide="download-cloud" class="w-4 h-4 text-rose-500"></i>
      <span>Import Services</span>
    </a>
    <button onclick="document.getElementById('new-service-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
      <i data-lucide="plus-circle" class="w-4 h-4"></i>
      <span>Create New Service</span>
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

<!-- Filter & Search Controls -->
<div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
  <form method="GET" class="flex flex-wrap items-center gap-3">
    <select name="category" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700">
      <option value="0">All Categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="relative">
      <input 
        type="text" 
        name="search" 
        value="<?= e($search) ?>" 
        placeholder="Search service name or ID..." 
        class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-400"
      >
      <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
    </div>
    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold">Filter</button>
    <?php if ($catFilter > 0 || !empty($search)): ?>
      <a href="/admin/services" class="text-xs text-rose-500 font-bold hover:underline">Reset</a>
    <?php endif; ?>
  </form>
  <span class="text-xs text-slate-400 font-semibold"><?= count($services) ?> Total Services</span>
</div>

<!-- Services Card List (NO TABLE UI!) -->
<?php if (empty($services)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <p class="text-xs text-slate-400">No services match your filters.</p>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($services as $s): 
      $origCost = (float)($s['original_rate'] > 0 ? $s['original_rate'] : round($s['rate'] / 1.3, 4));
      $mType = $s['margin_type'] ?: 'percentage';
      $mVal = (float)$s['margin_value'];
    ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between overflow-hidden">
        <div class="min-w-0">
          <div class="flex items-center justify-between gap-2 mb-2 min-w-0">
            <span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-600 font-bold text-xs truncate max-w-[160px]">
              <?= e($s['category_name']) ?>
            </span>
            <div class="flex items-center gap-1.5 shrink-0 text-xs">
              <span class="font-mono text-slate-400">ID: #<?= $s['id'] ?></span>
              <?php if (!empty($s['provider_name'])): ?>
                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-semibold">
                  <?= e($s['provider_name']) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>

          <h3 class="font-bold text-sm text-slate-800 mb-2 break-words"><?= e($s['name']) ?></h3>
          <p class="text-xs text-slate-400 mb-3 line-clamp-2 break-words"><?= e($s['description'] ?: 'High quality delivery speed and stability.') ?></p>

          <!-- Pricing & Limits Grid -->
          <div class="grid grid-cols-4 gap-2 py-2.5 px-3 bg-slate-50 rounded-2xl text-xs mb-3">
            <div class="min-w-0">
              <span class="text-slate-400 block text-[10px] font-semibold">Cost:</span>
              <span class="font-bold text-slate-700 text-xs block truncate">$<?= number_format($origCost, 4) ?></span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[10px] font-semibold">Margin:</span>
              <span class="font-bold text-rose-500 text-xs block truncate">
                <?= $mType === 'percentage' ? "+$mVal%" : "+$$mVal" ?>
              </span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[10px] font-semibold">Selling:</span>
              <span class="font-extrabold text-emerald-600 text-xs block truncate">$<?= number_format($s['rate'], 4) ?></span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[10px] font-semibold">Min-Max:</span>
              <span class="font-bold text-slate-700 text-[11px] block truncate">
                <?= number_format($s['min_quantity']) ?> - <?= number_format($s['max_quantity']) ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Controls: Quick Margin Recalculator, Edit & Toggle -->
        <div class="pt-3 border-t border-slate-100 space-y-2">
          <!-- Quick Inline Margin & Price Update -->
          <form method="POST" class="flex items-center gap-1.5 text-xs flex-wrap sm:flex-nowrap">
            <input type="hidden" name="action" value="update_margin">
            <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
            <input type="hidden" name="original_rate" value="<?= $origCost ?>">
            
            <span class="text-[11px] font-bold text-slate-500 whitespace-nowrap">Margin:</span>
            <select name="margin_type" class="px-1.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-700">
              <option value="percentage" <?= $mType === 'percentage' ? 'selected' : '' ?>>%</option>
              <option value="fixed" <?= $mType === 'fixed' ? 'selected' : '' ?>>$</option>
            </select>
            <input 
              type="number" 
              step="0.1" 
              name="margin_value" 
              value="<?= $mVal ?>" 
              class="w-16 px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-800 text-center"
            >
            <button type="submit" title="Recalculate & Save Selling Price" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-bold text-[11px] shrink-0 transition-colors">
              Recalculate
            </button>
          </form>

          <div class="flex items-center justify-between gap-2 pt-1">
            <!-- Full Edit Button (Modal Trigger) -->
            <button 
              type="button" 
              onclick='openEditServiceModal(<?= json_encode([
                "id" => $s["id"],
                "category_id" => $s["category_id"],
                "name" => $s["name"],
                "original_rate" => $origCost,
                "margin_type" => $mType,
                "margin_value" => $mVal,
                "rate" => (float)$s["rate"],
                "min_quantity" => (int)$s["min_quantity"],
                "max_quantity" => (int)$s["max_quantity"],
                "description" => $s["description"] ?? "",
                "status" => $s["status"]
              ]) ?>)' 
              class="px-3 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1 transition-colors"
            >
              <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
              <span>Edit Details</span>
            </button>

            <div class="flex items-center gap-1.5">
              <!-- Toggle Active Button -->
              <form method="POST">
                <input type="hidden" name="action" value="toggle_service">
                <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                <input type="hidden" name="status" value="<?= $s['status'] ?>">
                <button type="submit" class="px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap transition-colors <?= $s['status'] === 'active' ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">
                  <?= ucfirst($s['status']) ?>
                </button>
              </form>

              <!-- Delete Button -->
              <form method="POST" onsubmit="return confirm('Permanently delete this service from MySQL?');">
                <input type="hidden" name="action" value="delete_service">
                <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" title="Delete Service">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Create New Service Modal -->
<div id="new-service-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 border border-slate-200 shadow-2xl relative max-h-[90vh] overflow-y-auto">
    <button onclick="document.getElementById('new-service-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-1">Create New Service</h2>
    <p class="text-xs text-slate-400 mb-5">Add a new service offering with configured markup and selling price.</p>

    <form method="POST" id="new-service-form" class="space-y-4">
      <input type="hidden" name="action" value="create_service">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
        <select name="category_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Service Name</label>
        <input type="text" name="name" required placeholder="e.g. Instagram Followers [Non-Drop]" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium">
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Provider Cost ($)</label>
          <input type="number" step="0.0001" id="new-orig-rate" name="original_rate" value="1.0000" oninput="calcNewServicePrice()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Margin Markup</label>
          <div class="flex items-center gap-1">
            <select id="new-margin-type" name="margin_type" onchange="calcNewServicePrice()" class="px-1.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
              <option value="percentage">%</option>
              <option value="fixed">$</option>
            </select>
            <input type="number" step="0.1" id="new-margin-val" name="margin_value" value="30" oninput="calcNewServicePrice()" class="w-full px-2 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Selling Rate ($/1k)</label>
          <input type="number" step="0.0001" id="new-rate" name="rate" value="1.3000" required class="w-full px-3 py-2 bg-rose-50 border border-rose-200 rounded-xl text-xs font-bold text-rose-600">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Min Quantity</label>
          <input type="number" name="min_quantity" value="10" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Max Quantity</label>
          <input type="number" name="max_quantity" value="50000" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
        <textarea name="description" rows="2" placeholder="Details about speed, guarantee, refill..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
        <select name="status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
          <option value="active" selected>Active (Visible to users)</option>
          <option value="inactive">Inactive (Hidden)</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3">
        <button type="button" onclick="document.getElementById('new-service-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Save Service</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Service Modal -->
<div id="edit-service-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 border border-slate-200 shadow-2xl relative max-h-[90vh] overflow-y-auto">
    <button onclick="document.getElementById('edit-service-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-1">Edit Service & Margin</h2>
    <p class="text-xs text-slate-400 mb-5">Update provider cost, margin markup, selling price, and limits.</p>

    <form method="POST" id="edit-service-form" class="space-y-4">
      <input type="hidden" name="action" value="update_service">
      <input type="hidden" name="service_id" id="edit-svc-id">

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
        <select name="category_id" id="edit-cat-id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Service Name</label>
        <input type="text" name="name" id="edit-name" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium">
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Provider Cost ($)</label>
          <input type="number" step="0.0001" id="edit-orig-rate" name="original_rate" oninput="calcEditServicePrice()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Margin Markup</label>
          <div class="flex items-center gap-1">
            <select id="edit-margin-type" name="margin_type" onchange="calcEditServicePrice()" class="px-1.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
              <option value="percentage">%</option>
              <option value="fixed">$</option>
            </select>
            <input type="number" step="0.1" id="edit-margin-val" name="margin_value" oninput="calcEditServicePrice()" class="w-full px-2 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-700 mb-1">Selling Rate ($/1k)</label>
          <input type="number" step="0.0001" id="edit-rate" name="rate" required class="w-full px-3 py-2 bg-rose-50 border border-rose-200 rounded-xl text-xs font-bold text-rose-600">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Min Quantity</label>
          <input type="number" name="min_quantity" id="edit-min-qty" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Max Quantity</label>
          <input type="number" name="max_quantity" id="edit-max-qty" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
        <textarea name="description" id="edit-description" rows="2" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
        <select name="status" id="edit-status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
          <option value="active">Active (Visible)</option>
          <option value="inactive">Inactive (Hidden)</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3">
        <button type="button" onclick="document.getElementById('edit-service-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Update Service in MySQL</button>
      </div>
    </form>
  </div>
</div>

<script>
function calcNewServicePrice() {
  const cost = parseFloat(document.getElementById('new-orig-rate').value) || 0;
  const mType = document.getElementById('new-margin-type').value;
  const mVal = parseFloat(document.getElementById('new-margin-val').value) || 0;
  let price = cost;
  if (mType === 'percentage') {
    price = cost * (1 + (mVal / 100));
  } else {
    price = cost + mVal;
  }
  document.getElementById('new-rate').value = price.toFixed(4);
}

function calcEditServicePrice() {
  const cost = parseFloat(document.getElementById('edit-orig-rate').value) || 0;
  const mType = document.getElementById('edit-margin-type').value;
  const mVal = parseFloat(document.getElementById('edit-margin-val').value) || 0;
  let price = cost;
  if (mType === 'percentage') {
    price = cost * (1 + (mVal / 100));
  } else {
    price = cost + mVal;
  }
  document.getElementById('edit-rate').value = price.toFixed(4);
}

function openEditServiceModal(s) {
  document.getElementById('edit-svc-id').value = s.id;
  document.getElementById('edit-cat-id').value = s.category_id;
  document.getElementById('edit-name').value = s.name;
  document.getElementById('edit-orig-rate').value = s.original_rate.toFixed(4);
  document.getElementById('edit-margin-type').value = s.margin_type || 'percentage';
  document.getElementById('edit-margin-val').value = s.margin_value;
  document.getElementById('edit-rate').value = s.rate.toFixed(4);
  document.getElementById('edit-min-qty').value = s.min_quantity;
  document.getElementById('edit-max-qty').value = s.max_quantity;
  document.getElementById('edit-description').value = s.description || '';
  document.getElementById('edit-status').value = s.status;
  document.getElementById('edit-service-modal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
