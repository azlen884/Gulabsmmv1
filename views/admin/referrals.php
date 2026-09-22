<?php
$pageTitle = 'Refer & Earn Management - Admin Console';
$adminPage = 'referrals';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/ReferralHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle Admin Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_referral_settings') {
    $enabled = isset($_POST['referral_system_enabled']) ? '1' : '0';
    $commissionPercent = (float)($_POST['referral_commission_percent'] ?? 5.0);
    $commissionEvent = trim($_POST['referral_commission_event'] ?? 'order');

    if ($commissionPercent < 0 || $commissionPercent > 100) {
        $error = 'Commission percentage must be between 0% and 100%.';
    } elseif (!in_array($commissionEvent, ['order', 'deposit', 'both'], true)) {
        $error = 'Invalid commission event trigger selected.';
    } else {
        set_setting('referral_system_enabled', $enabled);
        set_setting('referral_commission_percent', number_format($commissionPercent, 2, '.', ''));
        set_setting('referral_commission_event', $commissionEvent);
        $msg = 'Refer & Earn configuration saved successfully.';
    }
}

// Read current settings
$isProgramEnabled = ReferralHelper::isProgramEnabled();
$commissionPercent = ReferralHelper::getCommissionPercent();
$commissionEvent = ReferralHelper::getCommissionEvent();

// Real MySQL Admin Statistics
$adminStats = ReferralHelper::getAdminStats();
$totalReferrals = $adminStats['total_referrals'];
$activeReferrers = $adminStats['active_referrers'];
$totalCommissionPaid = $adminStats['total_commission_paid'];
$totalTransactions = $adminStats['total_transactions'];

// Search & Filter
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');

$sql = "
    SELECT rt.*, 
           u_ref.username AS referrer_username, u_ref.email AS referrer_email,
           u_by.username AS referred_username, u_by.email AS referred_email
    FROM referral_transactions rt
    LEFT JOIN users u_ref ON rt.referrer_id = u_ref.id
    LEFT JOIN users u_by ON rt.referred_id = u_by.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u_ref.username LIKE ? OR u_by.username LIKE ? OR rt.source_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterType) && in_array($filterType, ['order', 'deposit'], true)) {
    $sql .= " AND rt.source_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY rt.id DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <div class="flex items-center gap-2 mb-1">
      <h1 class="text-2xl font-black text-slate-800 tracking-tight">Refer & Earn System</h1>
      <?php if ($isProgramEnabled): ?>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          System Active
        </span>
      <?php else: ?>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-200">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
          System Paused
        </span>
      <?php endif; ?>
    </div>
    <p class="text-xs text-slate-500">
      Configure referral rewards, commission percentage rules, and view real-time commission history.
    </p>
  </div>
</div>

<?php if ($msg): ?>
  <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-xs">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-xs">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<!-- Real MySQL Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <!-- Total Referrals -->
  <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0">
      <i data-lucide="users" class="w-6 h-6"></i>
    </div>
    <div class="min-w-0">
      <span class="text-xs font-semibold text-slate-400 block truncate">Total Users Referred</span>
      <div class="text-2xl font-black text-slate-800 tracking-tight"><?= number_format($totalReferrals) ?></div>
      <span class="text-[11px] text-slate-500 font-medium">Platform-wide</span>
    </div>
  </div>

  <!-- Active Referrers -->
  <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
      <i data-lucide="user-check" class="w-6 h-6"></i>
    </div>
    <div class="min-w-0">
      <span class="text-xs font-semibold text-slate-400 block truncate">Active Referrers</span>
      <div class="text-2xl font-black text-slate-800 tracking-tight"><?= number_format($activeReferrers) ?></div>
      <span class="text-[11px] text-indigo-600 font-medium">Inviting members</span>
    </div>
  </div>

  <!-- Total Commission Paid -->
  <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
      <i data-lucide="dollar-sign" class="w-6 h-6"></i>
    </div>
    <div class="min-w-0">
      <span class="text-xs font-semibold text-slate-400 block truncate">Total Commission Paid</span>
      <div class="text-2xl font-black text-slate-800 tracking-tight">$<?= number_format($totalCommissionPaid, 2) ?></div>
      <span class="text-[11px] text-emerald-600 font-medium">Credited to wallets</span>
    </div>
  </div>

  <!-- Total Commission Events -->
  <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex items-center gap-4">
    <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
      <i data-lucide="activity" class="w-6 h-6"></i>
    </div>
    <div class="min-w-0">
      <span class="text-xs font-semibold text-slate-400 block truncate">Commission Records</span>
      <div class="text-2xl font-black text-slate-800 tracking-tight"><?= number_format($totalTransactions) ?></div>
      <span class="text-[11px] text-slate-500 font-medium">Idempotent transactions</span>
    </div>
  </div>
</div>

<!-- Configuration Card -->
<div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-xs mb-8">
  <div class="mb-6">
    <h3 class="text-base font-black text-slate-800 tracking-tight">Refer & Earn Configuration</h3>
    <p class="text-xs text-slate-500 mt-0.5">Toggle the program, set dynamic commission percentages, and pick qualifying events.</p>
  </div>

  <form method="POST" class="space-y-6">
    <input type="hidden" name="action" value="update_referral_settings">

    <!-- 1. Enable / Disable Toggle -->
    <div class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-200">
      <div>
        <span class="text-xs font-bold text-slate-800 block">Refer & Earn System Status</span>
        <span class="text-xs text-slate-500 block">
          When disabled, no new referral commission is credited, and users see a paused notice. Existing records remain safe.
        </span>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
        <input 
          type="checkbox" 
          name="referral_system_enabled" 
          value="1" 
          class="sr-only peer" 
          <?= $isProgramEnabled ? 'checked' : '' ?>
        >
        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
      </label>
    </div>

    <!-- 2. Commission Percentage -->
    <div>
      <label class="block text-xs font-bold text-slate-700 mb-1">Commission Percentage (%)</label>
      <p class="text-[11px] text-slate-500 mb-2">
        Percentage of the qualifying transaction amount that the referrer earns directly into their wallet.
      </p>
      <div class="flex items-center gap-3">
        <div class="relative w-full max-w-xs">
          <input 
            type="number" 
            step="0.01" 
            min="0" 
            max="100" 
            name="referral_commission_percent" 
            id="commission-input"
            value="<?= htmlspecialchars(number_format($commissionPercent, 2, '.', '')) ?>" 
            required 
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-rose-500"
          >
          <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span>
        </div>

        <!-- Quick preset buttons -->
        <div class="flex items-center gap-1.5 flex-wrap">
          <button type="button" onclick="setPresetCommission(1)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 font-bold text-xs transition-colors">1%</button>
          <button type="button" onclick="setPresetCommission(5)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 font-bold text-xs transition-colors">5%</button>
          <button type="button" onclick="setPresetCommission(10)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 font-bold text-xs transition-colors">10%</button>
          <button type="button" onclick="setPresetCommission(15)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 font-bold text-xs transition-colors">15%</button>
          <button type="button" onclick="setPresetCommission(20)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 font-bold text-xs transition-colors">20%</button>
        </div>
      </div>
    </div>

    <!-- 3. Qualifying Event Trigger -->
    <div>
      <label class="block text-xs font-bold text-slate-700 mb-1">Qualifying Event Trigger</label>
      <p class="text-[11px] text-slate-500 mb-2">Select which transactions generate referral commission.</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-xl">
        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-white cursor-pointer transition-colors">
          <input type="radio" name="referral_commission_event" value="order" <?= $commissionEvent === 'order' ? 'checked' : '' ?> class="text-rose-600 focus:ring-rose-500">
          <div>
            <span class="text-xs font-bold text-slate-800 block">Orders Only</span>
            <span class="text-[10px] text-slate-500">When order is placed</span>
          </div>
        </label>

        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-white cursor-pointer transition-colors">
          <input type="radio" name="referral_commission_event" value="deposit" <?= $commissionEvent === 'deposit' ? 'checked' : '' ?> class="text-rose-600 focus:ring-rose-500">
          <div>
            <span class="text-xs font-bold text-slate-800 block">Deposits Only</span>
            <span class="text-[10px] text-slate-500">When funds are added</span>
          </div>
        </label>

        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-white cursor-pointer transition-colors">
          <input type="radio" name="referral_commission_event" value="both" <?= $commissionEvent === 'both' ? 'checked' : '' ?> class="text-rose-600 focus:ring-rose-500">
          <div>
            <span class="text-xs font-bold text-slate-800 block">Both Events</span>
            <span class="text-[10px] text-slate-500">Orders + Deposits</span>
          </div>
        </label>
      </div>
    </div>

    <!-- Submit Button -->
    <div class="pt-2">
      <button 
        type="submit" 
        class="px-6 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs transition-colors flex items-center gap-2 cursor-pointer"
      >
        <i data-lucide="save" class="w-4 h-4"></i>
        <span>Save Refer & Earn Settings</span>
      </button>
    </div>
  </form>
</div>

<!-- Search & Filter Bar for History -->
<div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
  <form method="GET" class="relative max-w-md w-full flex items-center gap-2">
    <div class="relative flex-1">
      <input 
        type="text" 
        name="search" 
        value="<?= e($search) ?>" 
        placeholder="Search referrer, referred user, or reference..." 
        class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-rose-400"
      >
      <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
    </div>

    <select 
      name="type" 
      onchange="this.form.submit()" 
      class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-400"
    >
      <option value="">All Events</option>
      <option value="order" <?= $filterType === 'order' ? 'selected' : '' ?>>Orders</option>
      <option value="deposit" <?= $filterType === 'deposit' ? 'selected' : '' ?>>Deposits</option>
    </select>
  </form>

  <span class="text-xs text-slate-400 font-semibold text-right">
    <?= count($records) ?> Records Found
  </span>
</div>

<!-- REAL REFERRAL RECORDS (RESPONSIVE CARDS UI — NO HTML TABLES!) -->
<div class="space-y-4">
  <div class="flex items-center justify-between px-1">
    <h3 class="text-base font-black text-slate-800 tracking-tight">Referral Commission Records</h3>
    <span class="text-xs text-slate-400 font-medium">Real-time MySQL records</span>
  </div>

  <?php if (empty($records)): ?>
    <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto shadow-xs">
      <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-3">
        <i data-lucide="gift" class="w-6 h-6"></i>
      </div>
      <h4 class="text-sm font-bold text-slate-800 mb-1">No Referral Records Found</h4>
      <p class="text-xs text-slate-400 leading-relaxed">
        <?= !empty($search) ? 'No transactions match your search query.' : 'Referral commissions will appear here automatically when referred users complete qualifying orders or deposits.' ?>
      </p>
    </div>
  <?php else: ?>
    <!-- Responsive Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php foreach ($records as $r): ?>
        <?php 
          $sourceLabel = ucfirst($r['source_type'] ?? 'Order');
          $commEarnedFormatted = '$' . number_format((float)$r['commission_amount'], 4);
          $baseAmountFormatted = '$' . number_format((float)$r['base_amount'], 2);
        ?>
        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs hover:border-rose-200 transition-all flex flex-col justify-between">
          <!-- Top Row: Users -->
          <div>
            <div class="flex items-center justify-between gap-2 mb-3">
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                #<?= e($r['id']) ?> · <?= e($sourceLabel) ?> Trigger
              </span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                Completed
              </span>
            </div>

            <!-- Referrer & Referred User Blocks -->
            <div class="grid grid-cols-2 gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-100 mb-3 text-xs">
              <!-- Referrer -->
              <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Referrer (Earned)</span>
                <span class="font-bold text-slate-800 block truncate">@<?= e($r['referrer_username'] ?: 'User #' . $r['referrer_id']) ?></span>
                <span class="text-[10px] text-slate-500 block truncate"><?= e($r['referrer_email'] ?: '') ?></span>
              </div>
              <!-- Referred User -->
              <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Referred (Payer)</span>
                <span class="font-bold text-slate-800 block truncate">@<?= e($r['referred_username'] ?: 'User #' . $r['referred_id']) ?></span>
                <span class="text-[10px] text-slate-500 block truncate"><?= e($r['referred_email'] ?: '') ?></span>
              </div>
            </div>
          </div>

          <!-- Bottom Metrics -->
          <div class="pt-2 border-t border-slate-100">
            <div class="grid grid-cols-3 gap-2 text-xs mb-2">
              <div>
                <span class="text-[10px] text-slate-400 block">Commission</span>
                <span class="font-bold text-emerald-600 text-sm"><?= $commEarnedFormatted ?></span>
              </div>
              <div>
                <span class="text-[10px] text-slate-400 block">Rate</span>
                <span class="font-bold text-slate-700"><?= (float)$r['commission_percent'] ?>%</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-400 block">Qualifying Base</span>
                <span class="font-bold text-slate-700"><?= $baseAmountFormatted ?></span>
              </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
              <span>Source ID: <strong><?= e($r['source_type']) ?> #<?= e($r['source_id']) ?></strong></span>
              <span><?= date('M d, Y · h:i A', strtotime($r['created_at'])) ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
function setPresetCommission(val) {
  document.getElementById('commission-input').value = val.toFixed(2);
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
