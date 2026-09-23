<?php
$pageTitle = 'Orders Manager - Admin Console';
$adminPage = 'orders';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/RefundHelper.php';

$db = getDB();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = (int)$_POST['order_id'];
    $newStatus = trim($_POST['status']);
    $allowed = ['pending', 'processing', 'in_progress', 'completed', 'partial', 'cancelled'];
    if (in_array($newStatus, $allowed)) {
        $db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
        $refundRes = RefundHelper::handleOrderStateChange($orderId, $newStatus);
        $msg = "Order #$orderId status updated to $newStatus.";
        if (!empty($refundRes['refunded'])) {
            $msg .= " Auto-refund of \${$refundRes['amount']} credited to customer wallet.";
        }
    }
}

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT o.*, u.username, u.email, s.name AS service_name, c.name AS category_name
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN services s ON o.service_id = s.id 
    JOIN categories c ON s.category_id = c.id 
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (o.id = ? OR o.link LIKE ? OR u.username LIKE ?)";
    $params[] = is_numeric($search) ? (int)$search : 0;
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
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Order Management</h1>
    <p class="text-xs text-slate-500 mt-1">Review, filter, and modify order execution statuses across all accounts.</p>
  </div>
</div>

<?php if (isset($msg)): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<!-- Search & Filters -->
<div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
  <!-- Status Filters -->
  <div class="flex items-center gap-1.5 overflow-x-auto custom-scrollbar">
    <a href="/admin/orders" class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap <?= $statusFilter === 'all' ? 'bg-rose-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All</a>
    <a href="/admin/orders?status=pending" class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap <?= $statusFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Pending</a>
    <a href="/admin/orders?status=processing" class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap <?= $statusFilter === 'processing' ? 'bg-blue-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Processing</a>
    <a href="/admin/orders?status=completed" class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap <?= $statusFilter === 'completed' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Completed</a>
    <a href="/admin/orders?status=cancelled" class="px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap <?= $statusFilter === 'cancelled' ? 'bg-red-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Cancelled</a>
  </div>

  <!-- Search input -->
  <form method="GET" class="relative max-w-xs w-full">
    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
    <input 
      type="text" 
      name="search" 
      value="<?= e($search) ?>" 
      placeholder="Search Order ID, Link, User..." 
      class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-400"
    >
    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
  </form>
</div>

<!-- Order Cards (NO TABLE UI!) -->
<?php if (empty($orders)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
      <i data-lucide="inbox" class="w-6 h-6"></i>
    </div>
    <div class="font-bold text-sm text-slate-700">No Orders Found</div>
    <div class="text-xs text-slate-400 mt-1">There are no orders matching this filter.</div>
  </div>
<?php else: ?>
  <div class="space-y-4">
    <?php foreach ($orders as $o): ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-4 sm:p-6 shadow-sm hover:border-slate-300 transition-all flex flex-col lg:flex-row lg:items-center justify-between gap-6 overflow-hidden">
        <!-- Order Primary Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 sm:gap-3 mb-2 flex-wrap">
            <span class="px-3 py-1 rounded-xl bg-slate-100 font-mono font-bold text-xs text-slate-800 shrink-0">
              #<?= $o['id'] ?>
            </span>
            <span class="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-0.5 rounded-full truncate max-w-[160px]">
              <?= e($o['category_name']) ?>
            </span>

            <?php if (!empty($o['is_dripfeed'])): ?>
              <a href="/admin/drip-feed?search=<?= $o['id'] ?>" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 inline-flex items-center gap-1">
                <i data-lucide="repeat" class="w-3 h-3"></i> Drip-Feed
              </a>
            <?php endif; ?>

            <?php if ((float)($o['discount_amount'] ?? 0) > 0): ?>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200 inline-flex items-center gap-1">
                <i data-lucide="tag" class="w-3 h-3"></i> -$<?= number_format($o['discount_amount'], 4) ?>
              </span>
            <?php endif; ?>

            <?php if (in_array($o['refill_status'] ?? '', ['pending', 'processing', 'completed'])): ?>
              <a href="/admin/refill?search=<?= $o['id'] ?>" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                <i data-lucide="refresh-cw" class="w-3 h-3"></i> Refill: <?= ucfirst($o['refill_status']) ?>
              </a>
            <?php endif; ?>

            <?php if (in_array($o['refund_status'] ?? '', ['refunded', 'partial_refunded'])): ?>
              <a href="/admin/refunds?search=<?= $o['id'] ?>" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 inline-flex items-center gap-1">
                <i data-lucide="wallet" class="w-3 h-3"></i> Refunded: $<?= number_format($o['refunded_amount'], 4) ?>
              </a>
            <?php endif; ?>

            <span class="text-xs text-slate-400 whitespace-nowrap ml-auto">
              <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?>
            </span>
          </div>

          <h3 class="font-bold text-sm sm:text-base text-slate-800 mb-2 break-words">
            <?= e($o['service_name']) ?>
          </h3>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 py-3 border-y border-slate-100 text-xs">
            <div class="min-w-0">
              <span class="text-slate-400 block text-[11px]">Customer:</span>
              <span class="font-bold text-slate-800 truncate block">@<?= e($o['username']) ?></span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[11px]">Quantity:</span>
              <span class="font-bold text-slate-800 truncate block"><?= number_format($o['quantity']) ?></span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[11px]">Charge:</span>
              <span class="font-bold text-emerald-600 truncate block">$<?= number_format($o['charge'], 4) ?></span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[11px]">Start / Remains:</span>
              <span class="font-bold text-slate-800 truncate block"><?= $o['start_count'] ?> / <?= $o['remains'] ?></span>
            </div>
          </div>

          <div class="mt-2 text-xs flex items-center gap-1.5 text-slate-500 min-w-0">
            <span class="font-semibold text-slate-400 shrink-0">Target Link:</span>
            <a href="<?= e($o['link']) ?>" target="_blank" class="text-rose-500 hover:underline font-mono truncate min-w-0 flex-1 break-all">
              <?= e($o['link']) ?>
            </a>
          </div>
        </div>

        <!-- Order Action Controls -->
        <div class="flex flex-col sm:flex-row lg:flex-col items-start lg:items-end justify-between gap-3 shrink-0 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100 w-full sm:w-auto">
          <form method="POST" class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <select name="status" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:outline-none focus:border-rose-400">
              <option value="pending" <?= $o['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="processing" <?= $o['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
              <option value="in_progress" <?= $o['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="completed" <?= $o['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="partial" <?= $o['status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
              <option value="cancelled" <?= $o['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors shrink-0 whitespace-nowrap">
              Update
            </button>
          </form>

          <span class="px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap <?= $o['status'] === 'completed' ? 'bg-emerald-50 text-emerald-600' : ($o['status'] === 'pending' ? 'bg-amber-50 text-amber-600' : ($o['status'] === 'cancelled' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600')) ?>">
            Status: <?= ucfirst($o['status']) ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
