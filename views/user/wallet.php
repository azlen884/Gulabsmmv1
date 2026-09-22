<?php
$pageTitle = 'My Wallet - RoseSMM';
$activePage = 'wallet';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = $user['id'];

// Get user transaction statistics
$totalDepositsStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'deposit' AND status = 'completed'");
$totalDepositsStmt->execute([$userId]);
$totalDeposits = (float)$totalDepositsStmt->fetchColumn();

$totalSpentStmt = $db->prepare("SELECT COALESCE(SUM(charge), 0) FROM orders WHERE user_id = ?");
$totalSpentStmt->execute([$userId]);
$totalSpent = (float)$totalSpentStmt->fetchColumn();

// Get recent transactions
$stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Wallet Overview</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage your funds, deposits, and transaction history.</p>
  </div>
  <a href="/add-funds" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-xs sm:text-sm shadow-sm transition-all">
    <i data-lucide="plus" class="w-4 h-4"></i>
    <span>Add Funds</span>
  </a>
</div>

<!-- Balance Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
  <!-- Active Balance -->
  <div class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-3xl p-6 text-white shadow-sm flex flex-col justify-between">
    <div>
      <span class="text-xs uppercase tracking-wider text-rose-100 font-semibold block mb-2">Available Balance</span>
      <div class="text-3xl sm:text-4xl font-black tracking-tight mb-4"><?= format_price($user['balance']) ?></div>
    </div>
    <div class="flex items-center gap-3">
      <a href="/add-funds" class="px-4 py-2 rounded-full bg-white text-rose-600 font-bold text-xs shadow-sm hover:bg-rose-50 transition-colors">
        + Deposit
      </a>
      <a href="/transactions" class="px-4 py-2 rounded-full bg-rose-700/60 text-white font-bold text-xs hover:bg-rose-700 transition-colors">
        View History
      </a>
    </div>
  </div>

  <!-- Total Deposited -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm flex flex-col justify-between">
    <div class="flex items-center justify-between mb-4">
      <span class="text-xs font-semibold text-slate-400">Total Lifetime Deposited</span>
      <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
        <i data-lucide="arrow-down-left" class="w-5 h-5"></i>
      </div>
    </div>
    <div>
      <div class="text-2xl sm:text-3xl font-bold text-slate-800"><?= format_price($totalDeposits) ?></div>
      <div class="text-xs text-emerald-600 font-semibold mt-1">Verified & Active</div>
    </div>
  </div>

  <!-- Total Spent -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm flex flex-col justify-between">
    <div class="flex items-center justify-between mb-4">
      <span class="text-xs font-semibold text-slate-400">Total Lifetime Spent</span>
      <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center">
        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
      </div>
    </div>
    <div>
      <div class="text-2xl sm:text-3xl font-bold text-slate-800"><?= format_price($totalSpent) ?></div>
      <div class="text-xs text-slate-400 font-semibold mt-1">Across all orders</div>
    </div>
  </div>
</div>

<!-- Transactions Section (NO TABLE UI!) -->
<div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
  <div class="flex items-center justify-between mb-4">
    <h3 class="font-bold text-base text-slate-800">Recent Transactions</h3>
    <a href="/transactions" class="text-xs font-bold text-rose-500 hover:text-rose-600">View All</a>
  </div>

  <?php if (empty($transactions)): ?>
    <div class="text-center py-8 text-xs text-slate-400">No transactions recorded yet.</div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($transactions as $tx): ?>
        <div class="p-4 rounded-2xl border border-slate-100 hover:border-rose-200 transition-all flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 <?= $tx['type'] === 'deposit' || $tx['type'] === 'bonus' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
              <i data-lucide="<?= $tx['type'] === 'deposit' ? 'arrow-down-left' : ($tx['type'] === 'bonus' ? 'gift' : 'arrow-up-right') ?>" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-bold text-xs sm:text-sm text-slate-800 capitalize">
                <?= e($tx['payment_method'] ?: ucfirst($tx['type'])) ?>
              </div>
              <div class="text-[11px] text-slate-400">
                <?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?>
                <?php if (!empty($tx['transaction_id'])): ?>
                  • Ref: <?= e($tx['transaction_id']) ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="text-right">
            <div class="text-sm font-extrabold <?= $tx['type'] === 'deposit' || $tx['type'] === 'bonus' ? 'text-emerald-600' : 'text-slate-800' ?>">
              <?= $tx['type'] === 'deposit' || $tx['type'] === 'bonus' ? '+' : '-' ?><?= format_price($tx['amount']) ?>
            </div>
            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tx['status'] === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?>">
              <?= ucfirst($tx['status']) ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
