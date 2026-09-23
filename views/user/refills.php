<?php
$pageTitle = 'Auto Refill Requests - RoseSMM';
$activePage = 'refills';
require_once __DIR__ . '/../layouts/user_header.php';
require_once __DIR__ . '/../../includes/RefillHelper.php';

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
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 border border-rose-200/60 text-rose-600 text-xs font-bold mb-2">
        <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> 100% Delivery Warranty Protection
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Auto Refill Center
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Request refills for dropped orders or track existing provider replenishment jobs.
      </p>
    </div>

    <!-- Quick Manual Refill Trigger Button -->
    <button 
      type="button" 
      onclick="document.getElementById('refillModal').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
    >
      <i data-lucide="refresh-cw" class="w-4 h-4"></i>
      <span>Request Refill for Order</span>
    </button>
  </div>

  <!-- Eligible Orders Quick-Action Banner (if any) -->
  <?php if (!empty($eligibleOrders)): ?>
    <div class="bg-gradient-to-r from-rose-500/10 via-pink-500/5 to-rose-500/10 rounded-3xl border border-rose-200/80 p-5">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-xs font-black uppercase tracking-wider text-rose-700 flex items-center gap-2">
          <i data-lucide="check-circle" class="w-4 h-4"></i>
          Orders Ready For Refill (<?= count($eligibleOrders) ?>)
        </h3>
        <span class="text-[11px] text-slate-500 font-semibold">Instant 1-Click Request</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach (array_slice($eligibleOrders, 0, 3) as $el): ?>
          <div class="bg-white rounded-2xl p-3 border border-rose-100 shadow-sm flex items-center justify-between gap-2">
            <div class="overflow-hidden">
              <div class="text-xs font-bold text-slate-800 truncate">Order #<?= $el['id'] ?> - <?= e($el['service_name']) ?></div>
              <div class="text-[10px] text-slate-400 truncate"><?= e($el['link']) ?></div>
            </div>
            <button 
              type="button" 
              onclick="triggerRefill(<?= $el['id'] ?>)" 
              class="refill-btn-<?= $el['id'] ?> shrink-0 px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold transition-colors cursor-pointer"
            >
              Refill
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Refill Requests Table -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
        <i data-lucide="history" class="w-4 h-4 text-rose-500"></i>
        Refill Requests History
      </h3>
      <span class="text-xs font-bold text-slate-400"><?= count($refills) ?> Total Requests</span>
    </div>

    <?php if (empty($refills)): ?>
      <div class="py-12 text-center">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center border border-rose-100 mb-3">
          <i data-lucide="shield-check" class="w-6 h-6"></i>
        </div>
        <p class="text-xs font-bold text-slate-700">No refill requests have been filed yet.</p>
        <p class="text-[11px] text-slate-400 mt-1">If any of your orders experience a drop within warranty, you can request a refill here.</p>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Refill ID</th>
              <th class="py-2.5 px-3">Order ID</th>
              <th class="py-2.5 px-3">Service</th>
              <th class="py-2.5 px-3">Type</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3">Submitted</th>
              <th class="py-2.5 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($refills as $rf): ?>
              <tr>
                <td class="py-3 px-3 font-bold text-rose-600">#<?= $rf['id'] ?></td>
                <td class="py-3 px-3 font-bold text-slate-800">
                  <a href="/orders?id=<?= $rf['order_id'] ?>" class="hover:text-rose-600 hover:underline">
                    #<?= $rf['order_id'] ?>
                  </a>
                </td>
                <td class="py-3 px-3">
                  <div class="font-semibold text-slate-700 truncate max-w-xs"><?= e($rf['service_name']) ?></div>
                  <div class="text-[10px] text-slate-400 truncate max-w-xs"><?= e($rf['link']) ?></div>
                </td>
                <td class="py-3 px-3 font-semibold text-slate-600">
                  <?= $rf['refill_type'] === 'auto' ? '<span class="text-blue-600 font-bold">Auto</span>' : 'Manual' ?>
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
                <td class="py-3 px-3 text-slate-400"><?= date('M d, Y H:i', strtotime($rf['created_at'])) ?></td>
                <td class="py-3 px-3 text-right">
                  <a href="/orders?id=<?= $rf['order_id'] ?>" class="text-xs text-rose-500 hover:text-rose-600 font-bold">
                    View Order
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Refill Request Modal -->
<div id="refillModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-xl border border-[#FCE4E8]">
    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
      <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
        <i data-lucide="refresh-cw" class="w-4 h-4 text-rose-500"></i>
        Submit Refill Request
      </h3>
      <button type="button" onclick="document.getElementById('refillModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <p class="text-xs text-slate-500">
      Enter the Order ID you want to refill. The order must have been placed within its service warranty period and completed.
    </p>
    <div class="space-y-3">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Order ID</label>
        <input type="number" id="modalOrderId" placeholder="e.g. 1045" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:border-rose-400 focus:ring-2 focus:ring-rose-50">
      </div>
      <button 
        type="button" 
        id="modalSubmitRefillBtn" 
        onclick="triggerRefill(document.getElementById('modalOrderId').value)"
        class="w-full py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-md transition-colors cursor-pointer"
      >
        Submit Refill Request
      </button>
    </div>
  </div>
</div>

<script>
function triggerRefill(orderId) {
  if (!orderId) {
    alert('Please specify a valid Order ID.');
    return;
  }

  const btn = document.querySelector('.refill-btn-' + orderId);
  if (btn) {
    btn.disabled = true;
    btn.innerText = 'Submitting...';
  }

  fetch('/api/order/refill', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert(data.message || 'Refill request submitted successfully!');
      location.reload();
    } else {
      alert(data.error || 'Refill submission failed.');
      if (btn) {
        btn.disabled = false;
        btn.innerText = 'Refill';
      }
    }
  })
  .catch(err => {
    alert('Connection error.');
    if (btn) {
      btn.disabled = false;
      btn.innerText = 'Refill';
    }
  });
}
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
