<?php
$pageTitle = 'Transactions Log - Admin Console';
$adminPage = 'transactions';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();

$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : 'all';

$sql = "
    SELECT t.*, u.username, u.email 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE 1=1
";
$params = [];

if ($typeFilter !== 'all' && !empty($typeFilter)) {
    $sql .= " AND t.type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY t.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Payments & Ledger</h1>
    <p class="text-xs text-slate-500 mt-1">Audit user deposits, order debits, bonuses, and manual credits.</p>
  </div>
</div>

<!-- Filter Tabs -->
<div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-sm mb-6 flex items-center gap-2 overflow-x-auto custom-scrollbar">
  <a href="/admin/transactions" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All</a>
  <a href="/admin/transactions?type=deposit" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'deposit' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Deposits</a>
  <a href="/admin/transactions?type=order" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'order' ? 'bg-rose-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Order Charges</a>
  <a href="/admin/transactions?type=bonus" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'bonus' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Bonuses</a>
</div>

<!-- Transaction Card List (NO TABLE UI!) -->
<?php if (empty($transactions)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <p class="text-xs text-slate-400">No transactions recorded.</p>
  </div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($transactions as $t): ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
          <div class="w-11 h-11 rounded-2xl flex items-center justify-center font-bold text-xs shrink-0 <?= $t['type'] === 'deposit' || $t['type'] === 'bonus' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
            <i data-lucide="<?= $t['type'] === 'deposit' ? 'arrow-down-left' : ($t['type'] === 'bonus' ? 'gift' : 'shopping-cart') ?>" class="w-5 h-5"></i>
          </div>
          <div>
            <div class="font-bold text-xs sm:text-sm text-slate-800">
              User: <span class="text-rose-600 font-extrabold">@<?= e($t['username']) ?></span> • <?= e($t['payment_method']) ?>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
              <span><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></span>
              <?php if (!empty($t['transaction_id'])): ?>
                • <span class="font-mono text-slate-500">Ref: <?= e($t['transaction_id']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between sm:justify-end gap-6 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
          <div class="text-right">
            <div class="text-sm sm:text-base font-extrabold <?= $t['type'] === 'deposit' || $t['type'] === 'bonus' ? 'text-emerald-600' : 'text-slate-800' ?>">
              <?= $t['type'] === 'deposit' || $t['type'] === 'bonus' ? '+' : '-' ?>$<?= number_format($t['amount'], 2) ?>
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

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
