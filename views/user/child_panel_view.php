<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ChildPanelHelper.php';

if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$pageTitle = 'Child Panel Setup & Management - RoseSMM';
$activePage = 'child-panels';

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$uStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

$panelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM child_panels WHERE id = ? AND user_id = ?");
$stmt->execute([$panelId, $userId]);
$panel = $stmt->fetch();

if (!$panel) {
    if (!headers_sent()) {
        header("Location: /child-panels");
    }
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/child-panels"></head><body><script>window.location.href="/child-panels";</script><p>Child Panel not found. <a href="/child-panels">Return to Child Panels</a></p></body></html>';
    exit;
}

$nameservers = ChildPanelHelper::getNameservers();
$plans = ChildPanelHelper::getPlans();
$currentPlan = $plans[$panel['plan']] ?? $plans['basic'];

$msg = '';
$err = '';
$activeTab = $_GET['tab'] ?? 'overview';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Verify Domain DNS
    if ($action === 'verify_domain') {
        $vRes = ChildPanelHelper::verifyDomain($panel['id']);
        if ($vRes['success']) {
            if ($vRes['dns_matched']) {
                $msg = "Domain verification successful! " . $vRes['message'];
            } else {
                $err = "DNS check: " . $vRes['message'];
            }
            // Refresh panel data
            $stmt->execute([$panelId, $userId]);
            $panel = $stmt->fetch();
        } else {
            $err = $vRes['error'];
        }
    }

    // 2. Update Branding & Settings
    elseif ($action === 'update_branding') {
        $panelName = trim($_POST['panel_name'] ?? '');
        $theme = trim($_POST['theme'] ?? 'default');
        $supportEmail = trim($_POST['support_email'] ?? '');
        $marginPercent = (float)($_POST['price_margin_percent'] ?? 15.0);

        ChildPanelHelper::updateBranding($panel['id'], [
            'panel_name' => $panelName,
            'theme' => $theme,
            'support_email' => $supportEmail,
            'price_margin_percent' => $marginPercent
        ]);

        $msg = "Branding and margin settings saved successfully.";
        $stmt->execute([$panelId, $userId]);
        $panel = $stmt->fetch();
    }

    // 3. Reset Admin Password
    elseif ($action === 'reset_admin_password') {
        $newPass = $_POST['new_admin_password'] ?? '';
        $rRes = ChildPanelHelper::resetAdminPassword($panel['id'], $newPass);
        if ($rRes['success']) {
            $msg = "Child Panel admin password reset successfully.";
        } else {
            $err = $rRes['error'];
        }
    }

    // 4. ADVANCED PLAN ONLY: External Provider API Management
    elseif ($action === 'add_provider_api') {
        // Strict server-side plan check
        if ($panel['plan'] !== 'advanced' || empty($panel['external_api_enabled'])) {
            $err = "Access Denied: External Provider APIs are only available on the Advanced Child Panel plan.";
        } else {
            $provName = trim($_POST['provider_name'] ?? '');
            $apiUrl = trim($_POST['api_url'] ?? '');
            $apiKey = trim($_POST['api_key'] ?? '');

            if (empty($provName) || empty($apiUrl) || empty($apiKey)) {
                $err = "Provider Name, API URL and API Key are required.";
            } else {
                // Test connection first
                $test = ChildPanelHelper::testProviderApi($apiUrl, $apiKey);
                $initBalance = $test['success'] ? (float)$test['balance'] : 0.00;
                $initCurr = $test['success'] ? ($test['currency'] ?? 'USD') : 'USD';

                $pIns = $db->prepare("
                    INSERT INTO child_panel_providers (child_panel_id, name, api_url, api_key, balance, currency, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $pIns->execute([$panel['id'], $provName, $apiUrl, $apiKey, $initBalance, $initCurr]);

                $msg = "Provider API added successfully! " . ($test['success'] ? "Connection verified (Balance: $$initBalance)." : "Note: {$test['error']}");
                $activeTab = 'providers';
            }
        }
    }

    // 5. ADVANCED PLAN ONLY: Test Provider Connection
    elseif ($action === 'test_provider_api') {
        if ($panel['plan'] !== 'advanced' || empty($panel['external_api_enabled'])) {
            $err = "Access Denied: External Provider APIs are only available on the Advanced Child Panel plan.";
        } else {
            $provId = (int)$_POST['provider_id'];
            $pStmt = $db->prepare("SELECT * FROM child_panel_providers WHERE id = ? AND child_panel_id = ?");
            $pStmt->execute([$provId, $panel['id']]);
            $prov = $pStmt->fetch();

            if ($prov) {
                $test = ChildPanelHelper::testProviderApi($prov['api_url'], $prov['api_key']);
                if ($test['success']) {
                    $newBal = (float)$test['balance'];
                    $db->prepare("UPDATE child_panel_providers SET balance = ?, currency = ? WHERE id = ?")
                       ->execute([$newBal, $test['currency'] ?? 'USD', $provId]);
                    $msg = "Provider Connection Active! Balance: " . ($test['currency'] ?? 'USD') . " " . number_format($newBal, 2);
                } else {
                    $err = "Connection failed: " . $test['error'];
                }
            }
            $activeTab = 'providers';
        }
    }

    // 6. ADVANCED PLAN ONLY: Delete Provider
    elseif ($action === 'delete_provider_api') {
        if ($panel['plan'] !== 'advanced' || empty($panel['external_api_enabled'])) {
            $err = "Access Denied.";
        } else {
            $provId = (int)$_POST['provider_id'];
            $db->prepare("DELETE FROM child_panel_services WHERE external_provider_id = ? AND child_panel_id = ?")
               ->execute([$provId, $panel['id']]);
            $db->prepare("DELETE FROM child_panel_providers WHERE id = ? AND child_panel_id = ?")
               ->execute([$provId, $panel['id']]);
            $msg = "Provider and associated imported services removed.";
            $activeTab = 'providers';
        }
    }

    // 7. ADVANCED PLAN ONLY: Import External Service
    elseif ($action === 'import_external_service') {
        if ($panel['plan'] !== 'advanced' || empty($panel['external_api_enabled'])) {
            $err = "Access Denied: External Provider service imports are only available on the Advanced Child Panel plan.";
        } else {
            $provId = (int)$_POST['provider_id'];
            $extSrvId = trim($_POST['external_service_id'] ?? '');
            $srvName = trim($_POST['service_name'] ?? '');
            $category = trim($_POST['category_name'] ?? 'Imported Services');
            $originalRate = (float)($_POST['original_rate'] ?? 0.0);
            $sellingRate = (float)($_POST['selling_rate'] ?? 0.0);
            $minQty = max(1, (int)($_POST['min_quantity'] ?? 10));
            $maxQty = max($minQty, (int)($_POST['max_quantity'] ?? 100000));
            $desc = trim($_POST['description'] ?? '');

            if (empty($srvName)) {
                $err = "Service name is required.";
            } else {
                $sIns = $db->prepare("
                    INSERT INTO child_panel_services (
                        child_panel_id, source_type, external_provider_id, external_service_id,
                        category_name, name, description, original_rate, selling_rate,
                        min_quantity, max_quantity, status
                    ) VALUES (
                        ?, 'external', ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, 'active'
                    )
                ");
                $sIns->execute([
                    $panel['id'], $provId, $extSrvId,
                    $category, $srvName, $desc, $originalRate, $sellingRate,
                    $minQty, $maxQty
                ]);
                $msg = "Service '$srvName' imported successfully to your Child Panel.";
                $activeTab = 'services';
            }
        }
    }

    // 8. Toggle / Edit Service
    elseif ($action === 'toggle_service_status') {
        $srvId = (int)$_POST['service_id'];
        $curr = $_POST['status'] ?? 'active';
        $next = ($curr === 'active') ? 'inactive' : 'active';
        $db->prepare("UPDATE child_panel_services SET status = ? WHERE id = ? AND child_panel_id = ?")
           ->execute([$next, $srvId, $panel['id']]);
        $msg = "Service status updated.";
        $activeTab = 'services';
    }
}

// Fetch Providers if Advanced Plan
$providers = [];
if ($panel['plan'] === 'advanced') {
    $pQuery = $db->prepare("SELECT * FROM child_panel_providers WHERE child_panel_id = ? ORDER BY id DESC");
    $pQuery->execute([$panel['id']]);
    $providers = $pQuery->fetchAll();
}

// Fetch Imported Services
$importedServices = [];
$sQuery = $db->prepare("
    SELECT s.*, p.name as provider_name 
    FROM child_panel_services s 
    LEFT JOIN child_panel_providers p ON s.external_provider_id = p.id 
    WHERE s.child_panel_id = ? 
    ORDER BY s.id DESC
");
$sQuery->execute([$panel['id']]);
$importedServices = $sQuery->fetchAll();

// Count Parent Services available
$parentServicesCount = (int)$db->query("SELECT COUNT(*) FROM services WHERE status = 'active'")->fetchColumn();

// Decode DNS details
$dnsDetails = !empty($panel['dns_details']) ? json_decode($panel['dns_details'], true) : [];
$expectedNs1 = $panel['nameserver_1'] ?: $nameservers['ns1'];
$expectedNs2 = $panel['nameserver_2'] ?: $nameservers['ns2'];

require_once __DIR__ . '/../layouts/user_header.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
  <!-- Top Navigation & Title -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1.5">
        <a href="/child-panels" class="text-xs font-bold text-slate-400 hover:text-rose-600 transition-colors flex items-center gap-1">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
          <span>All Child Panels</span>
        </a>
        <span class="text-slate-300">/</span>
        <span class="text-xs font-bold text-slate-700"><?= e($panel['domain']) ?></span>
      </div>
      <div class="flex items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight"><?= e($panel['domain']) ?></h1>
        <span class="px-3 py-0.5 rounded-full text-xs font-black uppercase tracking-wider <?= $panel['plan'] === 'advanced' ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-rose-100 text-rose-700 border border-rose-200' ?>">
          <?= ucfirst($panel['plan']) ?> Plan
        </span>
      </div>
      <p class="text-xs text-slate-500 mt-1">Branded Title: <span class="font-bold text-slate-700"><?= e($panel['panel_name']) ?></span> • Created on <?= date('M d, Y', strtotime($panel['created_at'])) ?></p>
    </div>

    <!-- Quick Status Pill & Preview Button -->
    <div class="flex items-center gap-2 sm:gap-3">
      <?php if ($panel['status'] === 'active'): ?>
        <a href="http://<?= e($panel['domain']) ?>" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
          <i data-lucide="external-link" class="w-4 h-4"></i>
          <span>Visit Panel</span>
        </a>
      <?php else: ?>
        <a href="/child-panel-portal?child_panel=<?= $panel['id'] ?>" target="_blank" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5" title="Preview your child panel in sandbox mode">
          <i data-lucide="eye" class="w-4 h-4"></i>
          <span>Live Preview</span>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-bold flex items-center gap-3">
      <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm font-bold flex items-center gap-3">
      <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
      <span><?= e($err) ?></span>
    </div>
  <?php endif; ?>

  <!-- PROGRESS STEPPER (Section 14: What has been completed, what is pending, what to do next) -->
  <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-sm">
    <div class="text-xs font-black text-slate-400 uppercase tracking-wider mb-4">Provisioning & Activation Roadmap</div>
    
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 relative">
      <!-- Step 1: Payment -->
      <div class="p-3.5 rounded-2xl border bg-emerald-50/60 border-emerald-200 text-emerald-900">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Step 1</span>
          <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
        </div>
        <div class="font-black text-xs sm:text-sm">Payment Confirmed</div>
        <div class="text-[11px] text-emerald-700 mt-0.5">Deducted from wallet (<?= $panel['payment_currency'] ?> <?= number_format($panel['payment_amount'], 2) ?>)</div>
      </div>

      <!-- Step 2: Admin Approval -->
      <div class="p-3.5 rounded-2xl border <?= in_array($panel['status'], ['approved', 'active']) ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-amber-50/60 border-amber-200 text-amber-900' ?>">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[10px] font-black uppercase tracking-wider <?= in_array($panel['status'], ['approved', 'active']) ? 'text-emerald-600' : 'text-amber-600' ?>">Step 2</span>
          <?php if (in_array($panel['status'], ['approved', 'active'])): ?>
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
          <?php else: ?>
            <i data-lucide="clock" class="w-4 h-4 text-amber-600 animate-pulse"></i>
          <?php endif; ?>
        </div>
        <div class="font-black text-xs sm:text-sm">Admin Approval</div>
        <div class="text-[11px] <?= in_array($panel['status'], ['approved', 'active']) ? 'text-emerald-700' : 'text-amber-700' ?> mt-0.5">
          <?= in_array($panel['status'], ['approved', 'active']) ? 'Approved by Admin' : 'Under Review by Admin' ?>
        </div>
      </div>

      <!-- Step 3: Connect Nameservers -->
      <div class="p-3.5 rounded-2xl border <?= $panel['dns_status'] === 'dns_connected' ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-800' ?>">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[10px] font-black uppercase tracking-wider <?= $panel['dns_status'] === 'dns_connected' ? 'text-emerald-600' : 'text-slate-400' ?>">Step 3</span>
          <?php if ($panel['dns_status'] === 'dns_connected'): ?>
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
          <?php else: ?>
            <i data-lucide="arrow-right-circle" class="w-4 h-4 text-rose-500"></i>
          <?php endif; ?>
        </div>
        <div class="font-black text-xs sm:text-sm">Nameserver Setup</div>
        <div class="text-[11px] <?= $panel['dns_status'] === 'dns_connected' ? 'text-emerald-700' : 'text-rose-600 font-bold' ?> mt-0.5">
          <?= $panel['dns_status'] === 'dns_connected' ? 'Nameservers Detected' : 'Action Required Below' ?>
        </div>
      </div>

      <!-- Step 4: Active & SSL -->
      <div class="p-3.5 rounded-2xl border <?= $panel['status'] === 'active' ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-800' ?>">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[10px] font-black uppercase tracking-wider <?= $panel['status'] === 'active' ? 'text-emerald-600' : 'text-slate-400' ?>">Step 4</span>
          <?php if ($panel['status'] === 'active'): ?>
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
          <?php else: ?>
            <i data-lucide="shield" class="w-4 h-4 text-slate-400"></i>
          <?php endif; ?>
        </div>
        <div class="font-black text-xs sm:text-sm">Panel Online (SSL)</div>
        <div class="text-[11px] <?= $panel['status'] === 'active' ? 'text-emerald-700' : 'text-slate-500' ?> mt-0.5">
          <?= $panel['status'] === 'active' ? 'Live on Your Domain' : 'Awaiting DNS Check' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- NAVIGATION TABS -->
  <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
    <a href="/child-panel?id=<?= $panel['id'] ?>&tab=overview" class="px-4 py-2 rounded-xl text-xs font-bold transition-colors <?= $activeTab === 'overview' ? 'bg-rose-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
      Overview & Domain Setup
    </a>
    <a href="/child-panel?id=<?= $panel['id'] ?>&tab=branding" class="px-4 py-2 rounded-xl text-xs font-bold transition-colors <?= $activeTab === 'branding' ? 'bg-rose-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
      Branding & Settings
    </a>
    <a href="/child-panel?id=<?= $panel['id'] ?>&tab=admin_access" class="px-4 py-2 rounded-xl text-xs font-bold transition-colors <?= $activeTab === 'admin_access' ? 'bg-rose-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
      Admin Credentials
    </a>

    <?php if ($panel['plan'] === 'advanced'): ?>
      <a href="/child-panel?id=<?= $panel['id'] ?>&tab=providers" class="px-4 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5 <?= $activeTab === 'providers' ? 'bg-purple-600 text-white shadow-sm' : 'text-purple-700 bg-purple-50 hover:bg-purple-100' ?>">
        <i data-lucide="server" class="w-3.5 h-3.5"></i>
        <span>External APIs (<?= count($providers) ?>)</span>
      </a>
      <a href="/child-panel?id=<?= $panel['id'] ?>&tab=services" class="px-4 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5 <?= $activeTab === 'services' ? 'bg-purple-600 text-white shadow-sm' : 'text-purple-700 bg-purple-50 hover:bg-purple-100' ?>">
        <i data-lucide="layers" class="w-3.5 h-3.5"></i>
        <span>Import Services (<?= count($importedServices) ?>)</span>
      </a>
    <?php endif; ?>
  </div>

  <!-- TAB CONTENT -->

  <?php if ($activeTab === 'overview'): ?>
    <!-- SECTION 5 & 6: NAMESERVER-BASED DOMAIN SETUP & LIVE VERIFICATION -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Domain Setup Box -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-sm">
          <div class="flex items-center justify-between gap-3 mb-4 pb-4 border-b border-slate-100">
            <div>
              <h2 class="text-lg font-black text-slate-800 tracking-tight">Connect Your Domain</h2>
              <p class="text-xs text-slate-500 mt-0.5">Follow the instructions below to point your domain to your new Child Panel.</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-black <?= $panel['dns_status'] === 'dns_connected' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
              <?= $panel['dns_status'] === 'dns_connected' ? 'DNS Connected' : 'Pending Nameserver Setup' ?>
            </span>
          </div>

          <!-- Configured Nameservers Box -->
          <div class="p-5 rounded-2xl bg-gradient-to-r from-rose-50/80 to-purple-50/80 border border-rose-200/80 mb-6">
            <div class="text-xs font-black uppercase tracking-wider text-rose-800 mb-3 flex items-center gap-1.5">
              <i data-lucide="server" class="w-4 h-4 text-rose-600"></i>
              <span>Assign These Nameservers at Your Domain Registrar:</span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="p-3.5 rounded-xl bg-white border border-rose-200 flex items-center justify-between">
                <div>
                  <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Nameserver 1</div>
                  <div class="font-mono text-sm font-bold text-slate-800 mt-0.5" id="ns1-text"><?= e($expectedNs1) ?></div>
                </div>
                <button type="button" onclick="navigator.clipboard.writeText('<?= e($expectedNs1) ?>'); this.innerText='Copied!';" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                  Copy
                </button>
              </div>

              <div class="p-3.5 rounded-xl bg-white border border-rose-200 flex items-center justify-between">
                <div>
                  <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Nameserver 2</div>
                  <div class="font-mono text-sm font-bold text-slate-800 mt-0.5" id="ns2-text"><?= e($expectedNs2) ?></div>
                </div>
                <button type="button" onclick="navigator.clipboard.writeText('<?= e($expectedNs2) ?>'); this.innerText='Copied!';" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                  Copy
                </button>
              </div>
            </div>
          </div>

          <!-- Step-by-Step Instructions -->
          <div class="space-y-3 mb-6">
            <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">Setup Instructions</h3>
            <ol class="space-y-2.5 text-xs text-slate-600">
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 text-[11px]">1</span>
                <span>Login to your domain registrar (GoDaddy, Namecheap, Cloudflare, Hostinger, Google Domains, etc.).</span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 text-[11px]">2</span>
                <span>Open your domain management dashboard and select <strong>DNS / Custom Nameservers</strong>.</span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 text-[11px]">3</span>
                <span>Replace any existing nameservers with <strong class="font-mono text-slate-800"><?= e($expectedNs1) ?></strong> and <strong class="font-mono text-slate-800"><?= e($expectedNs2) ?></strong>.</span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 text-[11px]">4</span>
                <span>Save your registrar changes.</span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 text-[11px]">5</span>
                <span>Wait for DNS propagation (usually 5 to 30 minutes, up to 24 hours depending on registrar).</span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 text-[11px]">6</span>
                <span>Return here and click the <strong>Verify Domain</strong> button below to test live resolution.</span>
              </li>
            </ol>
          </div>

          <!-- Verify Domain Action Button -->
          <div class="pt-5 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="text-xs text-slate-500">
              Last Checked: <span class="font-bold text-slate-700"><?= $panel['dns_last_checked'] ? date('M d, Y H:i:s', strtotime($panel['dns_last_checked'])) : 'Never' ?></span>
            </div>
            
            <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>">
              <input type="hidden" name="action" value="verify_domain">
              <button type="submit" class="px-6 py-2.5 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                <span>Verify Domain Now</span>
              </button>
            </form>
          </div>
        </div>

        <!-- DNS Query Live Details Report -->
        <?php if (!empty($dnsDetails)): ?>
          <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm">
            <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
              <i data-lucide="terminal" class="w-4 h-4 text-slate-500"></i>
              <span>Live DNS Inspection Log</span>
            </h3>
            <div class="p-3.5 rounded-2xl bg-slate-900 text-slate-200 font-mono text-xs space-y-1.5 overflow-x-auto">
              <div><span class="text-slate-500">Target Domain:</span> <?= e($panel['domain']) ?></div>
              <div><span class="text-slate-500">Expected NS:</span> <?= implode(', ', $dnsDetails['expected_ns'] ?? []) ?></div>
              <div><span class="text-slate-500">Detected NS:</span> <?= !empty($dnsDetails['detected_ns']) ? implode(', ', $dnsDetails['detected_ns']) : '<span class="text-amber-400">None detected yet</span>' ?></div>
              <div><span class="text-slate-500">Resolved IPs:</span> <?= !empty($dnsDetails['detected_ips']) ? implode(', ', $dnsDetails['detected_ips']) : '<span class="text-slate-400">None</span>' ?></div>
              <div><span class="text-slate-500">Match Status:</span> <?= !empty($dnsDetails['matched']) ? '<span class="text-emerald-400 font-bold">MATCHED & VALIDATED</span>' : '<span class="text-amber-400">WAITING FOR PROPAGATION</span>' ?></div>
              <div><span class="text-slate-500">System Report:</span> <?= e($dnsDetails['reason'] ?? '') ?></div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick Summary Card -->
      <div class="space-y-6">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
          <h3 class="text-sm font-black text-slate-800 tracking-tight pb-3 border-b border-slate-100">
            Panel Overview
          </h3>

          <div class="space-y-3 text-xs">
            <div>
              <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Plan</div>
              <div class="font-extrabold text-slate-800 text-sm mt-0.5"><?= e($currentPlan['name']) ?></div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Primary Service Source</div>
              <div class="font-bold text-slate-700 mt-0.5"><?= e($currentPlan['service_source']) ?></div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">External API Permission</div>
              <div class="font-bold mt-0.5 <?= $panel['external_api_enabled'] ? 'text-purple-600' : 'text-slate-500' ?>">
                <?= $panel['external_api_enabled'] ? 'Enabled (Full Provider Sourcing)' : 'Locked to Parent SMM Only' ?>
              </div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">SSL Security</div>
              <div class="font-bold mt-0.5 flex items-center gap-1.5">
                <?php if ($panel['ssl_status'] === 'ssl_active'): ?>
                  <i data-lucide="lock" class="w-3.5 h-3.5 text-emerald-600"></i>
                  <span class="text-emerald-700">SSL Certificate Active</span>
                <?php else: ?>
                  <i data-lucide="shield-alert" class="w-3.5 h-3.5 text-amber-500"></i>
                  <span class="text-amber-700">SSL Pending DNS Connection</span>
                <?php endif; ?>
              </div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Monthly Renewal</div>
              <div class="font-bold text-slate-800 mt-0.5">
                <?= $panel['expires_at'] ? date('M d, Y', strtotime($panel['expires_at'])) : 'Active' ?>
              </div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Available Catalog Services</div>
              <div class="font-bold text-slate-800 mt-0.5">
                <?= $parentServicesCount ?> Parent Services <?= count($importedServices) > 0 ? " + " . count($importedServices) . " Imported" : "" ?>
              </div>
            </div>
          </div>

          <div class="pt-3 border-t border-slate-100">
            <a href="/child-panel-portal?child_panel=<?= $panel['id'] ?>" target="_blank" class="w-full py-2.5 rounded-2xl bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white font-bold text-xs transition-colors flex items-center justify-center gap-2">
              <i data-lucide="layout-grid" class="w-4 h-4"></i>
              <span>Launch Child Panel</span>
            </a>
          </div>
        </div>
      </div>
    </div>

  <?php elseif ($activeTab === 'branding'): ?>
    <!-- BRANDING & SETTINGS -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm max-w-3xl">
      <h2 class="text-lg font-black text-slate-800 tracking-tight mb-1">Branding & Margin Configuration</h2>
      <p class="text-xs text-slate-500 mb-6">Customize your panel's title, default customer profit margin, and visual theme.</p>

      <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>&tab=branding" class="space-y-5">
        <input type="hidden" name="action" value="update_branding">

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Panel Brand Name</label>
          <input type="text" name="panel_name" required value="<?= e($panel['panel_name']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-medium outline-none focus:border-rose-500">
          <p class="text-[11px] text-slate-400 mt-1">Appears in header, browser tabs, and customer receipts.</p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Support Contact Email</label>
          <input type="email" name="support_email" value="<?= e($panel['support_email']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-medium outline-none focus:border-rose-500">
          <p class="text-[11px] text-slate-400 mt-1">Shown to your customers on the support ticket screen.</p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
            Default Profit Margin % on Parent Services
          </label>
          <div class="flex items-center gap-2">
            <input type="number" step="0.1" min="0" max="500" name="price_margin_percent" value="<?= (float)$panel['price_margin_percent'] ?>" class="w-40 px-4 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-bold outline-none focus:border-rose-500">
            <span class="text-xs font-bold text-slate-500">% markup on main panel rates</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-1">
            Example: If a parent service costs ₹100 and margin is 15%, your customers will see and buy at ₹115. You keep the ₹15 profit automatically!
          </p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Panel Theme</label>
          <select name="theme" class="w-full sm:w-64 px-4 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-medium outline-none focus:border-rose-500 bg-white">
            <option value="default" <?= $panel['theme'] === 'default' ? 'selected' : '' ?>>Rose Modern</option>
            <option value="dark" <?= $panel['theme'] === 'dark' ? 'selected' : '' ?>>Midnight Dark</option>
            <option value="cyan" <?= $panel['theme'] === 'cyan' ? 'selected' : '' ?>>Neon Cyan</option>
            <option value="emerald" <?= $panel['theme'] === 'emerald' ? 'selected' : '' ?>>Emerald Pro</option>
          </select>
        </div>

        <div class="pt-4 border-t border-slate-100">
          <button type="submit" class="px-6 py-2.5 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-all">
            Save Branding Changes
          </button>
        </div>
      </form>
    </div>

  <?php elseif ($activeTab === 'admin_access'): ?>
    <!-- ADMIN CREDENTIALS -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm max-w-2xl">
      <h2 class="text-lg font-black text-slate-800 tracking-tight mb-1">Child Panel Admin Console Credentials</h2>
      <p class="text-xs text-slate-500 mb-6">Manage administrator login details for your own provisioned panel.</p>

      <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2 mb-6 text-xs">
        <div class="flex items-center justify-between">
          <span class="text-slate-400 font-bold uppercase text-[10px]">Admin Username:</span>
          <span class="font-bold text-slate-800"><?= e($panel['admin_username']) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-slate-400 font-bold uppercase text-[10px]">Admin Email:</span>
          <span class="font-bold text-slate-800"><?= e($panel['admin_email']) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-slate-400 font-bold uppercase text-[10px]">Direct Admin URL:</span>
          <span class="font-mono text-slate-700">http://<?= e($panel['domain']) ?>/admin</span>
        </div>
      </div>

      <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>&tab=admin_access" class="space-y-4">
        <input type="hidden" name="action" value="reset_admin_password">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Set New Admin Password</label>
          <input type="password" name="new_admin_password" required minlength="6" placeholder="Enter new secure password" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-medium outline-none focus:border-rose-500">
        </div>
        <button type="submit" class="px-6 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-sm transition-all">
          Update Admin Password
        </button>
      </form>
    </div>

  <?php elseif ($activeTab === 'providers' && $panel['plan'] === 'advanced'): ?>
    <!-- ADVANCED PLAN: EXTERNAL SMM PROVIDER APIS -->
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-100 text-purple-700 border border-purple-200">
              Advanced Plan Feature
            </span>
          </div>
          <h2 class="text-xl font-black text-slate-800 tracking-tight mt-1">External SMM Provider APIs</h2>
          <p class="text-xs text-slate-500 mt-0.5">Connect your own external upstream SMM providers to import services and route orders directly.</p>
        </div>
        <button onclick="document.getElementById('add-provider-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2 shrink-0">
          <i data-lucide="plus-circle" class="w-4 h-4"></i>
          <span>Add Provider API</span>
        </button>
      </div>

      <?php if (empty($providers)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 p-10 text-center shadow-sm">
          <div class="w-14 h-14 rounded-2xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="server" class="w-7 h-7"></i>
          </div>
          <h3 class="text-sm font-black text-slate-800">No External Providers Connected Yet</h3>
          <p class="text-xs text-slate-500 max-w-md mx-auto mt-1 mb-4">
            As an Advanced Child Panel owner, you can connect any standard SMM API v2 provider to import and sell external services alongside the main panel.
          </p>
          <button onclick="document.getElementById('add-provider-modal').classList.remove('hidden')" class="px-4 py-2 rounded-xl bg-purple-600 text-white text-xs font-bold hover:bg-purple-700 transition-colors inline-flex items-center gap-1.5">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span>Add Your First Provider</span>
          </button>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <?php foreach ($providers as $prov): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-3">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <h3 class="text-sm font-black text-slate-800"><?= e($prov['name']) ?></h3>
                  <div class="text-[11px] font-mono text-slate-400 truncate max-w-xs mt-0.5"><?= e($prov['api_url']) ?></div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider <?= $prov['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                  <?= ucfirst($prov['status']) ?>
                </span>
              </div>

              <div class="p-3 rounded-xl bg-purple-50/60 border border-purple-100 flex items-center justify-between text-xs">
                <div>
                  <div class="text-[10px] text-purple-600 font-bold uppercase tracking-wider">Provider Balance</div>
                  <div class="text-base font-black text-purple-900 mt-0.5">
                    <?= e($prov['currency']) ?> <?= number_format($prov['balance'], 2) ?>
                  </div>
                </div>
                <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>&tab=providers">
                  <input type="hidden" name="action" value="test_provider_api">
                  <input type="hidden" name="provider_id" value="<?= $prov['id'] ?>">
                  <button type="submit" class="px-3 py-1.5 rounded-xl bg-white hover:bg-purple-100 text-purple-700 font-bold text-xs border border-purple-200 transition-colors shadow-xs flex items-center gap-1">
                    <i data-lucide="refresh-cw" class="w-3 h-3"></i>
                    <span>Test & Sync</span>
                  </button>
                </form>
              </div>

              <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                <a href="/child-panel?id=<?= $panel['id'] ?>&tab=services&provider=<?= $prov['id'] ?>" class="text-xs font-bold text-purple-600 hover:text-purple-700 flex items-center gap-1">
                  <i data-lucide="download" class="w-3.5 h-3.5"></i>
                  <span>Import Services</span>
                </a>

                <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>&tab=providers" onsubmit="return confirm('Remove this provider and its imported services?');">
                  <input type="hidden" name="action" value="delete_provider_api">
                  <input type="hidden" name="provider_id" value="<?= $prov['id'] ?>">
                  <button type="submit" class="text-xs font-bold text-rose-500 hover:text-rose-700 flex items-center gap-1">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span>Delete</span>
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Add Provider Modal -->
      <div id="add-provider-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-xl max-w-lg w-full">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="text-base font-black text-slate-800">Add External SMM Provider API</h3>
            <button onclick="document.getElementById('add-provider-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>

          <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>&tab=providers" class="space-y-4">
            <input type="hidden" name="action" value="add_provider_api">

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Provider Display Name</label>
              <input type="text" name="provider_name" required placeholder="e.g. SMM Kings API" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none focus:border-purple-500">
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">API URL (Endpoint)</label>
              <input type="url" name="api_url" required placeholder="https://provider.com/api/v2" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none focus:border-purple-500 font-mono">
              <p class="text-[11px] text-slate-400 mt-1">Must support Standard SMM API v2 (action=balance, action=services, action=add).</p>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">API Key</label>
              <input type="password" name="api_key" required placeholder="Paste provider API key" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none focus:border-purple-500 font-mono">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
              <button type="button" onclick="document.getElementById('add-provider-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100">
                Cancel
              </button>
              <button type="submit" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm">
                Save & Connect Provider
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  <?php elseif ($activeTab === 'services' && $panel['plan'] === 'advanced'): ?>
    <!-- ADVANCED PLAN: IMPORT SERVICES & MANAGE MARGINS -->
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-black text-slate-800 tracking-tight">Import & Manage Services</h2>
          <p class="text-xs text-slate-500 mt-0.5">Import external services from connected providers and configure your custom selling prices.</p>
        </div>

        <?php if (!empty($providers)): ?>
          <button onclick="document.getElementById('fetch-services-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2 shrink-0">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span>Fetch & Import from API</span>
          </button>
        <?php endif; ?>
      </div>

      <!-- Imported Services Table/List -->
      <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-black text-slate-800">Your Imported Services (<?= count($importedServices) ?>)</h3>
          <span class="text-xs text-slate-400">Main panel services (<?= $parentServicesCount ?>) are automatically available</span>
        </div>

        <?php if (empty($importedServices)): ?>
          <div class="text-center py-8 text-xs text-slate-400">
            No external services imported yet. Click "Fetch & Import from API" to bring in services from your connected providers.
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-wider">
                  <th class="pb-3">Service</th>
                  <th class="pb-3">Category</th>
                  <th class="pb-3">Provider</th>
                  <th class="pb-3">Cost Rate</th>
                  <th class="pb-3">Selling Rate</th>
                  <th class="pb-3">Profit</th>
                  <th class="pb-3">Status</th>
                  <th class="pb-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <?php foreach ($importedServices as $s): ?>
                  <?php $profit = (float)$s['selling_rate'] - (float)$s['original_rate']; ?>
                  <tr>
                    <td class="py-3 font-bold text-slate-800 max-w-xs truncate"><?= e($s['name']) ?></td>
                    <td class="py-3 text-slate-600"><?= e($s['category_name']) ?></td>
                    <td class="py-3 text-purple-700 font-semibold"><?= e($s['provider_name'] ?: 'External API') ?></td>
                    <td class="py-3 font-mono text-slate-500">$<?= number_format($s['original_rate'], 3) ?></td>
                    <td class="py-3 font-mono font-bold text-slate-800">$<?= number_format($s['selling_rate'], 3) ?></td>
                    <td class="py-3 font-mono font-bold text-emerald-600">+$<?= number_format($profit, 3) ?></td>
                    <td class="py-3">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $s['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= ucfirst($s['status']) ?>
                      </span>
                    </td>
                    <td class="py-3 text-right">
                      <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>&tab=services" class="inline-block">
                        <input type="hidden" name="action" value="toggle_service_status">
                        <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="status" value="<?= $s['status'] ?>">
                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors <?= $s['status'] === 'active' ? 'bg-slate-100 text-slate-700 hover:bg-slate-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                          <?= $s['status'] === 'active' ? 'Disable' : 'Enable' ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick Manual Import Modal -->
      <?php if (!empty($providers)): ?>
        <div id="fetch-services-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-xl max-w-lg w-full">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
              <h3 class="text-base font-black text-slate-800">Import Service from Provider</h3>
              <button onclick="document.getElementById('fetch-services-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
              </button>
            </div>

            <form method="POST" action="/child-panel?id=<?= $panel['id'] ?>&tab=services" class="space-y-4">
              <input type="hidden" name="action" value="import_external_service">

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Select Provider</label>
                <select name="provider_id" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none bg-white">
                  <?php foreach ($providers as $pr): ?>
                    <option value="<?= $pr['id'] ?>"><?= e($pr['name']) ?> (<?= e($pr['api_url']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">External Provider Service ID</label>
                <input type="text" name="external_service_id" required placeholder="e.g. 1024" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none font-mono">
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Service Name</label>
                <input type="text" name="service_name" required placeholder="e.g. Instagram Followers [Non-Drop]" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none">
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category</label>
                <input type="text" name="category_name" required placeholder="e.g. Instagram Followers" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none">
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Cost Rate (per 1k)</label>
                  <input type="number" step="0.001" min="0" name="original_rate" id="import-cost" required placeholder="0.50" oninput="calcSelling()" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none font-mono">
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Your Selling Rate (per 1k)</label>
                  <input type="number" step="0.001" min="0" name="selling_rate" id="import-sell" required placeholder="0.75" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none font-mono font-bold text-emerald-700">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Min Qty</label>
                  <input type="number" name="min_quantity" value="10" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none font-mono">
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Max Qty</label>
                  <input type="number" name="max_quantity" value="10000" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm outline-none font-mono">
                </div>
              </div>

              <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('fetch-services-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100">
                  Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm">
                  Import Service
                </button>
              </div>
            </form>
          </div>
        </div>

        <script>
        function calcSelling() {
          const cost = parseFloat(document.getElementById('import-cost').value) || 0;
          if (cost > 0) {
            // Default 30% margin
            document.getElementById('import-sell').value = (cost * 1.3).toFixed(3);
          }
        }
        </script>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
