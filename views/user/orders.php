<?php
$pageTitle = 'Order History - RoseSMM';
$activePage = 'orders';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = $user['id'];

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT o.*, s.name AS service_name, c.name AS category_name, c.slug AS category_slug
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

$sql .= " ORDER BY o.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Order History</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Track all your submitted orders in real time.</p>
  </div>
  <a href="/order" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs sm:text-sm shadow-sm transition-colors">
    <i data-lucide="plus" class="w-4 h-4"></i>
    <span>New Order</span>
  </a>
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
          'in_progress' => 'In Progress',
          'completed' => 'Completed',
          'canceled' => 'Canceled'
      ];
      foreach ($tabs as $key => $lbl): ?>
        <a 
          href="/orders?status=<?= $key ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
          class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors <?= $statusFilter === $key ? 'bg-rose-500 text-white' : 'bg-rose-50 text-slate-600 hover:bg-rose-100' ?>"
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
        class="w-full pl-10 pr-4 py-2 bg-rose-50/20 border border-[#FCE4E8] rounded-full text-xs focus:outline-none focus:border-rose-400"
      >
    </form>
  </div>
</div>

<!-- Responsive Card List (NO TABLE UI!) -->
<?php if (empty($orders)): ?>
  <div class="bg-white p-12 rounded-3xl border border-[#FCE4E8] text-center max-w-md mx-auto">
    <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-4">
      <i data-lucide="clock" class="w-8 h-8"></i>
    </div>
    <h3 class="text-base font-bold text-slate-800 mb-1">No Orders Found</h3>
    <p class="text-xs text-slate-500 mb-4">You have not placed any orders matching this filter.</p>
    <a href="/order" class="inline-block px-5 py-2.5 rounded-full bg-rose-500 text-white text-xs font-bold shadow-sm">Place an Order</a>
  </div>
<?php else: ?>
  <div class="space-y-4">
    <?php foreach ($orders as $ord): ?>
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-4 sm:p-5 shadow-sm hover:border-rose-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4 overflow-hidden">
        <!-- Left: Order Details & Service Name -->
        <div class="flex items-start gap-3 sm:gap-4 min-w-0 flex-1">
          <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5">
            #<?= $ord['id'] ?>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 truncate max-w-[150px]">
                <?= e($ord['category_name']) ?>
              </span>
              <span class="text-xs text-slate-400 whitespace-nowrap">
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

        <!-- Right: Metrics & Status Badge -->
        <div class="flex items-center justify-between md:justify-end gap-4 sm:gap-6 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0 flex-wrap sm:flex-nowrap">
          <div class="text-left md:text-right min-w-[70px]">
            <div class="text-xs text-slate-400">Quantity</div>
            <div class="text-sm font-extrabold text-slate-800"><?= number_format($ord['quantity']) ?></div>
          </div>

          <div class="text-left md:text-right min-w-[70px]">
            <div class="text-xs text-slate-400">Charge</div>
            <div class="text-sm font-extrabold text-rose-600"><?= format_price($ord['charge']) ?></div>
          </div>

          <div class="text-right shrink-0">
            <?php
            $badgeBg = 'bg-blue-50 text-blue-600';
            if ($ord['status'] === 'completed') $badgeBg = 'bg-emerald-50 text-emerald-600';
            elseif ($ord['status'] === 'pending') $badgeBg = 'bg-amber-50 text-amber-600';
            elseif ($ord['status'] === 'canceled') $badgeBg = 'bg-rose-50 text-rose-600';
            ?>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap <?= $badgeBg ?>">
              <?= ucfirst(str_replace('_', ' ', $ord['status'])) ?>
            </span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
