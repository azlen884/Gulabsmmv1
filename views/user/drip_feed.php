<?php
$pageTitle = 'Drip-Feed Orders - RoseSMM';
$activePage = 'drip-feed';
require_once __DIR__ . '/../layouts/user_header.php';

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
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 border border-rose-200/60 text-rose-600 text-xs font-bold mb-2">
        <i data-lucide="repeat" class="w-3.5 h-3.5"></i> Gradual Delivery Pipeline
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Drip-Feed Orders
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Monitor your automated scheduled deliveries distributed over time.
      </p>
    </div>

    <a href="/order" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors">
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>New Drip-Feed Order</span>
    </a>
  </div>

  <!-- Status Filter Tabs -->
  <div class="flex flex-wrap items-center gap-2">
    <?php foreach (['all' => 'All Orders', 'active' => 'Active Runs', 'completed' => 'Completed', 'paused' => 'Paused'] as $stKey => $label): ?>
      <a 
        href="/drip-feed<?= $stKey !== 'all' ? '?status=' . $stKey : '' ?>" 
        class="px-4 py-2 rounded-xl text-xs font-bold transition-colors <?= $statusFilter === $stKey ? 'bg-rose-600 text-white shadow-sm' : 'bg-white border border-[#FCE4E8] text-slate-600 hover:bg-rose-50/50' ?>"
      >
        <?= $label ?> (<?= $counts[$stKey] ?? 0 ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Drip Feed Orders List -->
  <?php if (empty($dripOrders)): ?>
    <div class="bg-white rounded-3xl border border-[#FCE4E8] p-12 text-center shadow-sm">
      <div class="w-16 h-16 rounded-3xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center border border-rose-100 mb-4">
        <i data-lucide="repeat" class="w-8 h-8"></i>
      </div>
      <h3 class="text-base font-black text-slate-800 mb-1">No Drip-Feed Orders Found</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto mb-5">
        Drip-feed allows you to build organic-looking growth by breaking a large order into smaller runs delivered automatically at your chosen intervals.
      </p>
      <a href="/order" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition-colors">
        Create Your First Drip-Feed Order
      </a>
    </div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($dripOrders as $drip): ?>
        <?php
        // Fetch batches for this drip feed
        $bStmt = $db->prepare("SELECT * FROM drip_feed_batches WHERE drip_feed_id = ? ORDER BY run_number ASC");
        $bStmt->execute([$drip['id']]);
        $batches = $bStmt->fetchAll();
        $progressPercent = min(100, round(((int)$drip['current_run'] / max(1, (int)$drip['runs'])) * 100));
        ?>
        <div class="bg-white rounded-3xl border border-[#FCE4E8] p-5 shadow-sm space-y-4">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div class="flex items-center gap-3">
              <span class="text-sm font-black text-rose-600">Drip #<?= $drip['id'] ?></span>
              <span class="text-slate-300">·</span>
              <span class="text-xs font-bold text-slate-700"><?= e($drip['service_name'] ?: 'Service #' . $drip['service_id']) ?></span>
            </div>
            <div class="flex items-center gap-2">
              <?php if ($drip['status'] === 'active'): ?>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active
                </span>
              <?php elseif ($drip['status'] === 'completed'): ?>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                  Completed
                </span>
              <?php elseif ($drip['status'] === 'paused'): ?>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                  Paused
                </span>
              <?php else: ?>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                  <?= ucfirst($drip['status']) ?>
                </span>
              <?php endif; ?>
              <span class="text-xs font-black text-slate-800"><?= format_price($drip['total_charge'], $userCurrency, 'USD') ?></span>
            </div>
          </div>

          <!-- Drip Metrics Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Runs Progress</span>
              <span class="font-bold text-slate-800"><?= $drip['current_run'] ?> of <?= $drip['runs'] ?> runs</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Quantity / Run</span>
              <span class="font-bold text-slate-800"><?= number_format($drip['quantity_per_run']) ?> (Total: <?= number_format($drip['total_quantity']) ?>)</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Interval</span>
              <span class="font-bold text-slate-800">Every <?= $drip['interval_minutes'] ?> mins</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Target Link</span>
              <a href="<?= e($drip['link']) ?>" target="_blank" class="font-semibold text-rose-500 hover:text-rose-600 truncate block">
                <?= e($drip['link']) ?>
              </a>
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="space-y-1">
            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500">
              <span>Overall Completion</span>
              <span><?= $progressPercent ?>%</span>
            </div>
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-rose-500 transition-all duration-500 rounded-full" style="width: <?= $progressPercent ?>%;"></div>
            </div>
          </div>

          <!-- Expand Batches Accordion -->
          <details class="group pt-2">
            <summary class="flex items-center justify-between text-xs font-bold text-rose-500 hover:text-rose-600 cursor-pointer select-none">
              <span>View <?= count($batches) ?> Scheduled Batches Breakdown</span>
              <i data-lucide="chevron-down" class="w-4 h-4 transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="mt-3 overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
                    <th class="py-2 px-2">Run</th>
                    <th class="py-2 px-2">Scheduled At</th>
                    <th class="py-2 px-2">Executed At</th>
                    <th class="py-2 px-2">Quantity</th>
                    <th class="py-2 px-2">Child Order</th>
                    <th class="py-2 px-2">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($batches as $batch): ?>
                    <tr>
                      <td class="py-2.5 px-2 font-bold text-slate-700">#<?= $batch['run_number'] ?></td>
                      <td class="py-2.5 px-2 text-slate-500"><?= date('M d, H:i', strtotime($batch['scheduled_at'])) ?></td>
                      <td class="py-2.5 px-2 text-slate-500">
                        <?= !empty($batch['executed_at']) ? date('M d, H:i', strtotime($batch['executed_at'])) : '-' ?>
                      </td>
                      <td class="py-2.5 px-2 font-semibold text-slate-800"><?= number_format($batch['quantity']) ?></td>
                      <td class="py-2.5 px-2">
                        <?php if (!empty($batch['order_id'])): ?>
                          <a href="/orders?id=<?= $batch['order_id'] ?>" class="font-bold text-rose-500 hover:underline">
                            #<?= $batch['order_id'] ?>
                          </a>
                        <?php else: ?>
                          <span class="text-slate-400">Pending Run</span>
                        <?php endif; ?>
                      </td>
                      <td class="py-2.5 px-2">
                        <?php if ($batch['status'] === 'completed'): ?>
                          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Completed</span>
                        <?php elseif ($batch['status'] === 'processing'): ?>
                          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">Processing</span>
                        <?php else: ?>
                          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Pending</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
