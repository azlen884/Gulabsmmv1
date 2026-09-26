<?php
$pageTitle = 'Refill Center - SMM Pro';
$activePage = 'refills';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../../includes/RefillHelper.php';

$db = getDB();
$userId = $user['id'];
$userCurrency = get_user_currency();

// Fetch refill requests for this user
$stmt = $db->prepare("
    SELECT r.*, o.link, o.quantity, o.status AS order_status, s.name AS service_name, s.refill_days
    FROM refill_requests r
    JOIN orders o ON r.order_id = o.id
    JOIN services s ON r.service_id = s.id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
");
$stmt->execute([$userId]);
$refills = $stmt->fetchAll();

// Fetch eligible orders that user can refill right now
$eligStmt = $db->prepare("
    SELECT o.id, o.service_id, o.link, o.quantity, o.created_at, o.refill_count, o.refill_status, s.name AS service_name, s.refill_days, s.refill_limit
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.user_id = ? 
      AND o.status IN ('completed', 'partial')
      AND s.refill_enabled = 1
      AND (o.refill_status IS NULL OR o.refill_status NOT IN ('pending', 'processing'))
    ORDER BY o.id DESC
    LIMIT 20
");
$eligStmt->execute([$userId]);
$eligibleOrders = $eligStmt->fetchAll();
?>

<div class="space-y-6 max-w-6xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="shield-check" class="w-6 h-6 text-[#10B981]"></i>
        Refill Guarantee Center
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Free automated replenishment for drop-guaranteed service orders.</p>
    </div>
    <a href="/orders" class="smm-btn-dark px-4 py-2 text-xs">
      <i data-lucide="arrow-left" class="w-4 h-4"></i>
      <span>Order History</span>
    </a>
  </div>

  <!-- Eligible Orders Card -->
  <?php if (!empty($eligibleOrders)): ?>
    <div class="smm-card p-5 space-y-3">
      <h2 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
        <i data-lucide="zap" class="w-4 h-4 text-[#FF2D78]"></i> Orders Eligible For Refill (Click to Request)
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($eligibleOrders as $el): ?>
          <div class="p-3 rounded-2xl bg-[#161628] border border-white/5 flex items-center justify-between gap-3">
            <div class="min-w-0">
              <span class="font-mono text-xs font-bold text-[#FF2D78]">#<?= (int)$el['id'] ?></span>
              <div class="text-xs font-bold text-white truncate"><?= e($el['service_name']) ?></div>
              <div class="text-[10px] text-[#9D9DB8]"><?= number_format($el['quantity']) ?> units • <?= (int)$el['refill_days'] ?>d Guarantee</div>
            </div>
            <button 
              type="button" 
              onclick="triggerRefill(<?= (int)$el['id'] ?>)" 
              class="smm-btn-pink px-3 py-1.5 text-[11px] shrink-0"
            >
              Refill
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Refill Requests Table -->
  <div class="smm-card overflow-hidden">
    <div class="p-5 border-b border-white/5 flex items-center justify-between">
      <h2 class="text-base font-black text-white flex items-center gap-2">
        <i data-lucide="refresh-cw" class="w-5 h-5 text-[#FF2D78]"></i>
        Refill History & Progress
      </h2>
      <span class="text-xs text-[#9D9DB8]"><?= count($refills) ?> Request(s)</span>
    </div>

    <div class="overflow-x-auto">
      <table class="smm-table">
        <thead>
          <tr>
            <th>Refill ID</th>
            <th>Order ID</th>
            <th>Service</th>
            <th>Target Link</th>
            <th>Status</th>
            <th>Requested</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($refills)): ?>
            <tr>
              <td colspan="6" class="text-center py-10 text-[#9D9DB8]">
                No refill requests submitted yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($refills as $rf): ?>
              <tr>
                <td class="font-mono text-xs font-bold text-white">#<?= (int)$rf['id'] ?></td>
                <td class="font-mono text-xs font-bold text-[#FF2D78]">#<?= (int)$rf['order_id'] ?></td>
                <td class="text-xs font-bold text-white max-w-xs truncate"><?= e($rf['service_name']) ?></td>
                <td class="text-xs font-mono text-[#9D9DB8] max-w-[150px] truncate"><?= e($rf['link']) ?></td>
                <td>
                  <span class="smm-badge-<?= $rf['status'] === 'completed' ? 'completed' : ($rf['status'] === 'rejected' ? 'canceled' : 'in-progress') ?>">
                    <?= ucfirst($rf['status']) ?>
                  </span>
                </td>
                <td class="text-xs text-[#9D9DB8]"><?= date('M d, H:i', strtotime($rf['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function triggerRefill(orderId) {
  if (!confirm(`Submit a refill request for order #${orderId}?`)) return;

  fetch('/api/order/refill', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId })
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message || (data.success ? 'Refill submitted!' : 'Failed.'));
    if (data.success) window.location.reload();
  })
  .catch(() => alert('Network error submitting refill.'));
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
