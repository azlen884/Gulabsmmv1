<?php
require_once __DIR__ . '/../../config/database.php';

if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$txnId = (int)($_GET['txn_id'] ?? 0);
$gatewayCode = trim($_GET['gateway'] ?? '');

// If transaction ID is provided, update its status to 'cancelled' in MySQL
if ($txnId > 0) {
    $db->prepare("
        UPDATE transactions 
        SET status = 'cancelled', updated_at = NOW() 
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ")->execute([$txnId, $userId]);
}

$pageTitle = 'Payment Cancelled - RoseSMM';
$activePage = 'add-funds';
require_once __DIR__ . '/../layouts/user_header.php';
?>

<div class="max-w-md mx-auto py-8">
  <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-center">
    <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-4 border border-slate-200 shadow-sm">
      <i data-lucide="x-circle" class="w-8 h-8 stroke-[2]"></i>
    </div>

    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold uppercase tracking-wider inline-block mb-2">
      Transaction Cancelled
    </span>

    <h1 class="text-2xl font-black text-slate-800 tracking-tight mb-2">Payment Was Cancelled</h1>
    <p class="text-xs text-slate-500 leading-relaxed mb-6">
      You cancelled the payment process before it completed. No funds were charged to your payment method, and your wallet balance remains unchanged.
    </p>

    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs text-slate-600 text-left mb-6 space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-slate-400">Status:</span>
        <span class="font-bold text-amber-600">Cancelled by User</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-slate-400">Balance Effect:</span>
        <span class="font-bold text-slate-800">$0.00 (No change)</span>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
      <a href="/add-funds" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        <span>Try Another Method</span>
      </a>
      <a href="/wallet" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-2">
        <i data-lucide="wallet" class="w-4 h-4"></i>
        <span>Return to Wallet</span>
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
