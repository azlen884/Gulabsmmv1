<?php
$pageTitle = 'Dashboard - Admin Console';
$adminPage = 'dashboard';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();

// Fast stats
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(charge), 0) FROM orders")->fetchColumn();
$pendingOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$activeServices = (int)$db->query("SELECT COUNT(*) FROM services WHERE status = 'active'")->fetchColumn();
$openTickets = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

// Recent orders
$recentOrders = $db->query("
    SELECT o.*, u.username, s.name AS service_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN services s ON o.service_id = s.id 
    ORDER BY o.id DESC LIMIT 6
")->fetchAll();
?>

<!-- Title & Fast Overview -->
<div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">System Control Center</h1>
    <p class="text-xs text-slate-500 mt-1">Live metrics, provider health, user activity, and order execution.</p>
  </div>
  <div class="flex items-center gap-2">
    <a href="/admin/orders" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
      View All Orders
    </a>
    <a href="/admin/services" class="px-4 py-2 rounded-xl bg-rose-500 text-white font-bold text-xs hover:bg-rose-600 transition-colors">
      + New Service
    </a>
  </div>
</div>

<!-- Metrics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3 overflow-hidden">
    <div class="min-w-0 flex-1">
      <span class="text-xs font-semibold text-slate-400 block mb-1 truncate">Total Orders</span>
      <div class="text-2xl font-black text-slate-800 truncate"><?= number_format($totalOrders) ?></div>
      <div class="text-[11px] text-amber-600 font-bold mt-1 truncate"><?= $pendingOrders ?> Pending</div>
    </div>
    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0">
      <i data-lucide="shopping-cart" class="w-6 h-6"></i>
    </div>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3 overflow-hidden">
    <div class="min-w-0 flex-1">
      <span class="text-xs font-semibold text-slate-400 block mb-1 truncate">Registered Users</span>
      <div class="text-2xl font-black text-slate-800 truncate"><?= number_format($totalUsers) ?></div>
      <div class="text-[11px] text-emerald-600 font-bold mt-1 truncate">Active Accounts</div>
    </div>
    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
      <i data-lucide="users" class="w-6 h-6"></i>
    </div>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3 overflow-hidden">
    <div class="min-w-0 flex-1">
      <span class="text-xs font-semibold text-slate-400 block mb-1 truncate">Gross Revenue</span>
      <div class="text-2xl font-black text-slate-800 truncate">$<?= number_format($totalRevenue, 2) ?></div>
      <div class="text-[11px] text-slate-400 font-bold mt-1 truncate">Lifetime Volume</div>
    </div>
    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
      <i data-lucide="dollar-sign" class="w-6 h-6"></i>
    </div>
  </div>

  <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3 overflow-hidden">
    <div class="min-w-0 flex-1">
      <span class="text-xs font-semibold text-slate-400 block mb-1 truncate">Support Inquiries</span>
      <div class="text-2xl font-black text-slate-800 truncate"><?= $openTickets ?></div>
      <div class="text-[11px] text-rose-600 font-bold mt-1 truncate">Awaiting Reply</div>
    </div>
    <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
      <i data-lucide="message-square" class="w-6 h-6"></i>
    </div>
  </div>
</div>

<!-- Recent Orders Card Section (NO TABLE UI!) -->
<div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm mb-6 overflow-hidden">
  <div class="flex items-center justify-between mb-4 gap-2">
    <div class="min-w-0">
      <h3 class="font-bold text-base text-slate-800 truncate">Recent Customer Orders</h3>
      <p class="text-xs text-slate-400 truncate">Real-time order feed across all users</p>
    </div>
    <a href="/admin/orders" class="text-xs font-bold text-rose-600 hover:text-rose-700 shrink-0 whitespace-nowrap">View All Orders →</a>
  </div>

  <?php if (empty($recentOrders)): ?>
    <div class="text-center py-8 text-xs text-slate-400">No orders placed yet.</div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($recentOrders as $ro): ?>
        <div class="p-4 rounded-2xl border border-slate-100 hover:border-slate-200 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 overflow-hidden">
          <div class="flex items-center gap-3.5 min-w-0 flex-1">
            <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center font-bold text-xs text-slate-700 shrink-0">
              #<?= $ro['id'] ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="font-bold text-xs sm:text-sm text-slate-800 break-words line-clamp-1">
                <?= e($ro['service_name']) ?>
              </div>
              <div class="text-[11px] text-slate-400 mt-0.5 truncate">
                User: <span class="font-bold text-slate-700">@<?= e($ro['username']) ?></span> • Qty: <?= number_format($ro['quantity']) ?> • Link: <span class="text-rose-500 font-mono"><?= e(substr($ro['link'], 0, 30)) ?>...</span>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between sm:justify-end gap-4 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 shrink-0">
            <div class="text-left sm:text-right">
              <div class="text-xs font-extrabold text-slate-800 whitespace-nowrap">$<?= number_format($ro['charge'], 4) ?></div>
              <div class="text-[10px] text-slate-400 whitespace-nowrap"><?= date('d M, h:i A', strtotime($ro['created_at'])) ?></div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap <?= $ro['status'] === 'completed' ? 'bg-emerald-50 text-emerald-600' : ($ro['status'] === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600') ?>">
              <?= ucfirst($ro['status']) ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Admin Quick Navigation Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <a href="/admin/providers" class="bg-white p-5 rounded-2xl border border-slate-200 hover:border-rose-400 transition-all block shadow-sm">
    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center mb-3">
      <i data-lucide="server" class="w-5 h-5"></i>
    </div>
    <div class="font-bold text-xs text-slate-800">Provider APIs</div>
    <div class="text-[11px] text-slate-400 mt-1">Check balances and configure keys</div>
  </a>

  <a href="/admin/provider-services" class="bg-white p-5 rounded-2xl border border-slate-200 hover:border-rose-400 transition-all block shadow-sm">
    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
      <i data-lucide="download" class="w-5 h-5"></i>
    </div>
    <div class="font-bold text-xs text-slate-800">Import Services</div>
    <div class="text-[11px] text-slate-400 mt-1">Sync services from external provider</div>
  </a>

  <a href="/admin/currencies" class="bg-white p-5 rounded-2xl border border-slate-200 hover:border-rose-400 transition-all block shadow-sm">
    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
      <i data-lucide="coins" class="w-5 h-5"></i>
    </div>
    <div class="font-bold text-xs text-slate-800">INR / Currency Rates</div>
    <div class="text-[11px] text-slate-400 mt-1">Configure default INR and rates</div>
  </a>

  <a href="/admin/sliders" class="bg-white p-5 rounded-2xl border border-slate-200 hover:border-rose-400 transition-all block shadow-sm">
    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-3">
      <i data-lucide="image" class="w-5 h-5"></i>
    </div>
    <div class="font-bold text-xs text-slate-800">Banner Sliders</div>
    <div class="text-[11px] text-slate-400 mt-1">Customize user dashboard banners</div>
  </a>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
