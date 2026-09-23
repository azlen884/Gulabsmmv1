<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ChildPanelHelper.php';

if (!is_admin()) {
    header("Location: /admin/login");
    exit;
}

$pageTitle = 'Child Panels Management - Admin Console';
$adminPage = 'child-panels';

$db = getDB();
$msg = '';
$err = '';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $panelId = (int)($_POST['child_panel_id'] ?? 0);

    // Save Global Nameservers and Plan Pricing Settings
    if ($action === 'save_settings') {
        $ns1 = trim($_POST['child_panel_ns1'] ?? '');
        $ns2 = trim($_POST['child_panel_ns2'] ?? '');
        $srvIp = trim($_POST['child_panel_server_ip'] ?? '');
        $basicPrice = (float)($_POST['child_panel_basic_price'] ?? 1499.00);
        $advPrice = (float)($_POST['child_panel_advanced_price'] ?? 3499.00);

        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'child_panel_ns1'")->execute([$ns1]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'child_panel_ns2'")->execute([$ns2]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'child_panel_server_ip'")->execute([$srvIp]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'child_panel_basic_price'")->execute([$basicPrice]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'child_panel_advanced_price'")->execute([$advPrice]);

        $msg = "Global Child Panel nameservers and plan pricing updated successfully.";
    }

    // Approve
    elseif ($action === 'approve') {
        $res = ChildPanelHelper::approveChildPanel($panelId, 'Approved by admin');
        if ($res['success']) {
            $msg = "Child Panel #$panelId approved successfully. Status set to: " . $res['new_status'];
        } else {
            $err = $res['error'];
        }
    }

    // Reject & Refund
    elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? 'Request rejected by admin.');
        $refund = !empty($_POST['refund_wallet']);
        $res = ChildPanelHelper::rejectChildPanel($panelId, $reason, $refund);
        if ($res['success']) {
            $msg = "Child Panel #$panelId rejected." . ($refund ? " User wallet was refunded." : "");
        } else {
            $err = $res['error'];
        }
    }

    // Suspend
    elseif ($action === 'suspend') {
        ChildPanelHelper::toggleSuspension($panelId, 'suspend');
        $msg = "Child Panel #$panelId suspended.";
    }

    // Activate / Reactivate
    elseif ($action === 'activate') {
        ChildPanelHelper::toggleSuspension($panelId, 'activate');
        $msg = "Child Panel #$panelId activated.";
    }

    // Verify Domain
    elseif ($action === 'verify_domain') {
        $vRes = ChildPanelHelper::verifyDomain($panelId);
        if ($vRes['success']) {
            $msg = "Domain verified: " . $vRes['message'];
        } else {
            $err = $vRes['error'];
        }
    }

    // Delete
    elseif ($action === 'delete') {
        ChildPanelHelper::deleteChildPanel($panelId);
        $msg = "Child Panel #$panelId permanently removed.";
    }
}

// Fetch Global Settings
$nameservers = ChildPanelHelper::getNameservers();
$basicPrice = (float)get_setting('child_panel_basic_price', '1499.00');
$advPrice = (float)get_setting('child_panel_advanced_price', '3499.00');

// Statistics
$totalCount = (int)$db->query("SELECT COUNT(*) FROM child_panels")->fetchColumn();
$activeCount = (int)$db->query("SELECT COUNT(*) FROM child_panels WHERE status = 'active'")->fetchColumn();
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM child_panels WHERE status = 'pending_approval'")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT SUM(payment_amount) FROM child_panels WHERE payment_status = 'paid'")->fetchColumn();

// Filter Query
$filterStatus = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT cp.*, u.username as owner_username, u.email as owner_email 
    FROM child_panels cp 
    JOIN users u ON cp.user_id = u.id 
    WHERE 1=1
";
$params = [];

if ($filterStatus !== 'all') {
    $sql .= " AND cp.status = ?";
    $params[] = $filterStatus;
}

if (!empty($search)) {
    $sql .= " AND (cp.domain LIKE ? OR cp.panel_name LIKE ? OR u.username LIKE ? OR cp.admin_email LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY cp.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$panels = $stmt->fetchAll();

require_once __DIR__ . '/../layouts/admin_header.php';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Child Panels Management</h1>
    <p class="text-xs text-slate-500 mt-1">Review reseller panel orders, configure automated nameservers, verify domains, and manage plans.</p>
  </div>
  <button onclick="document.getElementById('settings-drawer').classList.toggle('hidden')" class="px-5 py-2.5 rounded-full bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
    <i data-lucide="settings-2" class="w-4 h-4"></i>
    <span>Nameservers & Pricing Config</span>
  </button>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="mb-4 p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
    <span><?= e($err) ?></span>
  </div>
<?php endif; ?>

<!-- Stats Overview Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Panels</div>
    <div class="text-2xl font-black text-slate-900 mt-1"><?= $totalCount ?></div>
    <div class="text-[11px] text-slate-500 mt-0.5">All time orders</div>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
    <div class="text-[10px] text-amber-600 font-bold uppercase tracking-wider">Pending Review</div>
    <div class="text-2xl font-black text-amber-700 mt-1"><?= $pendingCount ?></div>
    <div class="text-[11px] text-amber-600 mt-0.5">Awaiting admin review</div>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
    <div class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider">Active & Live</div>
    <div class="text-2xl font-black text-emerald-700 mt-1"><?= $activeCount ?></div>
    <div class="text-[11px] text-emerald-600 mt-0.5">DNS connected panels</div>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
    <div class="text-[10px] text-purple-600 font-bold uppercase tracking-wider">Total Revenue</div>
    <div class="text-2xl font-black text-purple-900 mt-1">₹<?= number_format($totalRevenue, 2) ?></div>
    <div class="text-[11px] text-purple-600 mt-0.5">From child panel fees</div>
  </div>
</div>

<!-- Configurable Nameservers & Pricing Box (Section 4) -->
<div id="settings-drawer" class="mb-6 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm <?= empty($_POST['action']) ? 'hidden' : '' ?>">
  <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
    <div>
      <h2 class="text-base font-black text-slate-800">Child Panel Automated Nameservers & Plan Pricing</h2>
      <p class="text-xs text-slate-500">These nameserver records are automatically served to all child panel customers upon purchase.</p>
    </div>
    <button onclick="document.getElementById('settings-drawer').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
  </div>

  <form method="POST" action="/admin/child-panels" class="space-y-4">
    <input type="hidden" name="action" value="save_settings">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nameserver 1</label>
        <input type="text" name="child_panel_ns1" required value="<?= e($nameservers['ns1']) ?>" placeholder="e.g. ns1.yourdomain.com" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm font-mono outline-none focus:border-rose-500">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nameserver 2</label>
        <input type="text" name="child_panel_ns2" required value="<?= e($nameservers['ns2']) ?>" placeholder="e.g. ns2.yourdomain.com" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm font-mono outline-none focus:border-rose-500">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Server IP Address</label>
        <input type="text" name="child_panel_server_ip" required value="<?= e($nameservers['server_ip']) ?>" placeholder="e.g. 192.168.1.1" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm font-mono outline-none focus:border-rose-500">
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Basic Plan Monthly Price (₹)</label>
        <input type="number" step="1" name="child_panel_basic_price" required value="<?= $basicPrice ?>" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm font-bold outline-none focus:border-rose-500">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Advanced Plan Monthly Price (₹)</label>
        <input type="number" step="1" name="child_panel_advanced_price" required value="<?= $advPrice ?>" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm font-bold outline-none focus:border-purple-500">
      </div>
    </div>

    <div class="pt-3 border-t border-slate-100 flex justify-end">
      <button type="submit" class="px-6 py-2.5 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-all">
        Save Nameserver & Pricing Settings
      </button>
    </div>
  </form>
</div>

<!-- Filters & Search -->
<div class="bg-white rounded-2xl border border-slate-200 p-4 mb-5 flex flex-col sm:flex-row items-center justify-between gap-4">
  <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto">
    <a href="/admin/child-panels?status=all" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors shrink-0 <?= $filterStatus === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
      All (<?= $totalCount ?>)
    </a>
    <a href="/admin/child-panels?status=pending_approval" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors shrink-0 <?= $filterStatus === 'pending_approval' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' ?>">
      Pending Approval (<?= $pendingCount ?>)
    </a>
    <a href="/admin/child-panels?status=active" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors shrink-0 <?= $filterStatus === 'active' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
      Active (<?= $activeCount ?>)
    </a>
    <a href="/admin/child-panels?status=suspended" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-colors shrink-0 <?= $filterStatus === 'suspended' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' ?>">
      Suspended
    </a>
  </div>

  <form method="GET" action="/admin/child-panels" class="w-full sm:w-72 relative">
    <?php if ($filterStatus !== 'all'): ?>
      <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
    <?php endif; ?>
    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search domain, owner, name..." class="w-full pl-9 pr-4 py-1.5 rounded-xl border border-slate-200 text-xs outline-none focus:border-slate-800">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
      <i data-lucide="search" class="w-3.5 h-3.5"></i>
    </div>
  </form>
</div>

<!-- Child Panels Table (Section 3) -->
<div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-xs">
  <?php if (empty($panels)): ?>
    <div class="p-12 text-center text-xs text-slate-400">
      No Child Panels found matching the current filter.
    </div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-wider">
          <tr>
            <th class="p-4">ID</th>
            <th class="p-4">Domain & Panel</th>
            <th class="p-4">Owner / User</th>
            <th class="p-4">Plan</th>
            <th class="p-4">Status</th>
            <th class="p-4">DNS & SSL</th>
            <th class="p-4">Payment</th>
            <th class="p-4">Created / Expiry</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium">
          <?php foreach ($panels as $p): ?>
            <tr class="hover:bg-slate-50/60 transition-colors">
              <td class="p-4 font-mono font-bold text-slate-500">#<?= $p['id'] ?></td>
              
              <!-- Domain & Panel Name -->
              <td class="p-4">
                <a href="/admin/child-panel?id=<?= $p['id'] ?>" class="font-extrabold text-slate-800 hover:text-rose-600 transition-colors block text-sm">
                  <?= e($p['domain']) ?>
                </a>
                <div class="text-[11px] text-slate-400 font-normal"><?= e($p['panel_name']) ?></div>
              </td>

              <!-- Owner -->
              <td class="p-4">
                <div class="font-bold text-slate-700"><?= e($p['owner_username']) ?></div>
                <div class="text-[11px] text-slate-400"><?= e($p['owner_email']) ?></div>
              </td>

              <!-- Plan -->
              <td class="p-4">
                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider <?= $p['plan'] === 'advanced' ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-blue-100 text-blue-700 border border-blue-200' ?>">
                  <?= ucfirst($p['plan']) ?>
                </span>
                <?php if ($p['external_api_enabled']): ?>
                  <div class="text-[10px] text-purple-600 font-bold mt-0.5">Ext. API Ready</div>
                <?php endif; ?>
              </td>

              <!-- Status -->
              <td class="p-4">
                <?php if ($p['status'] === 'active'): ?>
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">ACTIVE</span>
                <?php elseif ($p['status'] === 'pending_approval'): ?>
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-100 text-amber-800">PENDING REVIEW</span>
                <?php elseif ($p['status'] === 'approved'): ?>
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-blue-100 text-blue-800">APPROVED</span>
                <?php elseif ($p['status'] === 'suspended'): ?>
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-100 text-rose-800">SUSPENDED</span>
                <?php else: ?>
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-slate-100 text-slate-700"><?= strtoupper($p['status']) ?></span>
                <?php endif; ?>
              </td>

              <!-- DNS & SSL -->
              <td class="p-4">
                <div class="flex items-center gap-1.5">
                  <?php if ($p['dns_status'] === 'dns_connected'): ?>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-emerald-700 font-bold">DNS OK</span>
                  <?php else: ?>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span class="text-amber-700 font-bold">Pending DNS</span>
                  <?php endif; ?>
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-mono">
                  <?= $p['ssl_status'] === 'ssl_active' ? 'SSL Active' : 'SSL Pending' ?>
                </div>
              </td>

              <!-- Payment -->
              <td class="p-4">
                <div class="font-bold text-slate-800"><?= $p['payment_currency'] ?> <?= number_format($p['payment_amount'], 2) ?></div>
                <span class="text-[10px] font-bold text-emerald-600 uppercase"><?= e($p['payment_status']) ?></span>
              </td>

              <!-- Dates -->
              <td class="p-4 text-[11px] text-slate-500">
                <div><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                <div class="text-[10px] text-slate-400">Renews: <?= $p['expires_at'] ? date('M d, Y', strtotime($p['expires_at'])) : '30d' ?></div>
              </td>

              <!-- Action Buttons -->
              <td class="p-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <a href="/admin/child-panel?id=<?= $p['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors" title="View Full Details">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                  </a>

                  <?php if ($p['status'] === 'pending_approval'): ?>
                    <form method="POST" action="/admin/child-panels" class="inline-block">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="child_panel_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-500 text-emerald-700 hover:text-white transition-colors" title="Approve Child Panel">
                        <i data-lucide="check" class="w-4 h-4"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <?php if ($p['status'] === 'active'): ?>
                    <form method="POST" action="/admin/child-panels" class="inline-block" onsubmit="return confirm('Suspend this child panel?');">
                      <input type="hidden" name="action" value="suspend">
                      <input type="hidden" name="child_panel_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="p-1.5 rounded-lg bg-amber-50 hover:bg-amber-500 text-amber-700 hover:text-white transition-colors" title="Suspend Panel">
                        <i data-lucide="pause" class="w-4 h-4"></i>
                      </button>
                    </form>
                  <?php elseif ($p['status'] === 'suspended'): ?>
                    <form method="POST" action="/admin/child-panels" class="inline-block">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="child_panel_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-500 text-emerald-700 hover:text-white transition-colors" title="Reactivate Panel">
                        <i data-lucide="play" class="w-4 h-4"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="POST" action="/admin/child-panels" class="inline-block" onsubmit="return confirm('Permanently delete this child panel? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="child_panel_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-500 text-rose-700 hover:text-white transition-colors" title="Delete Panel">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
