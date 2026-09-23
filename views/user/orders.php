<?php
$pageTitle = 'Order History - RoseSMM';
$activePage = 'orders';
require_once __DIR__ . '/../layouts/user_header.php';
require_once __DIR__ . '/../../includes/RefillHelper.php';

$db = getDB();
$userId = $user['id'];
$userCurrency = get_user_currency();

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT o.*, s.name AS service_name, s.refill_enabled, s.refill_days, s.refill_limit, c.name AS category_name, c.slug AS category_slug
    FROM orders o
    JOIN services s ON o.service_id = s.id
    JOIN categories c ON s.category_id = c.id
    WHERE o.user_id = ?
";
$params = [$userId];

if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (o.id = ? OR o.link LIKE ? OR s.name LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.id DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Order History</h1>
    <p class="text-xs text-slate-500 mt-1">Track orders, monitor automated delivery progress, and request refills.</p>
  </div>
  <div class="flex items-center gap-2">
    <a href="/refills" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white border border-[#FCE4E8] hover:bg-rose-50/50 text-slate-700 font-bold text-xs shadow-sm transition-colors">
      <i data-lucide="shield-check" class="w-4 h-4 text-rose-500"></i>
      <span>Refill Center</span>
    </a>
    <a href="/order" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors">
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>New Order</span>
    </a>
  </div>
</div>

<!-- Filters Bar -->
<div class="bg-white p-4 rounded-3xl border border-[#FCE4E8] shadow-sm mb-6 space-y-4">
  <div class="flex flex-col md:flex-row items-center justify-between gap-4">
    <!-- Status Tabs -->
    <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 custom-scrollbar">
      <?php 
      $tabs = [
          'all' => 'All Orders',
          'pending' => 'Pending',
          'processing' => 'Processing',
          'completed' => 'Completed',
          'partial' => 'Partial',
          'canceled' => 'Canceled'
      ];
      foreach ($tabs as $key => $lbl): ?>
        <a 
          href="/orders?status=<?= $key ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
          class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors <?= $statusFilter === $key ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-50 text-slate-600 hover:bg-rose-100' ?>"
        >
          <?= $lbl ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Search box -->
    <form method="GET" action="/orders" class="relative w-full md:w-72 shrink-0">
      <?php if ($statusFilter !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
      <?php endif; ?>
      <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
      <input 
        type="text" 
        name="search" 
        value="<?= e($search) ?>" 
        placeholder="Search Order ID, link, service..." 
        class="w-full pl-10 pr-4 py-2 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-50"
      >
    </form>
  </div>
</div>

<!-- Card List -->
<?php if (empty($orders)): ?>
  <div class="bg-white p-12 rounded-3xl border border-[#FCE4E8] text-center max-w-md mx-auto shadow-sm">
    <div class="w-16 h-16 rounded-3xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-4 border border-rose-100">
      <i data-lucide="clock" class="w-8 h-8"></i>
    </div>
    <h3 class="text-base font-black text-slate-800 mb-1">No Orders Found</h3>
    <p class="text-xs text-slate-500 mb-4">You have not placed any orders matching this filter.</p>
    <a href="/order" class="inline-block px-5 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm">Place an Order</a>
  </div>
<?php else: ?>
  <div class="space-y-4">
    <?php foreach ($orders as $ord): ?>
      <?php
        $refillCheck = RefillHelper::isEligible($ord);
        $isRefillEligible = $refillCheck['eligible'];
        $hasRefund = in_array($ord['refund_status'], ['refunded', 'partial_refunded']) && (float)$ord['refunded_amount'] > 0;
      ?>
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-4 sm:p-5 shadow-sm hover:border-rose-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4 overflow-hidden">
        <!-- Left: Order Details & Service Name -->
        <div class="flex items-start gap-3 sm:gap-4 min-w-0 flex-1">
          <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5 border border-rose-100">
            #<?= $ord['id'] ?>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600">
                <?= e($ord['category_name']) ?>
              </span>

              <?php if (!empty($ord['is_dripfeed'])): ?>
                <a href="/drip-feed" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 inline-flex items-center gap-1">
                  <i data-lucide="repeat" class="w-3 h-3"></i> Drip-Feed
                </a>
              <?php endif; ?>

              <?php if ((float)($ord['discount_amount'] ?? 0) > 0): ?>
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 inline-flex items-center gap-1">
                  <i data-lucide="tag" class="w-3 h-3"></i> Coupon -<?= format_price($ord['discount_amount'], $userCurrency, 'USD') ?>
                </span>
              <?php endif; ?>

              <?php if ($hasRefund): ?>
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                  <i data-lucide="wallet" class="w-3 h-3"></i> Refunded: <?= format_price($ord['refunded_amount'], $userCurrency, 'USD') ?>
                </span>
              <?php endif; ?>

              <span class="text-xs text-slate-400 whitespace-nowrap ml-auto sm:ml-0">
                <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
              </span>
            </div>

            <h3 class="text-sm font-bold text-slate-800 break-words mb-1">
              <?= e($ord['service_name']) ?>
            </h3>

            <div class="text-xs text-slate-500 flex items-center gap-1.5 min-w-0">
              <i data-lucide="link" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
              <a href="<?= e($ord['link']) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-rose-600 underline truncate min-w-0 flex-1 break-all">
                <?= e($ord['link']) ?>
              </a>
            </div>
          </div>
        </div>

        <!-- Right: Metrics, Badges & Refill Action -->
        <div class="flex items-center justify-between md:justify-end gap-3 sm:gap-5 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0 flex-wrap sm:flex-nowrap">
          <div class="text-left md:text-right min-w-[60px]">
            <div class="text-[10px] uppercase font-bold text-slate-400">Quantity</div>
            <div class="text-xs font-black text-slate-800"><?= number_format($ord['quantity']) ?></div>
          </div>

          <div class="text-left md:text-right min-w-[60px]">
            <div class="text-[10px] uppercase font-bold text-slate-400">Charge</div>
            <div class="text-xs font-black text-rose-600"><?= format_price($ord['charge'], $userCurrency, 'USD') ?></div>
          </div>

          <!-- Order Status Badge -->
          <div class="text-right shrink-0">
            <?php
            $badgeBg = 'bg-blue-50 text-blue-700 border-blue-200';
            if ($ord['status'] === 'completed') $badgeBg = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            elseif ($ord['status'] === 'pending') $badgeBg = 'bg-amber-50 text-amber-700 border-amber-200';
            elseif ($ord['status'] === 'canceled') $badgeBg = 'bg-rose-50 text-rose-700 border-rose-200';
            elseif ($ord['status'] === 'partial') $badgeBg = 'bg-purple-50 text-purple-700 border-purple-200';
            ?>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold border whitespace-nowrap <?= $badgeBg ?>">
              <?= ucfirst(str_replace('_', ' ', $ord['status'])) ?>
            </span>
          </div>

          <!-- Refill Action / Status Button -->
          <div class="shrink-0">
            <?php if (in_array($ord['refill_status'], ['pending', 'processing'])): ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Refill Processing
              </span>
            <?php elseif ($ord['refill_status'] === 'completed'): ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">
                <i data-lucide="check" class="w-3.5 h-3.5"></i> Refilled
              </span>
            <?php elseif ($isRefillEligible): ?>
              <button 
                type="button" 
                onclick="triggerRefill(<?= $ord['id'] ?>)" 
                class="refill-btn-<?= $ord['id'] ?> inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 text-xs font-bold transition-colors cursor-pointer"
              >
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                <span>Refill</span>
              </button>
            <?php elseif (!empty($ord['refill_enabled'])): ?>
              <span class="text-[11px] text-slate-400 font-semibold cursor-help" title="<?= e($refillCheck['reason']) ?>">
                Refill Ineligible
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
function triggerRefill(orderId) {
  if (!confirm('Submit a refill request for Order #' + orderId + '?')) return;

  const btn = document.querySelector('.refill-btn-' + orderId);
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-1">⏳</span> Submitting...';
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
      alert(data.error || 'Refill failed.');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> <span>Refill</span>';
        if (window.lucide) lucide.createIcons();
      }
    }
  })
  .catch(() => {
    alert('Network error.');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> <span>Refill</span>';
      if (window.lucide) lucide.createIcons();
    }
  });
}
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
