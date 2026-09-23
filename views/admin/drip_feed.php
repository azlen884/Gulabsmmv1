<?php
$pageTitle = 'Drip-Feed Orders - Admin Console';
$adminPage = 'drip-feed';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/DripFeedHelper.php';
require_once __DIR__ . '/../../includes/RefundHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $dripId = (int)($_POST['drip_id'] ?? 0);

    if ($_POST['action'] === 'pause') {
        $db->prepare("UPDATE drip_feed_orders SET status = 'paused', updated_at = NOW() WHERE id = ?")->execute([$dripId]);
        $msg = "Drip-Feed #{$dripId} paused.";
    } elseif ($_POST['action'] === 'resume') {
        $db->prepare("UPDATE drip_feed_orders SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$dripId]);
        $msg = "Drip-Feed #{$dripId} resumed.";
    } elseif ($_POST['action'] === 'cancel') {
        // Fetch drip feed info to calculate remaining refund
        $stmt = $db->prepare("SELECT * FROM drip_feed_orders WHERE id = ?");
        $stmt->execute([$dripId]);
        $drip = $stmt->fetch();

        if ($drip && $drip['status'] !== 'canceled') {
            $db->prepare("UPDATE drip_feed_orders SET status = 'canceled', updated_at = NOW() WHERE id = ?")->execute([$dripId]);
            $db->prepare("UPDATE drip_feed_batches SET status = 'canceled' WHERE drip_feed_id = ? AND status = 'pending'")->execute([$dripId]);

            // Calculate unexecuted runs refund
            $remainingRuns = max(0, (int)$drip['runs'] - (int)$drip['current_run']);
            if ($remainingRuns > 0 && (float)$drip['total_charge'] > 0) {
                $chargePerRun = (float)$drip['total_charge'] / max(1, (int)$drip['runs']);
                $refundAmt = round($chargePerRun * $remainingRuns, 4);

                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$refundAmt, $drip['user_id']]);
                $db->prepare("
                    INSERT INTO transactions (user_id, amount, charge, type, payment_method, status, transaction_id, created_at)
                    VALUES (?, ?, 0, 'refund', 'Wallet Balance', 'completed', ?, NOW())
                ")->execute([$drip['user_id'], $refundAmt, 'REF-DRIP-' . $dripId]);

                $msg = "Drip-Feed #{$dripId} canceled. Refunded \${$refundAmt} for {$remainingRuns} unexecuted runs.";
            } else {
                $msg = "Drip-Feed #{$dripId} canceled.";
            }
        }
    } elseif ($_POST['action'] === 'trigger_next') {
        // Execute next pending batch
        $bStmt = $db->prepare("SELECT * FROM drip_feed_batches WHERE drip_feed_id = ? AND status = 'pending' ORDER BY run_number ASC LIMIT 1");
        $bStmt->execute([$dripId]);
        $batch = $bStmt->fetch();

        if ($batch) {
            $runRes = DripFeedHelper::processBatch($batch['id']);
            if ($runRes['success']) {
                $msg = "Batch #{$batch['run_number']} executed successfully! Child Order #{$runRes['order_id']} created.";
            } else {
                $error = "Batch execution failed: " . ($runRes['error'] ?? 'Unknown');
            }
        } else {
            $error = "No pending batches available to trigger.";
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT d.*, u.username, s.name AS service_name, c.name AS category_name
    FROM drip_feed_orders d
    JOIN users u ON d.user_id = u.id
    JOIN services s ON d.service_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $sql .= " AND d.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (d.id = ? OR u.username LIKE ? OR d.link LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY d.id DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$dripOrders = $stmt->fetchAll();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200/60 text-blue-700 text-xs font-bold mb-2">
        <i data-lucide="repeat" class="w-3.5 h-3.5"></i> Scheduled Batch Engine
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Drip-Feed Order Management
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Oversee recurring delivery runs, inspect child order batches, and trigger next runs manually.
      </p>
    </div>
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

  <!-- Drip-Feed Orders Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">All Drip-Feed Orders</h3>
        <p class="text-xs text-slate-500 mt-0.5">Automated time-delayed order distributions.</p>
      </div>

      <!-- Filters -->
      <form method="GET" class="flex items-center gap-2">
        <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="paused" <?= $statusFilter === 'paused' ? 'selected' : '' ?>>Paused</option>
          <option value="canceled" <?= $statusFilter === 'canceled' ? 'selected' : '' ?>>Canceled</option>
        </select>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search ID, user, link..." class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs">
        <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold">Filter</button>
      </form>
    </div>

    <?php if (empty($dripOrders)): ?>
      <div class="text-center py-10 text-slate-400 text-xs font-medium">No drip-feed orders found.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Drip ID</th>
              <th class="py-2.5 px-3">User</th>
              <th class="py-2.5 px-3">Service</th>
              <th class="py-2.5 px-3">Progress (Runs)</th>
              <th class="py-2.5 px-3">Qty / Run</th>
              <th class="py-2.5 px-3">Interval</th>
              <th class="py-2.5 px-3">Total Cost</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($dripOrders as $dp): ?>
              <tr>
                <td class="py-3 px-3 font-bold text-rose-600">#<?= $dp['id'] ?></td>
                <td class="py-3 px-3 font-semibold text-slate-700"><?= e($dp['username']) ?></td>
                <td class="py-3 px-3">
                  <div class="font-medium text-slate-800 truncate max-w-xs"><?= e($dp['service_name']) ?></div>
                  <div class="text-[10px] text-slate-400 truncate max-w-xs"><?= e($dp['link']) ?></div>
                </td>
                <td class="py-3 px-3">
                  <span class="font-bold text-slate-800"><?= $dp['current_run'] ?></span>
                  <span class="text-slate-400">/ <?= $dp['runs'] ?></span>
                </td>
                <td class="py-3 px-3 font-semibold text-slate-700"><?= number_format($dp['quantity_per_run']) ?></td>
                <td class="py-3 px-3 font-semibold text-slate-700"><?= $dp['interval_minutes'] ?>m</td>
                <td class="py-3 px-3 font-black text-rose-600">$<?= number_format($dp['total_charge'], 4) ?></td>
                <td class="py-3 px-3">
                  <?php if ($dp['status'] === 'active'): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                  <?php elseif ($dp['status'] === 'completed'): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">Completed</span>
                  <?php elseif ($dp['status'] === 'paused'): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Paused</span>
                  <?php else: ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600"><?= ucfirst($dp['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-3 text-right space-x-1">
                  <form method="POST" class="inline">
                    <input type="hidden" name="drip_id" value="<?= $dp['id'] ?>">
                    <?php if ($dp['status'] === 'active'): ?>
                      <button type="submit" name="action" value="trigger_next" title="Trigger Next Batch" class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] cursor-pointer">
                        Run Next
                      </button>
                      <button type="submit" name="action" value="pause" class="px-2 py-1 rounded bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold text-[10px] cursor-pointer">
                        Pause
                      </button>
                    <?php elseif ($dp['status'] === 'paused'): ?>
                      <button type="submit" name="action" value="resume" class="px-2 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px] cursor-pointer">
                        Resume
                      </button>
                    <?php endif; ?>

                    <?php if ($dp['status'] !== 'completed' && $dp['status'] !== 'canceled'): ?>
                      <button type="submit" name="action" value="cancel" onclick="return confirm('Cancel drip-feed and refund remaining runs?');" class="px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[10px] cursor-pointer">
                        Cancel
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

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
