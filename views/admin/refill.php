<?php
$pageTitle = 'Auto Refill Management - Admin Console';
$adminPage = 'refill';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/RefillHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_global') {
        $enabled = !empty($_POST['auto_refill_enabled']) ? '1' : '0';
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_refill_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
            ->execute([$enabled, $enabled]);
        $msg = 'Auto Refill global setting saved.';
    } elseif ($_POST['action'] === 'update_settings') {
        $warranty = max(1, (int)($_POST['refill_default_days'] ?? 30));
        $cooldown = max(1, (int)($_POST['refill_cooldown_hours'] ?? 24));
        $maxLimit = max(1, (int)($_POST['refill_max_limit'] ?? 10));

        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('refill_default_days', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$warranty, $warranty]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('refill_cooldown_hours', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$cooldown, $cooldown]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('refill_max_limit', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$maxLimit, $maxLimit]);
        $msg = 'Refill parameters updated.';
    } elseif ($_POST['action'] === 'change_status') {
        $refillId = (int)$_POST['refill_id'];
        $status = $_POST['new_status'];
        $db->prepare("UPDATE refill_requests SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $refillId]);

        // Update corresponding order refill_status
        $rStmt = $db->prepare("SELECT order_id FROM refill_requests WHERE id = ?");
        $rStmt->execute([$refillId]);
        $ordId = $rStmt->fetchColumn();
        if ($ordId) {
            $db->prepare("UPDATE orders SET refill_status = ? WHERE id = ?")->execute([$status, $ordId]);
        }
        $msg = "Refill #{$refillId} marked as {$status}.";
    } elseif ($_POST['action'] === 'manual_refill') {
        $orderId = (int)$_POST['order_id'];
        $res = RefillHelper::requestRefill($orderId, 0, false);
        if ($res['success']) {
            $msg = $res['message'];
        } else {
            $error = $res['error'];
        }
    }
}

// Global settings
$isGlobalEnabled = ($db->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_refill_enabled'")->fetchColumn() ?? '1') === '1';
$defaultDays = (int)($db->query("SELECT setting_value FROM settings WHERE setting_key = 'refill_default_days'")->fetchColumn() ?? 30);
$cooldownHours = (int)($db->query("SELECT setting_value FROM settings WHERE setting_key = 'refill_cooldown_hours'")->fetchColumn() ?? 24);
$maxLimit = (int)($db->query("SELECT setting_value FROM settings WHERE setting_key = 'refill_max_limit'")->fetchColumn() ?? 10);

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT r.*, o.link, o.quantity, o.status AS order_status, u.username, s.name AS service_name, p.name AS provider_name
    FROM refill_requests r
    JOIN orders o ON r.order_id = o.id
    JOIN users u ON r.user_id = u.id
    JOIN services s ON r.service_id = s.id
    LEFT JOIN providers p ON s.provider_id = p.id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (r.id = ? OR r.order_id = ? OR u.username LIKE ? OR o.link LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY r.id DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$refills = $stmt->fetchAll();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/60 text-emerald-700 text-xs font-bold mb-2">
        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Automated Drops & Replenishment
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Auto Refill Management
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Monitor customer refill requests, trigger manual refills, and sync with upstream API providers.
      </p>
    </div>

    <!-- Manual Refill Trigger Button -->
    <button 
      type="button" 
      onclick="document.getElementById('manualRefillModal').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
    >
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>Trigger Manual Refill</span>
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- Settings & Switch Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Master Switch -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between space-y-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
          <i data-lucide="shield-check" class="w-5 h-5"></i>
        </div>
        <div>
          <h3 class="text-sm font-black text-slate-800">Global Auto Refill</h3>
          <p class="text-[11px] text-slate-400">Accept automated and user refill requests</p>
        </div>
      </div>
      <form method="POST" class="pt-2">
        <input type="hidden" name="action" value="toggle_global">
        <input type="hidden" name="auto_refill_enabled" value="<?= $isGlobalEnabled ? '0' : '1' ?>">
        <button 
          type="submit" 
          class="w-full py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors cursor-pointer <?= $isGlobalEnabled ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>"
        >
          <?= $isGlobalEnabled ? 'Disable Auto Refill Engine' : 'Enable Auto Refill Engine' ?>
        </button>
      </form>
    </div>

    <!-- Refill Settings Form -->
    <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 p-5 shadow-sm">
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="update_settings">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
          <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Refill Parameters</h3>
          <button type="submit" class="px-3 py-1 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 cursor-pointer">
            Save Changes
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Warranty Days</label>
            <input type="number" name="refill_default_days" value="<?= $defaultDays ?>" min="1" max="365" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-semibold">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Cooldown (Hours)</label>
            <input type="number" name="refill_cooldown_hours" value="<?= $cooldownHours ?>" min="1" max="168" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-semibold">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Max Refills / Order</label>
            <input type="number" name="refill_max_limit" value="<?= $maxLimit ?>" min="1" max="100" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-semibold">
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Refill Requests Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Refill Requests</h3>
        <p class="text-xs text-slate-500 mt-0.5">Track automated and manual replenishment tickets.</p>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
          <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
          <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search ID, user..." class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs">
          <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold">Filter</button>
        </form>
      </div>
    </div>

    <?php if (empty($refills)): ?>
      <div class="text-center py-10 text-slate-400 text-xs font-medium">No refill requests found.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Refill #</th>
              <th class="py-2.5 px-3">Order #</th>
              <th class="py-2.5 px-3">User</th>
              <th class="py-2.5 px-3">Service</th>
              <th class="py-2.5 px-3">Provider Refill ID</th>
              <th class="py-2.5 px-3">Type</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3">Date</th>
              <th class="py-2.5 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($refills as $rf): ?>
              <tr>
                <td class="py-3 px-3 font-bold text-rose-600">#<?= $rf['id'] ?></td>
                <td class="py-3 px-3 font-bold text-slate-800">
                  <a href="/admin/orders?search=<?= $rf['order_id'] ?>" class="hover:underline">
                    #<?= $rf['order_id'] ?>
                  </a>
                </td>
                <td class="py-3 px-3 font-semibold text-slate-700"><?= e($rf['username']) ?></td>
                <td class="py-3 px-3">
                  <div class="font-medium text-slate-800 truncate max-w-xs"><?= e($rf['service_name']) ?></div>
                  <div class="text-[10px] text-slate-400 truncate max-w-xs"><?= e($rf['link']) ?></div>
                </td>
                <td class="py-3 px-3 font-mono text-slate-500">
                  <?= !empty($rf['provider_refill_id']) ? e($rf['provider_refill_id']) : '-' ?>
                </td>
                <td class="py-3 px-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $rf['refill_type'] === 'auto' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700' ?>">
                    <?= ucfirst($rf['refill_type']) ?>
                  </span>
                </td>
                <td class="py-3 px-3">
                  <?php if ($rf['status'] === 'completed'): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Completed</span>
                  <?php elseif (in_array($rf['status'], ['pending', 'processing'])): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 inline-flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> <?= ucfirst($rf['status']) ?>
                    </span>
                  <?php elseif ($rf['status'] === 'rejected'): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Rejected</span>
                  <?php else: ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600"><?= ucfirst($rf['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-3 text-slate-400"><?= date('M d, H:i', strtotime($rf['created_at'])) ?></td>
                <td class="py-3 px-3 text-right">
                  <form method="POST" class="inline-flex items-center gap-1">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="refill_id" value="<?= $rf['id'] ?>">
                    <?php if ($rf['status'] !== 'completed'): ?>
                      <button type="submit" name="new_status" value="completed" class="px-2 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px] cursor-pointer">
                        Complete
                      </button>
                    <?php endif; ?>
                    <?php if ($rf['status'] !== 'rejected'): ?>
                      <button type="submit" name="new_status" value="rejected" class="px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[10px] cursor-pointer">
                        Reject
                      </button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Manual Refill Trigger Modal -->
<div id="manualRefillModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl border border-slate-200">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h3 class="text-base font-black text-slate-800">Trigger Manual Refill</h3>
      <button type="button" onclick="document.getElementById('manualRefillModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form method="POST" class="space-y-4 text-xs">
      <input type="hidden" name="action" value="manual_refill">
      <div>
        <label class="block font-bold text-slate-700 mb-1">Target Order ID</label>
        <input type="number" name="order_id" required placeholder="Enter Order ID" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold focus:border-rose-400">
        <p class="text-[10px] text-slate-400 mt-1">This will evaluate warranty eligibility and submit to the upstream provider API.</p>
      </div>
      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('manualRefillModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-slate-600 font-bold">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-sm cursor-pointer">
          Execute Refill
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
