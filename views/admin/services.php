<?php
$pageTitle = 'Services Manager - Admin Console';
$adminPage = 'services';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';

// Handle add service or toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_service') {
        $catId = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $type = $_POST['type'] ?? 'Default';
        $rate = (float)$_POST['rate'];
        $min = (int)$_POST['min'];
        $max = (int)$_POST['max'];
        $desc = trim($_POST['description'] ?? '');

        if ($catId > 0 && !empty($name) && $rate > 0) {
            $stmt = $db->prepare("
                INSERT INTO services (category_id, name, type, rate, min, max, description, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$catId, $name, $type, $rate, $min, $max, $desc]);
            $msg = "New service created successfully.";
        }
    } elseif ($action === 'toggle_service') {
        $svcId = (int)$_POST['service_id'];
        $currStatus = $_POST['status'];
        $newStatus = $currStatus === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE services SET status = ? WHERE id = ?")->execute([$newStatus, $svcId]);
        $msg = "Service status updated to $newStatus.";
    } elseif ($action === 'update_rate') {
        $svcId = (int)$_POST['service_id'];
        $rate = (float)$_POST['rate'];
        $db->prepare("UPDATE services SET rate = ? WHERE id = ?")->execute([$rate, $svcId]);
        $msg = "Service #$svcId rate updated to $$rate.";
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll();

$catFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT s.*, c.name AS category_name 
    FROM services s 
    JOIN categories c ON s.category_id = c.id 
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
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Services Directory</h1>
    <p class="text-xs text-slate-500 mt-1">Configure pricing rates, min/max constraints, and toggle service visibility.</p>
  </div>
  <button onclick="document.getElementById('new-service-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
    <i data-lucide="plus-circle" class="w-4 h-4"></i>
    <span>Create New Service</span>
  </button>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
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
    <?php foreach ($services as $s): ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-600 font-bold text-xs">
              <?= e($s['category_name']) ?>
            </span>
            <span class="text-xs font-mono text-slate-400">ID: #<?= $s['id'] ?></span>
          </div>

          <h3 class="font-bold text-sm text-slate-800 mb-2"><?= e($s['name']) ?></h3>
          <p class="text-xs text-slate-400 mb-3 line-clamp-2"><?= e($s['description']) ?></p>

          <div class="grid grid-cols-3 gap-2 py-3 border-y border-slate-100 text-xs mb-3">
            <div>
              <span class="text-slate-400 block text-[10px]">Rate per 1k:</span>
              <span class="font-extrabold text-emerald-600 text-sm">$<?= number_format($s['rate'], 4) ?></span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Min:</span>
              <span class="font-bold text-slate-700"><?= number_format($s['min']) ?></span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Max:</span>
              <span class="font-bold text-slate-700"><?= number_format($s['max']) ?></span>
            </div>
          </div>
        </div>

        <!-- Controls: Edit Rate & Toggle Active -->
        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
          <form method="POST" class="flex items-center gap-1.5 text-xs">
            <input type="hidden" name="action" value="update_rate">
            <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
            <span class="text-slate-400 font-bold">$</span>
            <input 
              type="number" 
              name="rate" 
              step="0.0001" 
              value="<?= $s['rate'] ?>" 
              class="w-20 px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono"
            >
            <button type="submit" class="px-2 py-1 rounded-lg bg-slate-800 text-white font-bold text-[10px]">Save</button>
          </form>

          <form method="POST">
            <input type="hidden" name="action" value="toggle_service">
            <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
            <input type="hidden" name="status" value="<?= $s['status'] ?>">
            <button type="submit" class="px-3 py-1 rounded-full text-xs font-bold <?= $s['status'] === 'active' ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">
              <?= ucfirst($s['status']) ?>
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Create New Service Modal -->
<div id="new-service-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 border border-slate-200 shadow-2xl relative">
    <button onclick="document.getElementById('new-service-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-1">Create New Service</h2>
    <p class="text-xs text-slate-400 mb-5">Add a new service offering to your customer catalog.</p>

    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="action" value="create_service">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
        <select name="category_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Service Name</label>
        <input type="text" name="name" required placeholder="e.g. Instagram Followers [Non-Drop]" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Rate ($/1K)</label>
          <input type="number" step="0.0001" name="rate" required placeholder="1.50" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Min Quantity</label>
          <input type="number" name="min" value="10" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Max Quantity</label>
          <input type="number" name="max" value="50000" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
        <textarea name="description" rows="2" placeholder="Details about speed, guarantee, refill..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('new-service-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Save Service</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
