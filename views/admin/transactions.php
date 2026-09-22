<?php
require_once __DIR__ . '/../../config/database.php';

// Verify admin authentication
if (!is_admin()) {
    header("Location: /admin/login");
    exit;
}

$db = getDB();
$msg = '';
$err = '';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $txnId = (int)($_POST['transaction_id'] ?? 0);

    try {
        if ($action === 'approve_transaction') {
            $db->beginTransaction();

            $tStmt = $db->prepare("SELECT * FROM transactions WHERE id = ? FOR UPDATE");
            $tStmt->execute([$txnId]);
            $txn = $tStmt->fetch();

            if (!$txn) {
                throw new Exception("Transaction #$txnId not found.");
            }

            if ($txn['status'] === 'completed') {
                throw new Exception("Transaction #$txnId is already marked as completed.");
            }

            $userId = (int)$txn['user_id'];
            $amount = (float)$txn['amount'];

            // Get deposit bonus
            $bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
            $bonusAmount = round(($bonusPercent / 100.0) * $amount, 2);
            $totalCredit = $amount + $bonusAmount;

            // Credit user wallet
            $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalCredit, $userId]);

            // Update transaction record
            $db->prepare("
                UPDATE transactions 
                SET status = 'completed', updated_at = NOW(), gateway_response = CONCAT(IFNULL(gateway_response, ''), ' | Approved by Admin on ', NOW())
                WHERE id = ?
            ")->execute([$txnId]);

            // Log bonus if any
            if ($bonusAmount > 0) {
                $db->prepare("
                    INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id, created_at)
                    VALUES (?, ?, 'bonus', 'Deposit Bonus (10%)', 'completed', ?, NOW())
                ")->execute([$userId, $bonusAmount, 'BONUS-' . $txn['transaction_id']]);
            }

            // Send notification
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Deposit Approved', ?, 'wallet')
            ")->execute([
                $userId,
                "Your deposit of \$$amount via {$txn['payment_method']} was approved by administration and credited to your wallet with \$$bonusAmount bonus."
            ]);

            $db->commit();
            $msg = "Transaction #$txnId successfully APPROVED! User wallet credited with $" . number_format($totalCredit, 2) . ".";
        } elseif ($action === 'reject_transaction') {
            $reason = trim($_POST['reject_reason'] ?? 'Payment verification failed or invalid reference provided.');
            
            $db->prepare("
                UPDATE transactions 
                SET status = 'failed', updated_at = NOW(), gateway_response = CONCAT(IFNULL(gateway_response, ''), ' | Rejected: ', ?)
                WHERE id = ?
            ")->execute([$reason, $txnId]);

            // Get user id
            $uid = $db->query("SELECT user_id FROM transactions WHERE id = $txnId")->fetchColumn();
            if ($uid) {
                $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'Deposit Rejected', ?, 'wallet')
                ")->execute([$uid, "Your deposit request #$txnId was rejected: $reason"]);
            }

            $msg = "Transaction #$txnId marked as REJECTED in MySQL.";
        } elseif ($action === 'delete_transaction') {
            $db->prepare("DELETE FROM transactions WHERE id = ?")->execute([$txnId]);
            $msg = "Transaction record #$txnId deleted from MySQL.";
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $err = "Action failed: " . $e->getMessage();
    }
}

$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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

if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR t.transaction_id LIKE ? OR t.id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = is_numeric($search) ? (int)$search : 0;
}

$sql .= " ORDER BY t.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Count pending deposits
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending' AND type = 'deposit'")->fetchColumn();

$pageTitle = 'Transactions Log - Admin Console';
$adminPage = 'transactions';
require_once __DIR__ . '/../layouts/admin_header.php';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Payments & Ledger</h1>
    <p class="text-xs text-slate-500 mt-1">Review real payment gateway transactions, verify manual deposits, and audit customer wallets.</p>
  </div>
  <div class="flex items-center gap-2">
    <a href="/admin/payment-gateways" class="px-4 py-2.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5">
      <i data-lucide="credit-card" class="w-4 h-4 text-rose-500"></i>
      <span>Gateway Settings</span>
    </a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 shrink-0 text-emerald-600"></i>
      <span><?= e($msg) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 font-bold text-sm">✕</button>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-600"></i>
      <span><?= e($err) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 font-bold text-sm">✕</button>
  </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
  <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-1 md:pb-0">
    <a href="/admin/transactions" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'all' && $statusFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All</a>
    
    <a href="/admin/transactions?status=pending" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors flex items-center gap-1.5 <?= $statusFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' ?>">
      <span>Pending Verification</span>
      <?php if ($pendingCount > 0): ?>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black <?= $statusFilter === 'pending' ? 'bg-white text-amber-600' : 'bg-amber-500 text-white' ?>"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>

    <a href="/admin/transactions?type=deposit" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'deposit' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Deposits</a>
    <a href="/admin/transactions?type=order" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'order' ? 'bg-rose-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Order Charges</a>
    <a href="/admin/transactions?type=bonus" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors <?= $typeFilter === 'bonus' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Bonuses</a>
  </div>

  <form method="GET" class="flex items-center gap-2">
    <?php if ($typeFilter !== 'all'): ?>
      <input type="hidden" name="type" value="<?= e($typeFilter) ?>">
    <?php endif; ?>
    <?php if ($statusFilter !== 'all'): ?>
      <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
    <?php endif; ?>
    <div class="relative w-full md:w-56">
      <input 
        type="text" 
        name="search" 
        value="<?= e($search) ?>" 
        placeholder="Search user, ref ID..." 
        class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-400"
      >
      <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2"></i>
    </div>
    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-slate-800 text-white text-xs font-bold shrink-0">Search</button>
  </form>
</div>

<!-- Transaction Card List (NO TABLE UI!) -->
<?php if (empty($transactions)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <p class="text-xs text-slate-400">No transactions found matching your criteria.</p>
  </div>
<?php else: ?>
  <div class="space-y-3.5">
    <?php foreach ($transactions as $t): 
      $isDeposit = ($t['type'] === 'deposit');
      $isBonus = ($t['type'] === 'bonus');
      $isPending = ($t['status'] === 'pending');
      $isCompleted = ($t['status'] === 'completed');
    ?>
      <div class="bg-white rounded-3xl border <?= $isPending ? 'border-amber-300 ring-2 ring-amber-100' : 'border-slate-200' ?> p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between gap-4">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
          <!-- Left: User & Transaction Info -->
          <div class="flex items-start gap-3.5 min-w-0">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center font-bold text-xs shrink-0 <?= $isDeposit || $isBonus ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
              <i data-lucide="<?= $isDeposit ? 'arrow-down-left' : ($isBonus ? 'gift' : 'shopping-cart') ?>" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-extrabold text-sm text-slate-900">@<?= e($t['username']) ?></span>
                <span class="text-slate-300">•</span>
                <span class="text-xs font-bold text-slate-600"><?= e($t['payment_method']) ?></span>
                <?php if (!empty($t['gateway_code'])): ?>
                  <span class="px-2 py-0.2 rounded-md bg-slate-100 font-mono text-[10px] text-slate-500 uppercase">
                    <?= e($t['gateway_code']) ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-2 flex-wrap">
                <span><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></span>
                <?php if (!empty($t['transaction_id'])): ?>
                  <span>•</span>
                  <span class="font-mono text-slate-600 font-bold">Ref: <?= e($t['transaction_id']) ?></span>
                <?php endif; ?>
                <span>•</span>
                <span class="text-slate-400 font-mono">ID: #<?= $t['id'] ?></span>
              </div>

              <!-- Notes / Customer Reference info if present -->
              <?php if (!empty($t['gateway_response'])): ?>
                <div class="mt-2 p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-[11px] text-slate-600 font-mono break-all max-w-xl">
                  <?= e($t['gateway_response']) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Right: Amount & Status Badge -->
          <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-2 shrink-0">
            <div class="text-base sm:text-lg font-black <?= $isDeposit || $isBonus ? 'text-emerald-600' : 'text-slate-800' ?>">
              <?= $isDeposit || $isBonus ? '+' : '-' ?>$<?= number_format($t['amount'], 2) ?>
            </div>

            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $isCompleted ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : ($isPending ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-500 border border-slate-200') ?>">
              <?= ucfirst($t['status']) ?>
            </span>
          </div>
        </div>

        <!-- Admin Management Controls for Pending Deposits -->
        <?php if ($isPending): ?>
          <div class="pt-3 border-t border-amber-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-amber-50/50 -mx-5 -mb-5 p-4 rounded-b-3xl">
            <div class="text-xs text-amber-900 font-medium flex items-center gap-1.5">
              <i data-lucide="clock" class="w-4 h-4 text-amber-600 shrink-0"></i>
              <span>This deposit is awaiting verification. Inspect the reference before approving.</span>
            </div>
            
            <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
              <!-- Reject Form -->
              <form method="POST" onsubmit="return confirm('Reject this deposit request?');">
                <input type="hidden" name="action" value="reject_transaction">
                <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 font-bold text-xs transition-colors">
                  Reject
                </button>
              </form>

              <!-- Approve Form -->
              <form method="POST" onsubmit="return confirm('Approve this deposit and credit $<?= number_format($t['amount'], 2) ?> to @<?= e($t['username']) ?>?');">
                <input type="hidden" name="action" value="approve_transaction">
                <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
                  <i data-lucide="check" class="w-3.5 h-3.5 stroke-[3]"></i>
                  <span>Approve & Credit Wallet</span>
                </button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <!-- Completed/Failed actions -->
          <div class="pt-2 border-t border-slate-100 flex items-center justify-end">
            <form method="POST" onsubmit="return confirm('Delete this transaction log from MySQL?');">
              <input type="hidden" name="action" value="delete_transaction">
              <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
              <button type="submit" class="text-slate-400 hover:text-rose-600 text-[11px] font-semibold flex items-center gap-1 transition-colors">
                <i data-lucide="trash-2" class="w-3 h-3"></i>
                <span>Delete Log</span>
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
