<?php
$pageTitle = 'Auto Refund Management - Admin Console';
$adminPage = 'refunds';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/RefundHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_global') {
        $enabled = !empty($_POST['auto_refund_enabled']) ? '1' : '0';
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_refund_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
            ->execute([$enabled, $enabled]);
        $msg = 'Auto Refund global setting saved.';
    } elseif ($_POST['action'] === 'manual_refund') {
        $orderId = (int)$_POST['order_id'];
        $amount = (float)$_POST['amount'];
        $reason = trim($_POST['reason'] ?? 'Manual admin wallet refund');

        $res = RefundHelper::processRefund($orderId, $amount, $reason, 'manual', false);
        if ($res['success']) {
            $msg = $res['message'];
        } else {
            $error = $res['error'];
        }
    }
}

// Global settings
$isGlobalEnabled = ($db->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_refund_enabled'")->fetchColumn() ?? '1') === '1';

// Filters
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? 'all';

$sql = "
    SELECT r.*, o.link, o.quantity, o.charge AS order_charge, o.status AS order_status, u.username, s.name AS service_name
    FROM refund_records r
    JOIN orders o ON r.order_id = o.id
    JOIN users u ON r.user_id = u.id
    JOIN services s ON o.service_id = s.id
    WHERE 1=1
";
$params = [];

if ($typeFilter !== 'all' && !empty($typeFilter)) {
    $sql .= " AND r.refund_type = ?";
    $params[] = $typeFilter;
}

if (!empty($search)) {
    $sql .= " AND (r.id = ? OR r.order_id = ? OR u.username LIKE ? OR r.wallet_transaction_id LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY r.id DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$refunds = $stmt->fetchAll();

// Total refunded sum
$totalRefunded = (float)$db->query("SELECT SUM(amount) FROM refund_records WHERE status = 'completed'")->fetchColumn();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-50 border border-amber-200/60 text-amber-700 text-xs font-bold mb-2">
        <i data-lucide="wallet" class="w-3.5 h-3.5"></i> Wallet Return & Credit Accounting
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Auto Refund System
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Monitor automatic wallet credits triggered upon canceled/partial orders and issue manual adjustments.
      </p>
    </div>

    <!-- Manual Refund Action -->
    <button 
      type="button" 
      onclick="document.getElementById('manualRefundModal').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
    >
      <i data-lucide="plus" class="w-4 h-4 text-amber-400"></i>
      <span>Issue Order Refund</span>
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

  <!-- Summary & Global Switch Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Master Switch -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between space-y-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
          <i data-lucide="power" class="w-5 h-5"></i>
        </div>
        <div>
          <h3 class="text-sm font-black text-slate-800">Auto Refund Switch</h3>
          <p class="text-[11px] text-slate-400">Triggers wallet credit on order cancellation</p>
        </div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="toggle_global">
        <input type="hidden" name="auto_refund_enabled" value="<?= $isGlobalEnabled ? '0' : '1' ?>">
        <button 
          type="submit" 
          class="w-full py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors cursor-pointer <?= $isGlobalEnabled ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>"
        >
          <?= $isGlobalEnabled ? 'Disable Auto Refund Engine' : 'Enable Auto Refund Engine' ?>
        </button>
      </form>
    </div>

    <!-- Total Refunded Metric -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold border border-emerald-100">
        <i data-lucide="dollar-sign" class="w-6 h-6"></i>
      </div>
      <div>
        <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Refunded to Wallets</div>
        <div class="text-xl font-black text-slate-800">$<?= number_format($totalRefunded, 2) ?> USD</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Credited back seamlessly</div>
      </div>
    </div>

    <!-- Rules Trigger Info -->
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm space-y-1.5 text-xs text-slate-600">
      <div class="font-bold text-slate-800 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
        <i data-lucide="info" class="w-4 h-4 text-amber-500"></i> Automated Refund Trigger Rules
      </div>
      <p class="text-[11px] text-slate-500">
        • <strong>Canceled:</strong> 100% of order charge is refunded.<br>
        • <strong>Partial:</strong> Unfulfilled ratio <code>(remains / quantity) * charge</code> refunded.
      </p>
    </div>
  </div>

  <!-- Refund Records Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Refund Transaction Records</h3>
        <p class="text-xs text-slate-500 mt-0.5">Audit log of wallet credits issued by system and admins.</p>
      </div>

      <!-- Filters -->
      <form method="GET" class="flex items-center gap-2">
        <select name="type" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
          <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
          <option value="auto" <?= $typeFilter === 'auto' ? 'selected' : '' ?>>Automated</option>
          <option value="manual" <?= $typeFilter === 'manual' ? 'selected' : '' ?>>Manual</option>
        </select>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search Order ID, user..." class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs">
        <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold">Filter</button>
      </form>
    </div>

    <?php if (empty($refunds)): ?>
      <div class="text-center py-10 text-slate-400 text-xs font-medium">No refund records found.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Refund ID</th>
              <th class="py-2.5 px-3">Order #</th>
              <th class="py-2.5 px-3">User</th>
              <th class="py-2.5 px-3">Amount</th>
              <th class="py-2.5 px-3">Type</th>
              <th class="py-2.5 px-3">Reason</th>
              <th class="py-2.5 px-3">Transaction Reference</th>
              <th class="py-2.5 px-3">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($refunds as $rf): ?>
              <tr>
                <td class="py-3 px-3 font-mono font-bold text-rose-600">#<?= $rf['id'] ?></td>
                <td class="py-3 px-3 font-bold text-slate-800">
                  <a href="/admin/orders?search=<?= $rf['order_id'] ?>" class="hover:underline">
                    #<?= $rf['order_id'] ?>
                  </a>
                </td>
                <td class="py-3 px-3 font-semibold text-slate-700"><?= e($rf['username']) ?></td>
                <td class="py-3 px-3 font-black text-emerald-600">
                  +$<?= number_format($rf['amount'], 4) ?> USD
                </td>
                <td class="py-3 px-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $rf['refund_type'] === 'auto' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' ?>">
                    <?= ucfirst($rf['refund_type']) ?>
                  </span>
                </td>
                <td class="py-3 px-3 text-slate-600 max-w-xs truncate" title="<?= e($rf['reason']) ?>">
                  <?= e($rf['reason']) ?>
                </td>
                <td class="py-3 px-3 font-mono text-[11px] text-slate-500">TXN-#<?= e($rf['wallet_transaction_id']) ?></td>
                <td class="py-3 px-3 text-slate-400"><?= date('M d, H:i', strtotime($rf['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Manual Refund Modal -->
<div id="manualRefundModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl border border-slate-200">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h3 class="text-base font-black text-slate-800">Issue Order Refund</h3>
      <button type="button" onclick="document.getElementById('manualRefundModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form method="POST" class="space-y-4 text-xs">
      <input type="hidden" name="action" value="manual_refund">
      <div>
        <label class="block font-bold text-slate-700 mb-1">Target Order ID</label>
        <input type="number" name="order_id" required placeholder="e.g. 1024" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold focus:border-rose-400">
      </div>
      <div>
        <label class="block font-bold text-slate-700 mb-1">Refund Amount in USD</label>
        <input type="number" step="0.0001" name="amount" required placeholder="e.g. 2.50" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold focus:border-rose-400">
      </div>
      <div>
        <label class="block font-bold text-slate-700 mb-1">Reason / Note for Customer</label>
        <input type="text" name="reason" value="Provider canceled - wallet credit" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('manualRefundModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-slate-600 font-bold">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold shadow-sm cursor-pointer">
          Credit User Wallet
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
