<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ChildPanelHelper.php';

// Auth check
if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$pageTitle = 'Child Panels - RoseSMM';
$activePage = 'child-panels';

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$uStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

$plans = ChildPanelHelper::getPlans();
$nameservers = ChildPanelHelper::getNameservers();

$msg = '';
$err = '';

// Handle Purchase Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase_child_panel') {
    $planKey = trim($_POST['plan'] ?? 'basic');
    $domain = trim($_POST['domain'] ?? '');
    $panelName = trim($_POST['panel_name'] ?? '');
    $adminUser = trim($_POST['admin_username'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = $_POST['admin_password'] ?? '';
    $theme = trim($_POST['theme'] ?? 'default');
    $supportEmail = trim($_POST['support_email'] ?? $adminEmail);

    $res = ChildPanelHelper::purchaseChildPanel(
        $userId,
        $planKey,
        $domain,
        $panelName,
        $adminUser,
        $adminEmail,
        $adminPass,
        'wallet_balance',
        [
            'theme' => $theme,
            'support_email' => $supportEmail
        ]
    );

    if ($res['success']) {
        // Refresh user balance
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch();
        $msg = $res['message'];
        // Redirect to detail page
        header("Location: /child-panel?id=" . $res['child_panel_id'] . "&created=1");
        exit;
    } else {
        $err = $res['error'];
    }
}

// Fetch user's child panels
$cpStmt = $db->prepare("SELECT * FROM child_panels WHERE user_id = ? ORDER BY id DESC");
$cpStmt->execute([$userId]);
$userPanels = $cpStmt->fetchAll();

require_once __DIR__ . '/../layouts/user_header.php';
?>

<div class="max-w-6xl mx-auto space-y-8">
  <!-- Top Banner / Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-rose-100 text-rose-700 border border-rose-200">
          Reseller Program
        </span>
        <span class="text-xs text-slate-400">• Nameserver Turnkey Solution</span>
      </div>
      <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Child Panels</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl">
        Launch your own branded SMM panel under your custom domain in minutes. Connect your domain via our automated nameservers, configure custom retail margins, and start selling instantly.
      </p>
    </div>
    <div class="flex items-center gap-3">
      <div class="px-4 py-2.5 rounded-2xl bg-white border border-[#FCE4E8] shadow-sm text-right">
        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Your Balance</div>
        <div class="text-base font-black text-rose-600"><?= format_price($user['balance']) ?></div>
      </div>
      <button onclick="document.getElementById('order-panel-section').scrollIntoView({ behavior: 'smooth' })" class="px-5 py-2.5 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm hover:shadow transition-all flex items-center gap-2 shrink-0">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>
        <span>Create Child Panel</span>
      </button>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-bold flex items-center gap-3">
      <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm font-bold flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
        <span><?= e($err) ?></span>
      </div>
      <?php if (strpos($err, 'Insufficient') !== false): ?>
        <a href="/add-funds" class="px-3.5 py-1.5 rounded-xl bg-rose-500 text-white text-xs font-bold hover:bg-rose-600 transition-colors shrink-0">
          Add Funds
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Active Child Panels List -->
  <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 mb-5">
      <div>
        <h2 class="text-base sm:text-lg font-black text-slate-800 tracking-tight">Your Child Panels</h2>
        <p class="text-xs text-slate-500">Manage your provisioned domains, verify DNS records, and configure settings.</p>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
        <?= count($userPanels) ?> Total
      </span>
    </div>

    <?php if (empty($userPanels)): ?>
      <div class="text-center py-10 px-4 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-500 border border-rose-100 flex items-center justify-center mx-auto mb-3">
          <i data-lucide="globe" class="w-7 h-7"></i>
        </div>
        <h3 class="text-sm font-black text-slate-800">You haven't created any Child Panels yet</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
          Choose between our Basic or Advanced turnkey plans below to launch your own profitable SMM business.
        </p>
        <button onclick="document.getElementById('order-panel-section').scrollIntoView({ behavior: 'smooth' })" class="px-4 py-2 rounded-xl bg-rose-500 text-white text-xs font-bold hover:bg-rose-600 transition-colors inline-flex items-center gap-1.5">
          <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
          <span>Explore Plans & Order</span>
        </button>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($userPanels as $p): ?>
          <div class="p-5 rounded-2xl border border-slate-200/90 hover:border-rose-300 transition-all bg-white hover:shadow-md flex flex-col justify-between">
            <div>
              <div class="flex items-start justify-between gap-2 mb-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 <?= $p['plan'] === 'advanced' ? 'bg-purple-50 text-purple-600 border border-purple-200' : 'bg-rose-50 text-rose-600 border border-rose-200' ?>">
                    <i data-lucide="<?= $p['plan'] === 'advanced' ? 'zap' : 'globe' ?>" class="w-4 h-4"></i>
                  </div>
                  <div>
                    <h3 class="text-sm font-black text-slate-800 truncate max-w-[200px] sm:max-w-[260px]"><?= e($p['domain']) ?></h3>
                    <div class="text-[11px] text-slate-400 font-medium"><?= e($p['panel_name']) ?></div>
                  </div>
                </div>
                <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-md <?= $p['plan'] === 'advanced' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                  <?= ucfirst($p['plan']) ?> Plan
                </span>
              </div>

              <!-- Status Badges -->
              <div class="grid grid-cols-2 gap-2 my-3 text-xs">
                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                  <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Panel Status</div>
                  <div class="font-extrabold mt-0.5 flex items-center gap-1.5">
                    <?php if ($p['status'] === 'active'): ?>
                      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                      <span class="text-emerald-700">Active</span>
                    <?php elseif ($p['status'] === 'approved'): ?>
                      <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                      <span class="text-blue-700">Approved</span>
                    <?php elseif ($p['status'] === 'pending_approval'): ?>
                      <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                      <span class="text-amber-700">Pending Review</span>
                    <?php elseif ($p['status'] === 'suspended'): ?>
                      <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                      <span class="text-rose-700">Suspended</span>
                    <?php else: ?>
                      <span class="text-slate-600"><?= ucfirst($p['status']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                  <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Domain DNS</div>
                  <div class="font-extrabold mt-0.5 flex items-center gap-1.5">
                    <?php if ($p['dns_status'] === 'dns_connected'): ?>
                      <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-emerald-600"></i>
                      <span class="text-emerald-700">Connected</span>
                    <?php elseif ($p['dns_status'] === 'verifying'): ?>
                      <i data-lucide="loader" class="w-3.5 h-3.5 text-blue-600 animate-spin"></i>
                      <span class="text-blue-700">Verifying</span>
                    <?php else: ?>
                      <i data-lucide="alert-circle" class="w-3.5 h-3.5 text-amber-500"></i>
                      <span class="text-amber-700">Pending Setup</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Nameserver Info Snippet -->
              <div class="p-2.5 rounded-xl bg-slate-50/80 border border-slate-100 text-[11px] font-mono text-slate-600 space-y-1">
                <div class="flex items-center justify-between">
                  <span class="text-slate-400 font-sans text-[10px] uppercase font-bold">NS1:</span>
                  <span class="font-bold text-slate-700"><?= e($p['nameserver_1'] ?: $nameservers['ns1']) ?></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-slate-400 font-sans text-[10px] uppercase font-bold">NS2:</span>
                  <span class="font-bold text-slate-700"><?= e($p['nameserver_2'] ?: $nameservers['ns2']) ?></span>
                </div>
              </div>
            </div>

            <!-- Card Actions -->
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
              <span class="text-[11px] text-slate-400 font-medium">
                Expires: <?= $p['expires_at'] ? date('M d, Y', strtotime($p['expires_at'])) : '30 days' ?>
              </span>
              <a href="/child-panel?id=<?= $p['id'] ?>" class="px-4 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white font-bold text-xs transition-colors flex items-center gap-1.5 shadow-sm">
                <span>Manage & DNS Setup</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Plan Comparison Cards & Order Section -->
  <div id="order-panel-section" class="space-y-6 pt-4">
    <div class="text-center max-w-xl mx-auto">
      <h2 class="text-2xl font-black text-slate-800 tracking-tight">Choose Your Reseller Plan</h2>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">
        Transparent turnkey plans with automated nameserver infrastructure and instant activation workflow.
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- BASIC PLAN CARD -->
      <div class="rounded-3xl border-2 border-slate-200 bg-white p-6 sm:p-7 flex flex-col justify-between relative transition-all hover:border-rose-300 hover:shadow-md">
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="px-3 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-700 border border-rose-200">
              <?= $plans['basic']['badge'] ?>
            </span>
            <span class="text-xs text-slate-400 font-medium">Turnkey Setup</span>
          </div>
          <h3 class="text-xl font-black text-slate-800"><?= $plans['basic']['name'] ?></h3>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?= $plans['basic']['description'] ?></p>

          <div class="my-6">
            <div class="flex items-baseline gap-1">
              <span class="text-3xl sm:text-4xl font-black text-slate-900"><?= $plans['basic']['symbol'] ?><?= number_format($plans['basic']['price'], 0) ?></span>
              <span class="text-xs text-slate-400 font-bold"><?= $plans['basic']['currency'] ?> / month</span>
            </div>
            <div class="text-[11px] text-rose-600 font-semibold mt-1">Deducted from your wallet balance</div>
          </div>

          <div class="space-y-2.5 text-xs text-slate-600 border-t border-slate-100 pt-5">
            <div class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-2">Included Features</div>
            <?php foreach ($plans['basic']['features'] as $f): ?>
              <div class="flex items-start gap-2">
                <i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                <span class="font-medium"><?= e($f) ?></span>
              </div>
            <?php endforeach; ?>

            <div class="pt-2"></div>
            <div class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-2">Plan Restrictions</div>
            <?php foreach ($plans['basic']['limitations'] as $l): ?>
              <div class="flex items-start gap-2 text-slate-400">
                <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5"></i>
                <span><?= e($l) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="button" onclick="selectPlan('basic', '<?= $plans['basic']['name'] ?>', <?= $plans['basic']['price'] ?>)" class="mt-8 w-full py-3 rounded-2xl bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white font-bold text-xs transition-colors flex items-center justify-center gap-2">
          <span>Select Basic Plan</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </div>

      <!-- ADVANCED PLAN CARD -->
      <div class="rounded-3xl border-2 border-purple-500/80 bg-gradient-to-b from-white to-purple-50/20 p-6 sm:p-7 flex flex-col justify-between relative shadow-lg shadow-purple-500/5">
        <div class="absolute -top-3.5 right-6 px-3.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-600 text-white shadow-sm flex items-center gap-1">
          <i data-lucide="sparkles" class="w-3 h-3"></i>
          <span>External API Ready</span>
        </div>
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="px-3 py-1 rounded-full text-xs font-black bg-purple-100 text-purple-700 border border-purple-200">
              <?= $plans['advanced']['badge'] ?>
            </span>
            <span class="text-xs text-purple-600 font-bold">Reseller Pro</span>
          </div>
          <h3 class="text-xl font-black text-slate-800"><?= $plans['advanced']['name'] ?></h3>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?= $plans['advanced']['description'] ?></p>

          <div class="my-6">
            <div class="flex items-baseline gap-1">
              <span class="text-3xl sm:text-4xl font-black text-slate-900"><?= $plans['advanced']['symbol'] ?><?= number_format($plans['advanced']['price'], 0) ?></span>
              <span class="text-xs text-slate-400 font-bold"><?= $plans['advanced']['currency'] ?> / month</span>
            </div>
            <div class="text-[11px] text-purple-600 font-semibold mt-1">Deducted from your wallet balance</div>
          </div>

          <div class="space-y-2.5 text-xs text-slate-600 border-t border-purple-100 pt-5">
            <div class="text-[11px] font-black text-purple-700 uppercase tracking-wider mb-2">Advanced Unlocked Features</div>
            <?php foreach ($plans['advanced']['features'] as $f): ?>
              <div class="flex items-start gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-purple-600 shrink-0 mt-0.5"></i>
                <span class="font-medium text-slate-800"><?= e($f) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="button" onclick="selectPlan('advanced', '<?= $plans['advanced']['name'] ?>', <?= $plans['advanced']['price'] ?>)" class="mt-8 w-full py-3 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs transition-colors flex items-center justify-center gap-2 shadow-md shadow-purple-600/20">
          <span>Select Advanced Plan</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </div>
    </div>

    <!-- Purchase & Provisioning Order Form Modal / Box -->
    <div id="order-form-box" class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
      <div class="flex items-start justify-between gap-4 mb-6 pb-4 border-b border-slate-100">
        <div>
          <h3 class="text-lg sm:text-xl font-black text-slate-800 tracking-tight">Order Child Panel Setup</h3>
          <p class="text-xs text-slate-500 mt-0.5">Enter your custom domain and administrator account details to submit your request.</p>
        </div>
        <div class="px-3.5 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold" id="selected-plan-badge">
          Selected: Basic Plan
        </div>
      </div>

      <form method="POST" action="/child-panels" class="space-y-6" id="child-panel-form">
        <input type="hidden" name="action" value="purchase_child_panel">
        <input type="hidden" name="plan" id="input-plan" value="basic">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Domain Name -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
              Your Domain Name <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="globe" class="w-4 h-4"></i>
              </div>
              <input type="text" name="domain" id="input-domain" required placeholder="e.g. mysmmpanel.com" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-xs sm:text-sm font-medium text-slate-800 outline-none transition-all">
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">
              Enter your own purchased domain or subdomain without http:// or www.
            </p>
          </div>

          <!-- Panel Name -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
              Child Panel Name <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="layout" class="w-4 h-4"></i>
              </div>
              <input type="text" name="panel_name" required placeholder="e.g. Alpha Boost SMM" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-xs sm:text-sm font-medium text-slate-800 outline-none transition-all">
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">
              The public brand name that will appear in your panel header and title.
            </p>
          </div>

          <!-- Admin Username -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
              Child Panel Admin Username <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="user" class="w-4 h-4"></i>
              </div>
              <input type="text" name="admin_username" required placeholder="e.g. admin" value="<?= e($user['username']) ?>" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-xs sm:text-sm font-medium text-slate-800 outline-none transition-all">
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">
              You will use this to log in to your Child Panel management console.
            </p>
          </div>

          <!-- Admin Email -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
              Admin Contact Email <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="mail" class="w-4 h-4"></i>
              </div>
              <input type="email" name="admin_email" required placeholder="admin@yourdomain.com" value="<?= e($user['email']) ?>" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-xs sm:text-sm font-medium text-slate-800 outline-none transition-all">
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">
              Email for child panel password recovery and system notices.
            </p>
          </div>

          <!-- Admin Initial Password -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
              Child Panel Admin Password <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="lock" class="w-4 h-4"></i>
              </div>
              <input type="password" name="admin_password" required minlength="6" placeholder="Choose a secure password" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-xs sm:text-sm font-medium text-slate-800 outline-none transition-all">
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">
              At least 6 characters. You can change this anytime from your panel.
            </p>
          </div>

          <!-- Theme -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
              Initial Theme
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="palette" class="w-4 h-4"></i>
              </div>
              <select name="theme" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-xs sm:text-sm font-medium text-slate-800 outline-none transition-all bg-white">
                <option value="default">Rose Modern (Recommended)</option>
                <option value="dark">Midnight Dark</option>
                <option value="cyan">Neon Cyan</option>
                <option value="emerald">Emerald Pro</option>
              </select>
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">
              You can adjust themes and custom colors in your branding settings.
            </p>
          </div>
        </div>

        <!-- Nameserver Notice -->
        <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-amber-900 text-xs space-y-2">
          <div class="flex items-center gap-2 font-bold text-amber-800">
            <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0"></i>
            <span>How Domain Connection Works</span>
          </div>
          <p class="leading-relaxed">
            After submitting this request and payment confirmation, you will point your domain's nameservers at your registrar to:
            <span class="font-mono font-bold text-slate-800 bg-white/80 px-1.5 py-0.5 rounded border border-amber-300"><?= e($nameservers['ns1']) ?></span> and
            <span class="font-mono font-bold text-slate-800 bg-white/80 px-1.5 py-0.5 rounded border border-amber-300"><?= e($nameservers['ns2']) ?></span>.
            Our automated DNS system will verify your domain once approved by Admin.
          </p>
        </div>

        <!-- Order Summary & Submit -->
        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">Payment Method</div>
            <div class="text-sm font-bold text-slate-800 flex items-center gap-2 mt-0.5">
              <i data-lucide="wallet" class="w-4 h-4 text-emerald-600"></i>
              <span>Wallet Balance (Current: <?= format_price($user['balance']) ?>)</span>
            </div>
          </div>
          <div class="flex items-center gap-4 w-full sm:w-auto justify-end">
            <div class="text-right">
              <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Due Today</div>
              <div class="text-xl font-black text-rose-600" id="total-price-display">
                <?= $plans['basic']['symbol'] ?><?= number_format($plans['basic']['price'], 2) ?>
              </div>
            </div>
            <button type="submit" class="px-7 py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-black text-xs sm:text-sm shadow-sm transition-all flex items-center gap-2 shrink-0">
              <i data-lucide="shield-check" class="w-4 h-4"></i>
              <span>Confirm & Purchase</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function selectPlan(key, name, price) {
  document.getElementById('input-plan').value = key;
  document.getElementById('selected-plan-badge').innerText = 'Selected: ' + name;
  document.getElementById('selected-plan-badge').className = key === 'advanced' 
    ? 'px-3.5 py-1.5 rounded-xl bg-purple-100 text-purple-700 text-xs font-bold border border-purple-200' 
    : 'px-3.5 py-1.5 rounded-xl bg-rose-100 text-rose-700 text-xs font-bold border border-rose-200';
  
  const symbol = '<?= $plans['basic']['symbol'] ?>';
  document.getElementById('total-price-display').innerText = symbol + Number(price).toFixed(2);
  
  const box = document.getElementById('order-form-box');
  box.scrollIntoView({ behavior: 'smooth' });
  document.getElementById('input-domain').focus();
}
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
