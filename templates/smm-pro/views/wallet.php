<?php
$pageTitle = 'My Wallet - SMM Pro';
$activePage = 'wallet';
require_once __DIR__ . '/../layouts/header.php';

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
$stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 15");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Top Bar -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="wallet" class="w-6 h-6 text-[#FF2D78]"></i>
        Wallet & Billing
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Monitor credit balances, audit transaction invoices, and make automated deposits.</p>
    </div>
    <a href="/add-funds" class="smm-btn-pink px-5 py-2.5 text-xs font-bold">
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>Add Funds</span>
    </a>
  </div>

  <!-- Wallet Metric Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <!-- Active Balance Card -->
    <div class="rounded-3xl p-6 bg-gradient-to-br from-[#FF2D78] via-[#D91B5C] to-[#9333EA] text-white shadow-xl shadow-[#FF2D78]/20 flex flex-col justify-between relative overflow-hidden">
      <div class="mb-4">
        <span class="text-xs uppercase tracking-wider text-white/80 font-bold block mb-1">Available Credits</span>
        <div class="text-3xl sm:text-4xl font-black tracking-tight"><?= format_price($user['balance']) ?></div>
      </div>
      <div class="flex items-center gap-2">
        <a href="/add-funds" class="px-4 py-2 rounded-full bg-white text-[#FF2D78] font-bold text-xs shadow-md hover:bg-white/90 transition-colors">
          + Deposit Funds
        </a>
      </div>
    </div>

    <!-- Total Deposited Card -->
    <div class="smm-card p-6 flex flex-col justify-between">
      <div class="flex items-center justify-between mb-4">
        <span class="text-xs uppercase tracking-wider text-[#9D9DB8] font-bold">Lifetime Deposited</span>
        <div class="w-10 h-10 rounded-2xl bg-[#10B981]/15 text-[#34D399] flex items-center justify-center">
          <i data-lucide="arrow-down-left" class="w-5 h-5"></i>
        </div>
      </div>
      <div class="text-2xl sm:text-3xl font-black text-white font-mono"><?= format_price($totalDeposits) ?></div>
    </div>

    <!-- Total Spent Card -->
    <div class="smm-card p-6 flex flex-col justify-between">
      <div class="flex items-center justify-between mb-4">
        <span class="text-xs uppercase tracking-wider text-[#9D9DB8] font-bold">Total Spent</span>
        <div class="w-10 h-10 rounded-2xl bg-purple-500/15 text-purple-400 flex items-center justify-center">
          <i data-lucide="arrow-up-right" class="w-5 h-5"></i>
        </div>
      </div>
      <div class="text-2xl sm:text-3xl font-black text-white font-mono"><?= format_price($totalSpent) ?></div>
    </div>
  </div>

  <!-- Transactions Table -->
  <div class="smm-card overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-white/5 flex items-center justify-between">
      <h2 class="text-base font-black text-white flex items-center gap-2">
        <i data-lucide="history" class="w-5 h-5 text-[#FF2D78]"></i>
        Recent Transactions
      </h2>
      <span class="text-xs text-[#9D9DB8]"><?= count($transactions) ?> record(s)</span>
    </div>

    <div class="overflow-x-auto">
      <table class="smm-table">
        <thead>
          <tr>
            <th>Tx ID</th>
            <th>Type</th>
            <th>Gateway</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transactions)): ?>
            <tr>
              <td colspan="6" class="text-center py-10 text-[#9D9DB8]">
                No transactions recorded yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($transactions as $tx): 
              $st = strtolower($tx['status']);
              $badge = 'smm-badge-pending';
              if ($st === 'completed') $badge = 'smm-badge-completed';
              elseif ($st === 'failed' || $st === 'canceled') $badge = 'smm-badge-canceled';
            ?>
              <tr>
                <td class="font-mono text-xs font-bold text-[#FF2D78]">
                  #<?= (int)$tx['id'] ?>
                </td>
                <td class="capitalize font-semibold text-white">
                  <?= e($tx['type']) ?>
                </td>
                <td class="text-xs text-[#9D9DB8]">
                  <?= e($tx['gateway'] ?? 'Online') ?>
                </td>
                <td class="font-bold text-white font-mono">
                  <?= format_price($tx['amount']) ?>
                </td>
                <td>
                  <span class="<?= $badge ?>">
                    <?= ucfirst($tx['status']) ?>
                  </span>
                </td>
                <td class="text-xs text-[#9D9DB8]">
                  <?= date('M d, Y H:i', strtotime($tx['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
