<?php
$pageTitle = 'Transactions - RoseSMM';
$activePage = 'wallet';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = $user['id'];

$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : 'all';

$sql = "SELECT * FROM transactions WHERE user_id = ?";
$params = [$userId];

if ($typeFilter !== 'all' && !empty($typeFilter)) {
    $sql .= " AND type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Transactions Log</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Review all your wallet deposits, order deductions, and bonuses.</p>
  </div>
  <a href="/add-funds" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs sm:text-sm shadow-sm transition-colors">
    <i data-lucide="plus" class="w-4 h-4"></i>
    <span>Add Funds</span>
  </a>
</div>

<!-- Type Filter Tabs -->
<div class="bg-white p-3 rounded-2xl border border-[#FCE4E8] shadow-sm mb-6 flex items-center gap-2 overflow-x-auto custom-scrollbar">
  <a href="/transactions" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'all' ? 'bg-rose-500 text-white' : 'bg-rose-50 text-slate-600 hover:bg-rose-100' ?>">All Types</a>
  <a href="/transactions?type=deposit" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'deposit' ? 'bg-rose-500 text-white' : 'bg-rose-50 text-slate-600 hover:bg-rose-100' ?>">Deposits</a>
  <a href="/transactions?type=bonus" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'bonus' ? 'bg-rose-500 text-white' : 'bg-rose-50 text-slate-600 hover:bg-rose-100' ?>">Bonuses</a>
  <a href="/transactions?type=order" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'order' ? 'bg-rose-500 text-white' : 'bg-rose-50 text-slate-600 hover:bg-rose-100' ?>">Order Charges</a>
</div>

<!-- Responsive Card List (NO TABLE UI!) -->
<?php if (empty($transactions)): ?>
  <div class="bg-white p-12 rounded-3xl border border-[#FCE4E8] text-center max-w-md mx-auto">
    <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-4">
      <i data-lucide="receipt" class="w-8 h-8"></i>
    </div>
    <h3 class="text-base font-bold text-slate-800 mb-1">No Transactions Found</h3>
    <p class="text-xs text-slate-500 mb-4">There are no records matching your filter.</p>
  </div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($transactions as $t): ?>
      <div class="bg-white rounded-2xl border border-[#FCE4E8] p-4 sm:p-5 shadow-sm hover:border-rose-300 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
          <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 <?= $t['type'] === 'deposit' || $t['type'] === 'bonus' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
            <i data-lucide="<?= $t['type'] === 'deposit' ? 'arrow-down-left' : ($t['type'] === 'bonus' ? 'gift' : 'shopping-cart') ?>" class="w-5 h-5"></i>
          </div>
          <div>
            <div class="font-bold text-xs sm:text-sm text-slate-800">
              <?= e($t['payment_method'] ?: ucfirst($t['type'])) ?>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
              <span><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></span>
              <?php if (!empty($t['transaction_id'])): ?>
                • <span class="font-mono text-slate-500">ID: <?= e($t['transaction_id']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between sm:justify-end gap-6 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
          <div class="text-right">
            <div class="text-sm sm:text-base font-extrabold <?= $t['type'] === 'deposit' || $t['type'] === 'bonus' ? 'text-emerald-600' : 'text-slate-800' ?>">
              <?= $t['type'] === 'deposit' || $t['type'] === 'bonus' ? '+' : '-' ?><?= format_price($t['amount']) ?>
            </div>
          </div>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $t['status'] === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?>">
            <?= ucfirst($t['status']) ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
