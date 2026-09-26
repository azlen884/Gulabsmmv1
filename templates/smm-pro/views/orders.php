<?php
$pageTitle = 'Order History - SMM Pro';
$activePage = 'orders';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../../includes/RefillHelper.php';

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

// Counts for filter pills
$countSql = "SELECT status, COUNT(*) as cnt FROM orders WHERE user_id = ? GROUP BY status";
$countStmt = $db->prepare($countSql);
$countStmt->execute([$userId]);
$statusCounts = [];
$totalAll = 0;
while ($r = $countStmt->fetch()) {
    $statusCounts[strtolower($r['status'])] = (int)$r['cnt'];
    $totalAll += (int)$r['cnt'];
}
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Top Bar -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="clipboard-list" class="w-6 h-6 text-[#FF2D78]"></i>
        Order History
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Real-time status tracking, instant fulfillment verification, and automated refills.</p>
    </div>
    <div class="flex items-center gap-2.5">
      <a href="/order" class="smm-btn-pink px-4 py-2 text-xs">
        <i data-lucide="plus" class="w-4 h-4"></i>
        <span>Place New Order</span>
      </a>
      <a href="/refills" class="smm-btn-dark px-4 py-2 text-xs">
        <i data-lucide="shield-check" class="w-4 h-4 text-[#FF2D78]"></i>
        <span>Refill Center</span>
      </a>
    </div>
  </div>

  <!-- Filters & Search Card -->
  <div class="smm-card p-4 sm:p-5 space-y-4">
    <!-- Status Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
      <a href="/orders?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">
        <span>All</span>
        <span class="text-[10px] opacity-75 font-bold">(<?= $totalAll ?>)</span>
      </a>
      <a href="/orders?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">
        <span>Pending</span>
        <span class="text-[10px] opacity-75 font-bold">(<?= $statusCounts['pending'] ?? 0 ?>)</span>
      </a>
      <a href="/orders?status=in_progress<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $statusFilter === 'in_progress' ? 'active' : '' ?>">
        <span>In Progress</span>
        <span class="text-[10px] opacity-75 font-bold">(<?= ($statusCounts['in_progress'] ?? 0) + ($statusCounts['processing'] ?? 0) ?>)</span>
      </a>
      <a href="/orders?status=completed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $statusFilter === 'completed' ? 'active' : '' ?>">
        <span>Completed</span>
        <span class="text-[10px] opacity-75 font-bold">(<?= $statusCounts['completed'] ?? 0 ?>)</span>
      </a>
      <a href="/orders?status=partial<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $statusFilter === 'partial' ? 'active' : '' ?>">
        <span>Partial</span>
        <span class="text-[10px] opacity-75 font-bold">(<?= $statusCounts['partial'] ?? 0 ?>)</span>
      </a>
      <a href="/orders?status=canceled<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $statusFilter === 'canceled' ? 'active' : '' ?>">
        <span>Canceled</span>
        <span class="text-[10px] opacity-75 font-bold">(<?= ($statusCounts['canceled'] ?? 0) + ($statusCounts['cancelled'] ?? 0) ?>)</span>
      </a>
    </div>

    <!-- Search Form -->
    <form method="GET" action="/orders" class="flex gap-2">
      <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
      <div class="relative flex-1">
        <i data-lucide="search" class="w-4 h-4 text-[#6C6C8A] absolute left-3.5 top-1/2 -translate-y-1/2"></i>
        <input 
          type="text" 
          name="search" 
          value="<?= e($search) ?>" 
          placeholder="Search by Order ID, URL link, or service name..." 
          class="smm-input pl-9 text-xs"
        >
      </div>
      <button type="submit" class="smm-btn-pink px-5 text-xs">Search</button>
      <?php if (!empty($search)): ?>
        <a href="/orders?status=<?= e($statusFilter) ?>" class="smm-btn-dark px-3 text-xs">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Orders Table -->
  <div class="smm-card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="smm-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Service</th>
            <th>Target Link</th>
            <th>Qty</th>
            <th>Charge</th>
            <th>Start / Remains</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="9" class="text-center py-12 text-[#9D9DB8]">
                <div class="w-12 h-12 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-3 text-[#FF2D78]">
                  <i data-lucide="inbox" class="w-6 h-6"></i>
                </div>
                <div class="font-bold text-white text-sm mb-1">No orders found</div>
                <p class="text-xs text-[#6C6C8A] max-w-sm mx-auto mb-4">You have not placed any orders matching this criteria yet.</p>
                <a href="/order" class="smm-btn-pink px-4 py-2 text-xs">Create New Order</a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $ord): 
              $status = strtolower($ord['status']);
              $badgeClass = 'smm-badge-pending';
              if ($status === 'completed') $badgeClass = 'smm-badge-completed';
              elseif (in_array($status, ['processing', 'in_progress'])) $badgeClass = 'smm-badge-in-progress';
              elseif (in_array($status, ['canceled', 'cancelled', 'failed'])) $badgeClass = 'smm-badge-canceled';
              elseif ($status === 'partial') $badgeClass = 'smm-badge-partial';

              $refillCheck = RefillHelper::isEligible($ord);
              $refillEligible = $refillCheck['eligible'] ?? false;
            ?>
              <tr>
                <td class="font-mono text-xs font-bold text-[#FF2D78]">
                  #<?= (int)$ord['id'] ?>
                </td>
                <td class="max-w-[220px]">
                  <div class="font-bold text-white text-xs truncate" title="<?= e($ord['service_name']) ?>">
                    <?= e($ord['service_name']) ?>
                  </div>
                  <div class="text-[10px] text-[#9D9DB8]">
                    <?= e($ord['category_name']) ?>
                  </div>
                </td>
                <td class="max-w-[160px] truncate text-xs font-mono text-[#9D9DB8]">
                  <a href="<?= e($ord['link']) ?>" target="_blank" class="hover:text-[#FF2D78] hover:underline" rel="noopener noreferrer">
                    <?= e($ord['link']) ?>
                  </a>
                </td>
                <td class="font-semibold text-white">
                  <?= number_format($ord['quantity']) ?>
                </td>
                <td class="font-bold text-white font-mono">
                  <?= format_price($ord['charge']) ?>
                </td>
                <td class="text-xs text-[#9D9DB8]">
                  <span class="text-white"><?= number_format($ord['start_count'] ?? 0) ?></span> / 
                  <span class="text-[#FF2D78]"><?= number_format($ord['remains'] ?? 0) ?></span>
                </td>
                <td>
                  <span class="<?= $badgeClass ?>">
                    <?= ucfirst($ord['status']) ?>
                  </span>
                </td>
                <td class="text-[11px] text-[#9D9DB8] whitespace-nowrap">
                  <?= date('M d, Y H:i', strtotime($ord['created_at'])) ?>
                </td>
                <td>
                  <?php if ($refillEligible): ?>
                    <button 
                      type="button" 
                      onclick="requestOrderRefill(<?= (int)$ord['id'] ?>)" 
                      class="px-2.5 py-1 rounded-lg bg-[#FF2D78]/20 border border-[#FF2D78]/40 text-[#FF2D78] hover:bg-[#FF2D78] hover:text-white transition-colors text-[10px] font-black uppercase tracking-wider"
                    >
                      Refill
                    </button>
                  <?php else: ?>
                    <span class="text-[10px] text-[#6C6C8A]">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function requestOrderRefill(orderId) {
  if (!confirm(`Submit a free refill request for order #${orderId}?`)) return;

  fetch('/api/order/refill', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId })
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message || (data.success ? 'Refill submitted successfully!' : 'Failed to request refill.'));
    if (data.success) window.location.reload();
  })
  .catch(() => alert('Network error submitting refill.'));
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
