<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ChildPanelHelper.php';

if (!is_admin()) {
    header("Location: /admin/login");
    exit;
}

$pageTitle = 'Child Panel Details - Admin Console';
$adminPage = 'child-panels';

$db = getDB();
$panelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT cp.*, u.username as owner_username, u.email as owner_email, u.balance as owner_balance 
    FROM child_panels cp 
    LEFT JOIN users u ON cp.user_id = u.id 
    WHERE cp.id = ?
");
$stmt->execute([$panelId]);
$panel = $stmt->fetch();

if (!$panel) {
    require_once __DIR__ . '/../layouts/admin_header.php';
    ?>
    <div class="max-w-2xl mx-auto my-12 bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-center">
      <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="alert-circle" class="w-8 h-8"></i>
      </div>
      <h1 class="text-xl font-black text-slate-800 tracking-tight mb-2">Child Panel Not Found</h1>
      <p class="text-xs text-slate-500 mb-6">The requested Child Panel #<?= htmlspecialchars((string)$panelId) ?> does not exist or has been removed from the database.</p>
      <a href="/admin/child-panels" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-sm transition-all">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span>Return to Child Panels</span>
      </a>
    </div>
    <?php
    require_once __DIR__ . '/../layouts/admin_footer.php';
    exit;
}

$msg = '';
$err = '';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Approve
    if ($action === 'approve') {
        $notes = trim($_POST['notes'] ?? '');
        $res = ChildPanelHelper::approveChildPanel($panelId, $notes);
        if ($res['success']) {
            $msg = "Child Panel approved successfully! Current status: " . $res['new_status'];
        } else {
            $err = $res['error'];
        }
    }

    // 2. Reject
    elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? 'Requirements not met');
        $refund = !empty($_POST['refund_wallet']);
        $res = ChildPanelHelper::rejectChildPanel($panelId, $reason, $refund);
        if ($res['success']) {
            $msg = "Child Panel rejected." . ($refund ? " User wallet was refunded." : "");
        } else {
            $err = $res['error'];
        }
    }

    // 3. Suspend / Activate
    elseif ($action === 'toggle_status') {
        $target = $_POST['target_status'] ?? 'active';
        $act = ($target === 'suspended') ? 'suspend' : 'activate';
        ChildPanelHelper::toggleSuspension($panelId, $act);
        $msg = "Child Panel status changed to $target.";
    }

    // 4. Verify Domain
    elseif ($action === 'verify_domain') {
        $vRes = ChildPanelHelper::verifyDomain($panelId);
        if ($vRes['success']) {
            $msg = "Domain verified: " . $vRes['message'];
        } else {
            $err = $vRes['error'];
        }
    }

    // 5. Reset Admin Password
    elseif ($action === 'reset_admin_password') {
        $newPass = $_POST['new_password'] ?? '';
        $rRes = ChildPanelHelper::resetAdminPassword($panelId, $newPass);
        if ($rRes['success']) {
            $msg = "Child panel admin password updated successfully.";
        } else {
            $err = $rRes['error'];
        }
    }

    // 6. Update Plan & Permissions
    elseif ($action === 'update_plan_permissions') {
        $newPlan = $_POST['plan'] ?? 'basic';
        ChildPanelHelper::updatePlan($panelId, $newPlan);
        $msg = "Plan updated to " . ucfirst($newPlan) . " with permissions synced.";
    }

    // 7. Update Branding & Margins
    elseif ($action === 'update_branding') {
        $pName = trim($_POST['panel_name'] ?? '');
        $theme = trim($_POST['theme'] ?? 'default');
        $supEmail = trim($_POST['support_email'] ?? '');
        $margin = (float)($_POST['price_margin_percent'] ?? 15.0);

        ChildPanelHelper::updateBranding($panelId, [
            'panel_name' => $pName,
            'theme' => $theme,
            'support_email' => $supEmail,
            'price_margin_percent' => $margin
        ]);
        $msg = "Branding and margin settings saved.";
    }

    // 8. Delete
    elseif ($action === 'delete') {
        ChildPanelHelper::deleteChildPanel($panelId);
        header("Location: /admin/child-panels?deleted=1");
        exit;
    }

    // Refresh panel record
    $stmt->execute([$panelId]);
    $panel = $stmt->fetch();
}

$nameservers = ChildPanelHelper::getNameservers();
$plans = ChildPanelHelper::getPlans();
$currentPlan = $plans[$panel['plan'] ?? 'basic'] ?? ($plans['basic'] ?? ['name' => 'Basic Plan']);

// Decode DNS details safely
$dnsDetails = [];
if (!empty($panel['dns_details'])) {
    $decoded = json_decode($panel['dns_details'], true);
    if (is_array($decoded)) {
        $dnsDetails = $decoded;
    }
}

// Connected Providers Count (if Advanced)
$providerStmt = $db->prepare("SELECT COUNT(*) FROM child_panel_providers WHERE child_panel_id = ?");
$providerStmt->execute([$panelId]);
$providersTotal = (int)$providerStmt->fetchColumn();

// Imported Services Count
$srvStmt = $db->prepare("SELECT COUNT(*) FROM child_panel_services WHERE child_panel_id = ?");
$srvStmt->execute([$panelId]);
$servicesTotal = (int)$srvStmt->fetchColumn();

require_once __DIR__ . '/../layouts/admin_header.php';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <div class="flex items-center gap-2 mb-1">
      <a href="/admin/child-panels" class="text-xs font-bold text-slate-400 hover:text-rose-600 transition-colors flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
        <span>Child Panels List</span>
      </a>
      <span class="text-slate-300">/</span>
      <span class="text-xs font-bold text-slate-600">Panel #<?= $panel['id'] ?></span>
    </div>
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-black text-slate-800 tracking-tight"><?= e($panel['domain']) ?></h1>
      <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider <?= $panel['plan'] === 'advanced' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
        <?= ucfirst($panel['plan']) ?>
      </span>
      <?php if ($panel['status'] === 'active'): ?>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-800">ACTIVE</span>
      <?php elseif ($panel['status'] === 'pending_approval'): ?>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-100 text-amber-800">PENDING REVIEW</span>
      <?php elseif ($panel['status'] === 'approved'): ?>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-100 text-blue-800">APPROVED</span>
      <?php elseif ($panel['status'] === 'suspended'): ?>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-rose-100 text-rose-800">SUSPENDED</span>
      <?php else: ?>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-slate-100 text-slate-700"><?= strtoupper($panel['status']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="flex items-center gap-2">
    <a href="/child-panel-portal?child_panel=<?= $panel['id'] ?>" target="_blank" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5">
      <i data-lucide="eye" class="w-4 h-4"></i>
      <span>Preview Panel</span>
    </a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="mb-5 p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="mb-5 p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
    <span><?= e($err) ?></span>
  </div>
<?php endif; ?>

<!-- Quick Approval Callout if Pending -->
<?php if ($panel['status'] === 'pending_approval'): ?>
  <div class="mb-6 p-5 rounded-3xl bg-amber-50 border-2 border-amber-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0">
        <i data-lucide="bell" class="w-5 h-5"></i>
      </div>
      <div>
        <h3 class="text-sm font-black text-amber-900">Child Panel Request Awaiting Review</h3>
        <p class="text-xs text-amber-800 mt-0.5">The user has completed payment (<?= $panel['payment_currency'] ?> <?= number_format($panel['payment_amount'], 2) ?>). Please review details and approve.</p>
      </div>
    </div>
    <div class="flex items-center gap-2 shrink-0">
      <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>">
        <input type="hidden" name="action" value="approve">
        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-colors flex items-center gap-1.5">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span>Approve Request</span>
        </button>
      </form>
      <button onclick="document.getElementById('reject-modal').classList.remove('hidden')" class="px-4 py-2 rounded-xl bg-rose-100 hover:bg-rose-200 text-rose-800 font-bold text-xs transition-colors">
        Reject & Refund
      </button>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Left Column: Details -->
  <div class="lg:col-span-2 space-y-6">

    <!-- 1. GENERAL INFORMATION (Section 13) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs">
      <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 pb-3 border-b border-slate-100 flex items-center gap-2">
        <i data-lucide="info" class="w-4 h-4 text-slate-500"></i>
        <span>General Information</span>
      </h2>

      <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Child Panel ID</div>
          <div class="font-mono font-bold text-slate-800 text-sm mt-0.5">#<?= $panel['id'] ?></div>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Owner Account</div>
          <div class="font-bold text-slate-800 text-sm mt-0.5"><?= e($panel['owner_username']) ?></div>
          <div class="text-[11px] text-slate-400"><?= e($panel['owner_email']) ?></div>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Active Plan</div>
          <div class="font-extrabold text-slate-800 text-sm mt-0.5"><?= e($currentPlan['name']) ?></div>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Creation Date</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= date('M d, Y H:i', strtotime($panel['created_at'])) ?></div>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Monthly Expiry</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= $panel['expires_at'] ? date('M d, Y', strtotime($panel['expires_at'])) : '30 days' ?></div>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Last Activity / Updated</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= date('M d, Y H:i', strtotime($panel['updated_at'])) ?></div>
        </div>
      </div>
    </div>

    <!-- 2. DOMAIN INFORMATION & DNS VERIFICATION (Section 13) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
          <i data-lucide="globe" class="w-4 h-4 text-slate-500"></i>
          <span>Domain & Nameserver Infrastructure</span>
        </h2>
        <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>">
          <input type="hidden" name="action" value="verify_domain">
          <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs transition-colors flex items-center gap-1.5">
            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
            <span>Re-Check DNS</span>
          </button>
        </form>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs mb-4">
        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Assigned Nameserver 1</div>
          <div class="font-mono font-bold text-slate-800 text-sm mt-0.5"><?= e($panel['nameserver_1'] ?: $nameservers['ns1']) ?></div>
        </div>

        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Assigned Nameserver 2</div>
          <div class="font-mono font-bold text-slate-800 text-sm mt-0.5"><?= e($panel['nameserver_2'] ?: $nameservers['ns2']) ?></div>
        </div>

        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">DNS Verification State</div>
          <div class="font-bold text-sm mt-0.5 flex items-center gap-1.5">
            <?php if ($panel['dns_status'] === 'dns_connected'): ?>
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <span class="text-emerald-700">DNS Connected & Propagated</span>
            <?php else: ?>
              <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
              <span class="text-amber-700">Pending Nameserver Setup</span>
            <?php endif; ?>
          </div>
          <div class="text-[11px] text-slate-400 mt-1">Last Checked: <?= $panel['dns_last_checked'] ? date('M d, H:i', strtotime($panel['dns_last_checked'])) : 'Never' ?></div>
        </div>

        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">SSL Security Status</div>
          <div class="font-bold text-sm mt-0.5 flex items-center gap-1.5">
            <?php if ($panel['ssl_status'] === 'ssl_active'): ?>
              <i data-lucide="lock" class="w-4 h-4 text-emerald-600"></i>
              <span class="text-emerald-700">SSL Active (HTTPS Secured)</span>
            <?php else: ?>
              <i data-lucide="shield-alert" class="w-4 h-4 text-amber-500"></i>
              <span class="text-amber-700">SSL Pending DNS Connection</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!empty($dnsDetails)): ?>
        <div class="p-3 rounded-xl bg-slate-900 text-slate-300 font-mono text-[11px] space-y-1">
          <div><span class="text-slate-500">Detected NS:</span> <?= !empty($dnsDetails['detected_ns']) ? implode(', ', $dnsDetails['detected_ns']) : 'None' ?></div>
          <div><span class="text-slate-500">Detected IP:</span> <?= !empty($dnsDetails['detected_ips']) ? implode(', ', $dnsDetails['detected_ips']) : 'None' ?></div>
          <div><span class="text-slate-500">Result:</span> <?= e($dnsDetails['reason'] ?? '') ?></div>
        </div>
      <?php endif; ?>
    </div>

    <!-- 3. ACCESS & ADMIN CREDENTIALS (Section 13) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs">
      <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 pb-3 border-b border-slate-100 flex items-center gap-2">
        <i data-lucide="key" class="w-4 h-4 text-slate-500"></i>
        <span>Access & Admin Console Information</span>
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs mb-5">
        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Public Panel URL</div>
          <a href="http://<?= e($panel['domain']) ?>" target="_blank" class="font-mono font-bold text-rose-600 hover:underline mt-0.5 block">
            http://<?= e($panel['domain']) ?>
          </a>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Admin Console URL</div>
          <div class="font-mono text-slate-700 mt-0.5">http://<?= e($panel['domain']) ?>/admin</div>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Child Panel Admin Username</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= e($panel['admin_username']) ?></div>
        </div>

        <div>
          <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Admin Contact Email</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= e($panel['admin_email']) ?></div>
        </div>
      </div>

      <!-- Password Reset Form -->
      <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>" class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col sm:flex-row items-center gap-3">
        <input type="hidden" name="action" value="reset_admin_password">
        <div class="w-full sm:w-auto flex-1">
          <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Reset Admin Password</label>
          <input type="password" name="new_password" required minlength="6" placeholder="Enter new password" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs outline-none bg-white">
        </div>
        <button type="submit" class="w-full sm:w-auto px-4 py-2 mt-4 sm:mt-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs transition-colors shrink-0">
          Reset Password
        </button>
      </form>
    </div>

    <!-- 4. BRANDING & MARGINS (Section 13) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs">
      <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 pb-3 border-b border-slate-100 flex items-center gap-2">
        <i data-lucide="palette" class="w-4 h-4 text-slate-500"></i>
        <span>Branding & Retail Margins</span>
      </h2>

      <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>" class="space-y-4 text-xs">
        <input type="hidden" name="action" value="update_branding">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Brand Name</label>
            <input type="text" name="panel_name" value="<?= e($panel['panel_name']) ?>" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold outline-none">
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Theme</label>
            <select name="theme" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-medium outline-none bg-white">
              <option value="default" <?= $panel['theme'] === 'default' ? 'selected' : '' ?>>Rose Modern</option>
              <option value="dark" <?= $panel['theme'] === 'dark' ? 'selected' : '' ?>>Midnight Dark</option>
              <option value="cyan" <?= $panel['theme'] === 'cyan' ? 'selected' : '' ?>>Neon Cyan</option>
              <option value="emerald" <?= $panel['theme'] === 'emerald' ? 'selected' : '' ?>>Emerald Pro</option>
            </select>
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Support Email</label>
            <input type="email" name="support_email" value="<?= e($panel['support_email']) ?>" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs outline-none">
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Profit Margin % on Parent Services</label>
            <input type="number" step="0.1" name="price_margin_percent" value="<?= (float)$panel['price_margin_percent'] ?>" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold outline-none">
          </div>
        </div>

        <div class="pt-2 flex justify-end">
          <button type="submit" class="px-5 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-xs transition-colors">
            Save Branding Changes
          </button>
        </div>
      </form>
    </div>

  </div>

  <!-- Right Column: Plan, Payment & Admin Actions -->
  <div class="space-y-6">

    <!-- PLAN & PERMISSIONS (Section 13) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-4">
      <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
        <i data-lucide="sliders" class="w-4 h-4 text-slate-500"></i>
        <span>Plan & Permissions</span>
      </h2>

      <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>" class="space-y-4 text-xs">
        <input type="hidden" name="action" value="update_plan_permissions">

        <div>
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Child Panel Plan</label>
          <select name="plan" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold outline-none bg-white">
            <option value="basic" <?= $panel['plan'] === 'basic' ? 'selected' : '' ?>>Basic (Parent Services Only)</option>
            <option value="advanced" <?= $panel['plan'] === 'advanced' ? 'selected' : '' ?>>Advanced (+ External SMM Provider APIs)</option>
          </select>
        </div>

        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-slate-600">External API Support:</span>
            <span class="font-bold <?= $panel['external_api_enabled'] ? 'text-purple-600' : 'text-slate-400' ?>">
              <?= $panel['external_api_enabled'] ? 'Enabled' : 'Disabled' ?>
            </span>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-slate-600">Connected Providers:</span>
            <span class="font-bold text-slate-800"><?= $providersTotal ?></span>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-slate-600">Imported Services:</span>
            <span class="font-bold text-slate-800"><?= $servicesTotal ?></span>
          </div>
        </div>

        <button type="submit" class="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs transition-colors">
          Update Plan & Permissions
        </button>
      </form>
    </div>

    <!-- PAYMENT DETAILS (Section 13) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-3 text-xs">
      <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
        <i data-lucide="receipt" class="w-4 h-4 text-slate-500"></i>
        <span>Payment Information</span>
      </h2>

      <div class="flex items-center justify-between">
        <span class="text-slate-400 uppercase text-[10px] font-bold">Status:</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">
          <?= e($panel['payment_status']) ?>
        </span>
      </div>

      <div class="flex items-center justify-between">
        <span class="text-slate-400 uppercase text-[10px] font-bold">Amount Paid:</span>
        <span class="font-bold text-slate-800 text-sm"><?= $panel['payment_currency'] ?> <?= number_format($panel['payment_amount'], 2) ?></span>
      </div>

      <div class="flex items-center justify-between">
        <span class="text-slate-400 uppercase text-[10px] font-bold">Method:</span>
        <span class="font-medium text-slate-700"><?= e($panel['payment_method']) ?></span>
      </div>

      <div class="flex items-center justify-between">
        <span class="text-slate-400 uppercase text-[10px] font-bold">Transaction Ref:</span>
        <span class="font-mono text-slate-600 text-[11px]"><?= e($panel['payment_ref'] ?: 'N/A') ?></span>
      </div>
    </div>

    <!-- MANAGEMENT ACTIONS (Section 13) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-3">
      <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
        <i data-lucide="shield-check" class="w-4 h-4 text-slate-500"></i>
        <span>Management Actions</span>
      </h2>

      <?php if ($panel['status'] === 'pending_approval'): ?>
        <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-colors flex items-center justify-center gap-2">
            <i data-lucide="check" class="w-4 h-4"></i>
            <span>Approve Request</span>
          </button>
        </form>

        <button onclick="document.getElementById('reject-modal').classList.remove('hidden')" class="w-full py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs transition-colors flex items-center justify-center gap-2">
          <i data-lucide="x" class="w-4 h-4"></i>
          <span>Reject Request</span>
        </button>
      <?php endif; ?>

      <?php if ($panel['status'] === 'active'): ?>
        <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>" onsubmit="return confirm('Suspend this child panel?');">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="target_status" value="suspended">
          <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-xs transition-colors flex items-center justify-center gap-2">
            <i data-lucide="pause" class="w-4 h-4"></i>
            <span>Suspend Panel</span>
          </button>
        </form>
      <?php elseif ($panel['status'] === 'suspended' || $panel['status'] === 'approved'): ?>
        <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="target_status" value="active">
          <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors flex items-center justify-center gap-2">
            <i data-lucide="play" class="w-4 h-4"></i>
            <span>Activate / Reactivate Panel</span>
          </button>
        </form>
      <?php endif; ?>

      <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>" onsubmit="return confirm('Are you sure you want to permanently delete this child panel? All related records will be removed.');">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="w-full py-2 rounded-xl text-rose-500 hover:text-rose-700 hover:bg-rose-50 font-bold text-xs transition-colors flex items-center justify-center gap-1.5">
          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
          <span>Delete Child Panel</span>
        </button>
      </form>
    </div>

  </div>
</div>

<!-- Reject Modal -->
<div id="reject-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xl max-w-md w-full">
    <h3 class="text-base font-black text-slate-800 mb-2">Reject Child Panel Request</h3>
    <p class="text-xs text-slate-500 mb-4">Please provide a reason. You can choose whether to refund the user's wallet.</p>

    <form method="POST" action="/admin/child-panel?id=<?= $panel['id'] ?>" class="space-y-4">
      <input type="hidden" name="action" value="reject">

      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Reason for Rejection</label>
        <textarea name="reason" required rows="3" placeholder="e.g. Inappropriate domain or registrar requirements." class="w-full p-3 rounded-xl border border-slate-200 text-xs outline-none focus:border-rose-500"></textarea>
      </div>

      <div class="flex items-center gap-2">
        <input type="checkbox" name="refund_wallet" id="refund_cb" value="1" checked class="rounded text-rose-600 focus:ring-rose-500">
        <label for="refund_cb" class="text-xs font-bold text-slate-700">Refund <?= $panel['payment_currency'] ?> <?= number_format($panel['payment_amount'], 2) ?> to User's Wallet</label>
      </div>

      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
        <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm">
          Confirm Rejection
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
