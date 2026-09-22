<?php
$pageTitle = 'Users Manager - Admin Console';
$adminPage = 'users';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();

$msg = '';
$err = '';

// Handle balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'adjust_balance') {
        $targetUserId = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        $type = $_POST['type'] ?? 'add'; // 'add' or 'deduct'

        if ($targetUserId > 0 && $amount > 0) {
            if ($type === 'add') {
                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $targetUserId]);
                $db->prepare("INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id) VALUES (?, ?, 'deposit', 'Admin Manual Credit', 'completed', ?)")
                    ->execute([$targetUserId, $amount, 'ADM-ADD-' . time()]);
                $msg = "Added $" . number_format($amount, 2) . " to user ID #$targetUserId.";
            } else {
                $db->prepare("UPDATE users SET balance = GREATEST(0, balance - ?) WHERE id = ?")->execute([$amount, $targetUserId]);
                $db->prepare("INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id) VALUES (?, ?, 'order', 'Admin Manual Debit', 'completed', ?)")
                    ->execute([$targetUserId, $amount, 'ADM-SUB-' . time()]);
                $msg = "Deducted $" . number_format($amount, 2) . " from user ID #$targetUserId.";
            }
        }
    } elseif ($action === 'toggle_status') {
        $targetUserId = (int)$_POST['user_id'];
        $newStatus = $_POST['status'] === 'banned' ? 'active' : 'banned';
        $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $targetUserId]);
        $msg = "User status changed to $newStatus.";
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS total_orders,
           (SELECT COALESCE(SUM(charge), 0) FROM orders WHERE user_id = u.id) AS total_spent
    FROM users u
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY u.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">User Management</h1>
    <p class="text-xs text-slate-500 mt-1">Manage accounts, credit/debit balances, and handle account suspensions.</p>
  </div>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<!-- Search Bar -->
<div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm mb-6 flex items-center justify-between">
  <form method="GET" class="relative max-w-sm w-full">
    <input 
      type="text" 
      name="search" 
      value="<?= e($search) ?>" 
      placeholder="Search username, email or name..." 
      class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-400"
    >
    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
  </form>
  <span class="text-xs text-slate-400 font-semibold"><?= count($users) ?> Total Accounts</span>
</div>

<!-- Users Card List (NO TABLE UI!) -->
<?php if (empty($users)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <p class="text-xs text-slate-400">No users found.</p>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($users as $u): ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between overflow-hidden">
        <div class="min-w-0">
          <!-- Top Row: Avatar & Status -->
          <div class="flex items-center justify-between mb-3 gap-3 min-w-0">
            <div class="flex items-center gap-3 min-w-0 flex-1">
              <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 font-bold flex items-center justify-center text-sm shrink-0">
                <?= strtoupper(substr($u['username'], 0, 1)) ?>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="font-bold text-sm text-slate-800 flex items-center gap-2 flex-wrap min-w-0">
                  <span class="break-words"><?= e($u['full_name'] ?: $u['username']) ?></span>
                  <?php if ($u['role'] === 'admin'): ?>
                    <span class="px-2 py-0.5 rounded-full bg-slate-900 text-white text-[10px] font-bold shrink-0">ADMIN</span>
                  <?php endif; ?>
                </h3>
                <div class="text-[11px] text-slate-400 truncate">@<?= e($u['username']) ?> • <?= e($u['email']) ?></div>
              </div>
            </div>

            <form method="POST" class="shrink-0">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="status" value="<?= $u['status'] ?>">
              <button type="submit" class="px-2.5 py-1 rounded-full text-xs font-bold transition-colors whitespace-nowrap <?= $u['status'] === 'active' ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-red-50 text-red-600 hover:bg-red-100' ?>">
                <?= ucfirst($u['status']) ?>
              </button>
            </form>
          </div>

          <!-- Financial & Order Stats Grid -->
          <div class="grid grid-cols-3 gap-2 py-3 border-y border-slate-100 mb-4 text-xs">
            <div class="min-w-0">
              <span class="text-slate-400 block text-[10px]">Balance:</span>
              <span class="font-extrabold text-slate-900 text-sm truncate block">$<?= number_format($u['balance'], 2) ?></span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[10px]">Total Spent:</span>
              <span class="font-bold text-slate-700 text-sm truncate block">$<?= number_format($u['total_spent'], 2) ?></span>
            </div>
            <div class="min-w-0">
              <span class="text-slate-400 block text-[10px]">Orders:</span>
              <span class="font-bold text-slate-700 text-sm truncate block"><?= number_format($u['total_orders']) ?></span>
            </div>
          </div>
        </div>

        <!-- Quick Balance Adjust Form -->
        <form method="POST" class="flex items-center gap-2 pt-2 border-t border-slate-100 text-xs flex-wrap sm:flex-nowrap">
          <input type="hidden" name="action" value="adjust_balance">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <select name="type" class="px-2 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 shrink-0">
            <option value="add">+ Add</option>
            <option value="deduct">- Deduct</option>
          </select>
          <input 
            type="number" 
            name="amount" 
            step="0.01" 
            min="1" 
            placeholder="Amount $" 
            required 
            class="flex-1 min-w-[90px] px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs"
          >
          <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-bold text-[11px] transition-colors shrink-0 whitespace-nowrap">
            Apply
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
