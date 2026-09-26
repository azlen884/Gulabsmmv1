<?php
$pageTitle = 'Drip-Feed Orders - SMM Pro';
$activePage = 'drip-feed';
require_once __DIR__ . '/../layouts/header.php';

$db = getDB();
$userId = $user['id'];
$userCurrency = get_user_currency();

$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT d.*, s.name AS service_name, s.category_id, c.name AS category_name
    FROM drip_feed_orders d
    LEFT JOIN services s ON d.service_id = s.id
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE d.user_id = ?
";
$params = [$userId];

if (in_array($statusFilter, ['active', 'completed', 'paused', 'canceled'])) {
    $sql .= " AND d.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY d.id DESC LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$dripOrders = $stmt->fetchAll();

// Counts for filter pills
$counts = ['all' => 0, 'active' => 0, 'completed' => 0, 'paused' => 0];
$cStmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM drip_feed_orders WHERE user_id = ? GROUP BY status");
$cStmt->execute([$userId]);
while ($r = $cStmt->fetch()) {
    if (isset($counts[$r['status']])) {
        $counts[$r['status']] = (int)$r['cnt'];
    }
    $counts['all'] += (int)$r['cnt'];
}
?>

<div class="space-y-6 max-w-6xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="repeat" class="w-6 h-6 text-[#FF2D78]"></i>
        Drip-Feed Automation
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Scheduled, gradual delivery pipeline across custom runs and intervals.</p>
    </div>
    <a href="/order" class="smm-btn-pink px-4 py-2 text-xs">
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>New Drip Order</span>
    </a>
  </div>

  <!-- Filter Pills -->
  <div class="smm-card p-4 flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
    <a href="/drip-feed" class="smm-cat-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">
      <span>All</span> <span class="text-[10px] opacity-75 font-bold">(<?= $counts['all'] ?>)</span>
    </a>
    <a href="/drip-feed?status=active" class="smm-cat-pill <?= $statusFilter === 'active' ? 'active' : '' ?>">
      <span>Active</span> <span class="text-[10px] opacity-75 font-bold">(<?= $counts['active'] ?>)</span>
    </a>
    <a href="/drip-feed?status=completed" class="smm-cat-pill <?= $statusFilter === 'completed' ? 'active' : '' ?>">
      <span>Completed</span> <span class="text-[10px] opacity-75 font-bold">(<?= $counts['completed'] ?>)</span>
    </a>
    <a href="/drip-feed?status=paused" class="smm-cat-pill <?= $statusFilter === 'paused' ? 'active' : '' ?>">
      <span>Paused</span> <span class="text-[10px] opacity-75 font-bold">(<?= $counts['paused'] ?>)</span>
    </a>
  </div>

  <!-- Table -->
  <div class="smm-card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="smm-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Service</th>
            <th>Link</th>
            <th>Runs</th>
            <th>Interval</th>
            <th>Total Qty</th>
            <th>Total Cost</th>
            <th>Status</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dripOrders)): ?>
            <tr>
              <td colspan="9" class="text-center py-10 text-[#9D9DB8]">
                No drip-feed orders found. Enable "Drip-Feed" when submitting an order.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($dripOrders as $d): ?>
              <tr>
                <td class="font-mono text-xs font-bold text-[#FF2D78]">#<?= (int)$d['id'] ?></td>
                <td class="max-w-[200px] truncate text-white font-semibold text-xs"><?= e($d['service_name']) ?></td>
                <td class="max-w-[150px] truncate text-xs font-mono text-[#9D9DB8]"><?= e($d['link']) ?></td>
                <td class="text-xs text-white"><?= (int)$d['runs_completed'] ?> / <?= (int)$d['runs_total'] ?></td>
                <td class="text-xs text-[#9D9DB8]"><?= (int)$d['interval_minutes'] ?> min</td>
                <td class="font-semibold text-white"><?= number_format($d['total_quantity']) ?></td>
                <td class="font-bold text-[#FF2D78] font-mono"><?= format_price($d['total_charge']) ?></td>
                <td>
                  <span class="smm-badge-<?= $d['status'] === 'completed' ? 'completed' : ($d['status'] === 'active' ? 'in-progress' : 'pending') ?>">
                    <?= ucfirst($d['status']) ?>
                  </span>
                </td>
                <td class="text-xs text-[#9D9DB8]"><?= date('M d, H:i', strtotime($d['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
