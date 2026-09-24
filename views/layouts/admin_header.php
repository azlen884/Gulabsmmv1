<?php
require_once __DIR__ . '/../../config/database.php';

// Verify admin authentication
if (!is_admin()) {
    header("Location: /admin/login");
    exit;
}

$adminUser = current_user();
$adminPage = $adminPage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'RoseSMM Admin Panel') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            rose: {
              50: '#FFF0F3',
              100: '#FFE2E8',
              200: '#FCD3DC',
              500: '#FF3B69',
              600: '#E11D48',
              700: '#BE123C',
              800: '#9F1239',
              900: '#881337',
            }
          }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <?php render_theme_head_tags(); ?>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col <?= get_theme_body_class() ?>">

<!-- Mobile Admin Sidebar Drawer & Backdrop -->
<div id="admin-mobile-backdrop" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 hidden lg:hidden" onclick="toggleAdminSidebar()"></div>
<aside id="admin-mobile-drawer" class="fixed inset-y-0 left-0 w-72 bg-slate-900 text-slate-300 z-50 flex flex-col justify-between overflow-y-auto transform -translate-x-full transition-transform duration-200 ease-in-out lg:hidden shadow-2xl">
  <div class="p-5">
    <div class="flex items-center justify-between mb-6">
      <a href="/admin" class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-2xl bg-rose-500 text-white flex items-center justify-center font-black text-sm">
          R
        </div>
        <div>
          <span class="text-base font-bold text-white block leading-tight">RoseSMM</span>
          <span class="text-[10px] uppercase font-bold tracking-wider text-rose-400 block">Administration</span>
        </div>
      </a>
      <button onclick="toggleAdminSidebar()" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors" aria-label="Close Admin Menu">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Admin Mobile Navigation Items -->
    <nav class="space-y-1 text-xs font-semibold">
      <a href="/admin" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'dashboard' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'dashboard' ? 'bg-white/20 border border-white/30 text-white' : 'bg-indigo-500/15 border border-indigo-500/30 text-indigo-400' ?>">
          <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
        </div>
        <span>Dashboard</span>
      </a>
      <a href="/admin/orders" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'orders' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'orders' ? 'bg-white/20 border border-white/30 text-white' : 'bg-sky-500/15 border border-sky-500/30 text-sky-400' ?>">
          <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
        </div>
        <span>Orders</span>
      </a>
      <a href="/admin/drip-feed" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'drip-feed' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'drip-feed' ? 'bg-white/20 border border-white/30 text-white' : 'bg-blue-500/15 border border-blue-500/30 text-blue-400' ?>">
          <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
        </div>
        <span>Drip-Feed Orders</span>
      </a>
      <a href="/admin/refill" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'refill' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'refill' ? 'bg-white/20 border border-white/30 text-white' : 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400' ?>">
          <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
        </div>
        <span>Auto Refill</span>
      </a>
      <a href="/admin/refunds" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'refunds' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'refunds' ? 'bg-white/20 border border-white/30 text-white' : 'bg-amber-500/15 border border-amber-500/30 text-amber-400' ?>">
          <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
        </div>
        <span>Auto Refund</span>
      </a>
      <a href="/admin/coupons" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'coupons' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'coupons' ? 'bg-white/20 border border-white/30 text-white' : 'bg-purple-500/15 border border-purple-500/30 text-purple-400' ?>">
          <i data-lucide="tag" class="w-3.5 h-3.5"></i>
        </div>
        <span>Coupons & Discounts</span>
      </a>
      <a href="/admin/flash-sales" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'flash-sales' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'flash-sales' ? 'bg-white/20 border border-white/30 text-white' : 'bg-rose-500/15 border border-rose-500/30 text-rose-400' ?>">
          <i data-lucide="zap" class="w-3.5 h-3.5 fill-rose-400/20"></i>
        </div>
        <span>Flash Sales</span>
      </a>
      <a href="/admin/ticket-automation" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'ticket-automation' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'ticket-automation' ? 'bg-white/20 border border-white/30 text-white' : 'bg-cyan-500/15 border border-cyan-500/30 text-cyan-400' ?>">
          <i data-lucide="bot" class="w-3.5 h-3.5"></i>
        </div>
        <span>Ticket Automation</span>
      </a>
      <a href="/admin/cron-jobs" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'cron-jobs' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'cron-jobs' ? 'bg-white/20 border border-white/30 text-white' : 'bg-green-500/15 border border-green-500/30 text-green-400' ?>">
          <i data-lucide="clock-4" class="w-3.5 h-3.5"></i>
        </div>
        <span>Cron Automation</span>
      </a>
      <a href="/admin/users" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'users' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'users' ? 'bg-white/20 border border-white/30 text-white' : 'bg-indigo-500/15 border border-indigo-500/30 text-indigo-400' ?>">
          <i data-lucide="users" class="w-3.5 h-3.5"></i>
        </div>
        <span>Users</span>
      </a>
      <a href="/admin/services" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'services' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'services' ? 'bg-white/20 border border-white/30 text-white' : 'bg-violet-500/15 border border-violet-500/30 text-violet-400' ?>">
          <i data-lucide="layers" class="w-3.5 h-3.5"></i>
        </div>
        <span>Services</span>
      </a>
      <a href="/admin/categories" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'categories' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'categories' ? 'bg-white/20 border border-white/30 text-white' : 'bg-amber-500/15 border border-amber-500/30 text-amber-400' ?>">
          <i data-lucide="folder" class="w-3.5 h-3.5"></i>
        </div>
        <span>Categories</span>
      </a>
      <a href="/admin/providers" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'providers' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'providers' ? 'bg-white/20 border border-white/30 text-white' : 'bg-cyan-500/15 border border-cyan-500/30 text-cyan-400' ?>">
          <i data-lucide="server" class="w-3.5 h-3.5"></i>
        </div>
        <span>Providers</span>
      </a>
      <a href="/admin/provider-services" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'provider-services' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'provider-services' ? 'bg-white/20 border border-white/30 text-white' : 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400' ?>">
          <i data-lucide="download" class="w-3.5 h-3.5"></i>
        </div>
        <span>Import Services</span>
      </a>
      <a href="/admin/transactions" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'transactions' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'transactions' ? 'bg-white/20 border border-white/30 text-white' : 'bg-teal-500/15 border border-teal-500/30 text-teal-400' ?>">
          <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
        </div>
        <span>Transactions</span>
      </a>
      <a href="/admin/referrals" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= in_array($adminPage, ['referrals', 'refer-earn']) ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= in_array($adminPage, ['referrals', 'refer-earn']) ? 'bg-white/20 border border-white/30 text-white' : 'bg-pink-500/15 border border-pink-500/30 text-pink-400' ?>">
          <i data-lucide="gift" class="w-3.5 h-3.5"></i>
        </div>
        <span>Refer & Earn</span>
      </a>
      <a href="/admin/payment-gateways" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'payment-gateways' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'payment-gateways' ? 'bg-white/20 border border-white/30 text-white' : 'bg-blue-500/15 border border-blue-500/30 text-blue-400' ?>">
          <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
        </div>
        <span>Payment Gateways</span>
      </a>
      <a href="/admin/currencies" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'currencies' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'currencies' ? 'bg-white/20 border border-white/30 text-white' : 'bg-amber-500/15 border border-amber-500/30 text-amber-400' ?>">
          <i data-lucide="coins" class="w-3.5 h-3.5"></i>
        </div>
        <span>Currencies (INR/USD)</span>
      </a>
      <a href="/admin/sliders" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'sliders' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'sliders' ? 'bg-white/20 border border-white/30 text-white' : 'bg-purple-500/15 border border-purple-500/30 text-purple-400' ?>">
          <i data-lucide="image" class="w-3.5 h-3.5"></i>
        </div>
        <span>Sliders & Banners</span>
      </a>
      <a href="/admin/tickets" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'tickets' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'tickets' ? 'bg-white/20 border border-white/30 text-white' : 'bg-orange-500/15 border border-orange-500/30 text-orange-400' ?>">
          <i data-lucide="headset" class="w-3.5 h-3.5"></i>
        </div>
        <span>Support Tickets</span>
      </a>
      <a href="/admin/notifications" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'notifications' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'notifications' ? 'bg-white/20 border border-white/30 text-white' : 'bg-blue-500/15 border border-blue-500/30 text-blue-400' ?>">
          <i data-lucide="bell" class="w-3.5 h-3.5"></i>
        </div>
        <span>Broadcast Notifications</span>
      </a>
      <a href="/admin/settings" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'settings' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'settings' ? 'bg-white/20 border border-white/30 text-white' : 'bg-slate-700/50 border border-slate-600/50 text-slate-300' ?>">
          <i data-lucide="settings" class="w-3.5 h-3.5"></i>
        </div>
        <span>System Settings</span>
      </a>
      <a href="/admin/theme" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'theme' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'theme' ? 'bg-white/20 border border-white/30 text-white' : 'bg-fuchsia-500/15 border border-fuchsia-500/30 text-fuchsia-400' ?>">
          <i data-lucide="palette" class="w-3.5 h-3.5"></i>
        </div>
        <span>Website Theme</span>
      </a>
    </nav>
  </div>

  <div class="p-5 border-t border-slate-800 flex items-center justify-between text-xs">
    <div>
      <div class="font-bold text-white"><?= e($adminUser['full_name'] ?: 'Administrator') ?></div>
      <div class="text-[11px] text-slate-400">admin@rosesmm.com</div>
    </div>
    <a href="/logout" class="text-rose-400 hover:text-rose-300" title="Logout">
      <i data-lucide="log-out" class="w-4 h-4"></i>
    </a>
  </div>
</aside>

<div class="flex flex-1 min-h-screen">
  <!-- Desktop Admin Sidebar (Separate Admin Layout) -->
  <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between shrink-0 hidden lg:flex sticky top-0 h-screen overflow-y-auto">
    <div class="p-5">
      <!-- Admin Logo -->
      <a href="/admin" class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-2xl bg-rose-500 text-white flex items-center justify-center font-black">
          R
        </div>
        <div>
          <span class="text-lg font-bold text-white block leading-tight">RoseSMM</span>
          <span class="text-[10px] uppercase font-bold tracking-wider text-rose-400 block">Administration</span>
        </div>
      </a>

      <!-- Admin Navigation -->
      <nav class="space-y-1 text-xs font-semibold">
        <a href="/admin" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'dashboard' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'dashboard' ? 'bg-white/20 border border-white/30 text-white' : 'bg-indigo-500/15 border border-indigo-500/30 text-indigo-400' ?>">
            <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
          </div>
          <span>Dashboard</span>
        </a>
        <a href="/admin/orders" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'orders' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'orders' ? 'bg-white/20 border border-white/30 text-white' : 'bg-sky-500/15 border border-sky-500/30 text-sky-400' ?>">
            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
          </div>
          <span>Orders</span>
        </a>
        <a href="/admin/drip-feed" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'drip-feed' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'drip-feed' ? 'bg-white/20 border border-white/30 text-white' : 'bg-blue-500/15 border border-blue-500/30 text-blue-400' ?>">
            <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
          </div>
          <span>Drip-Feed Orders</span>
        </a>
        <a href="/admin/refill" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'refill' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'refill' ? 'bg-white/20 border border-white/30 text-white' : 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400' ?>">
            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
          </div>
          <span>Auto Refill</span>
        </a>
        <a href="/admin/refunds" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'refunds' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'refunds' ? 'bg-white/20 border border-white/30 text-white' : 'bg-amber-500/15 border border-amber-500/30 text-amber-400' ?>">
            <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
          </div>
          <span>Auto Refund</span>
        </a>
        <a href="/admin/coupons" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'coupons' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'coupons' ? 'bg-white/20 border border-white/30 text-white' : 'bg-purple-500/15 border border-purple-500/30 text-purple-400' ?>">
            <i data-lucide="tag" class="w-3.5 h-3.5"></i>
          </div>
          <span>Coupons & Discounts</span>
        </a>
        <a href="/admin/flash-sales" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'flash-sales' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'flash-sales' ? 'bg-white/20 border border-white/30 text-white' : 'bg-rose-500/15 border border-rose-500/30 text-rose-400' ?>">
            <i data-lucide="zap" class="w-3.5 h-3.5 fill-rose-400/20"></i>
          </div>
          <span>Flash Sales</span>
        </a>
        <a href="/admin/ticket-automation" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'ticket-automation' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'ticket-automation' ? 'bg-white/20 border border-white/30 text-white' : 'bg-cyan-500/15 border border-cyan-500/30 text-cyan-400' ?>">
            <i data-lucide="bot" class="w-3.5 h-3.5"></i>
          </div>
          <span>Ticket Automation</span>
        </a>
        <a href="/admin/cron-jobs" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'cron-jobs' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'cron-jobs' ? 'bg-white/20 border border-white/30 text-white' : 'bg-green-500/15 border border-green-500/30 text-green-400' ?>">
            <i data-lucide="clock-4" class="w-3.5 h-3.5"></i>
          </div>
          <span>Cron Automation</span>
        </a>
        <a href="/admin/users" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'users' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'users' ? 'bg-white/20 border border-white/30 text-white' : 'bg-indigo-500/15 border border-indigo-500/30 text-indigo-400' ?>">
            <i data-lucide="users" class="w-3.5 h-3.5"></i>
          </div>
          <span>Users</span>
        </a>
        <a href="/admin/services" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'services' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'services' ? 'bg-white/20 border border-white/30 text-white' : 'bg-violet-500/15 border border-violet-500/30 text-violet-400' ?>">
            <i data-lucide="layers" class="w-3.5 h-3.5"></i>
          </div>
          <span>Services</span>
        </a>
        <a href="/admin/categories" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'categories' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'categories' ? 'bg-white/20 border border-white/30 text-white' : 'bg-amber-500/15 border border-amber-500/30 text-amber-400' ?>">
            <i data-lucide="folder" class="w-3.5 h-3.5"></i>
          </div>
          <span>Categories</span>
        </a>
        <a href="/admin/providers" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'providers' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'providers' ? 'bg-white/20 border border-white/30 text-white' : 'bg-cyan-500/15 border border-cyan-500/30 text-cyan-400' ?>">
            <i data-lucide="server" class="w-3.5 h-3.5"></i>
          </div>
          <span>Providers</span>
        </a>
        <a href="/admin/provider-services" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'provider-services' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'provider-services' ? 'bg-white/20 border border-white/30 text-white' : 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400' ?>">
            <i data-lucide="download" class="w-3.5 h-3.5"></i>
          </div>
          <span>Import Services</span>
        </a>
        <a href="/admin/transactions" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'transactions' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'transactions' ? 'bg-white/20 border border-white/30 text-white' : 'bg-teal-500/15 border border-teal-500/30 text-teal-400' ?>">
            <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
          </div>
          <span>Transactions</span>
        </a>
        <a href="/admin/referrals" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= in_array($adminPage, ['referrals', 'refer-earn']) ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= in_array($adminPage, ['referrals', 'refer-earn']) ? 'bg-white/20 border border-white/30 text-white' : 'bg-pink-500/15 border border-pink-500/30 text-pink-400' ?>">
            <i data-lucide="gift" class="w-3.5 h-3.5"></i>
          </div>
          <span>Refer & Earn</span>
        </a>
        <a href="/admin/payment-gateways" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'payment-gateways' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'payment-gateways' ? 'bg-white/20 border border-white/30 text-white' : 'bg-blue-500/15 border border-blue-500/30 text-blue-400' ?>">
            <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
          </div>
          <span>Payment Gateways</span>
        </a>
        <a href="/admin/currencies" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'currencies' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'currencies' ? 'bg-white/20 border border-white/30 text-white' : 'bg-amber-500/15 border border-amber-500/30 text-amber-400' ?>">
            <i data-lucide="coins" class="w-3.5 h-3.5"></i>
          </div>
          <span>Currencies (INR/USD)</span>
        </a>
        <a href="/admin/sliders" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'sliders' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'sliders' ? 'bg-white/20 border border-white/30 text-white' : 'bg-purple-500/15 border border-purple-500/30 text-purple-400' ?>">
            <i data-lucide="image" class="w-3.5 h-3.5"></i>
          </div>
          <span>Sliders & Banners</span>
        </a>
        <a href="/admin/tickets" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'tickets' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'tickets' ? 'bg-white/20 border border-white/30 text-white' : 'bg-orange-500/15 border border-orange-500/30 text-orange-400' ?>">
            <i data-lucide="headset" class="w-3.5 h-3.5"></i>
          </div>
          <span>Support Tickets</span>
        </a>
        <a href="/admin/notifications" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'notifications' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'notifications' ? 'bg-white/20 border border-white/30 text-white' : 'bg-blue-500/15 border border-blue-500/30 text-blue-400' ?>">
            <i data-lucide="bell" class="w-3.5 h-3.5"></i>
          </div>
          <span>Broadcast Notifications</span>
        </a>
        <a href="/admin/settings" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'settings' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'settings' ? 'bg-white/20 border border-white/30 text-white' : 'bg-slate-700/50 border border-slate-600/50 text-slate-300' ?>">
            <i data-lucide="settings" class="w-3.5 h-3.5"></i>
          </div>
          <span>System Settings</span>
        </a>
        <a href="/admin/theme" class="group flex items-center gap-3 px-3 py-2 rounded-xl transition-colors <?= $adminPage === 'theme' ? 'bg-rose-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $adminPage === 'theme' ? 'bg-white/20 border border-white/30 text-white' : 'bg-fuchsia-500/15 border border-fuchsia-500/30 text-fuchsia-400' ?>">
            <i data-lucide="palette" class="w-3.5 h-3.5"></i>
          </div>
          <span>Website Theme</span>
        </a>
      </nav>
    </div>

    <!-- Admin User info in sidebar -->
    <div class="p-4 border-t border-slate-800 flex items-center justify-between text-xs">
      <div>
        <div class="font-bold text-white"><?= e($adminUser['full_name'] ?: 'Administrator') ?></div>
        <div class="text-[11px] text-slate-400">admin@rosesmm.com</div>
      </div>
      <a href="/logout" class="w-8 h-8 rounded-lg bg-rose-500/15 border border-rose-500/30 text-rose-400 hover:text-rose-300 hover:bg-rose-500/25 transition-all flex items-center justify-center shrink-0" title="Logout">
        <i data-lucide="log-out" class="w-4 h-4"></i>
      </a>
    </div>
  </aside>

  <!-- Admin Content Container -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Top Admin Header -->
    <header class="bg-white border-b border-slate-200 px-3 sm:px-4 lg:px-8 py-2.5 sm:py-3.5 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <!-- Mobile Admin Menu Toggle Button -->
        <button type="button" onclick="toggleAdminSidebar()" class="p-1.5 sm:p-2 -ml-1 text-slate-600 hover:text-rose-600 hover:bg-slate-100 rounded-lg lg:hidden transition-colors shrink-0" aria-label="Toggle Admin Navigation Menu">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <a href="/admin" class="flex items-center gap-2 lg:hidden min-w-0">
          <span class="w-6 h-6 rounded-lg bg-rose-500 text-white flex items-center justify-center font-black text-xs shrink-0">R</span>
          <span class="font-bold text-slate-800 text-sm truncate">Admin</span>
        </a>
        <span class="hidden lg:inline text-xs font-semibold text-slate-500">Administration Console</span>
      </div>

      <div class="flex items-center gap-2 sm:gap-4 text-xs font-semibold shrink-0">
        <a href="/dashboard" class="text-slate-600 hover:text-rose-600 flex items-center gap-1.5 px-2 py-1 rounded-lg hover:bg-slate-50 transition-colors whitespace-nowrap" target="_blank">
          <i data-lucide="external-link" class="w-3.5 h-3.5 text-slate-400"></i>
          <span class="hidden sm:inline">View User Portal</span>
          <span class="sm:hidden">Portal</span>
        </a>
        <a href="/logout" class="px-2.5 sm:px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 transition-colors whitespace-nowrap">
          Logout
        </a>
      </div>
    </header>

    <script>
      function toggleAdminSidebar() {
        const drawer = document.getElementById('admin-mobile-drawer');
        const backdrop = document.getElementById('admin-mobile-backdrop');
        if (!drawer || !backdrop) return;
        const isOpen = !drawer.classList.contains('-translate-x-full');
        if (isOpen) {
          drawer.classList.add('-translate-x-full');
          backdrop.classList.add('hidden');
          document.body.style.overflow = '';
        } else {
          drawer.classList.remove('-translate-x-full');
          backdrop.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        }
      }
    </script>

    <main class="flex-1 p-4 lg:p-8">
