<?php
// Ensure database and session are loaded
require_once __DIR__ . '/../../config/database.php';

// Auth check
if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$user = current_user();
$activePage = $activePage ?? 'dashboard';
$userCurrency = get_user_currency();
$currencies = get_currencies();

// Get unread notification count
$notifStmt = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
$notifStmt->execute([$user['id']]);
$unreadCount = (int)$notifStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(str_replace('RoseSMM', get_site_name(), $pageTitle ?? (get_site_name() . ' - Social Media Services'))) ?></title>
  <meta name="description" content="<?= e(get_site_name()) ?> - Premium Social Media Marketing Services Panel. Fast, Secure, Reliable.">
  <!-- Tailwind CSS CDN -->
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
              300: '#FAA7B8',
              400: '#F76D8C',
              500: '#FF3B69',
              600: '#E11D48',
              700: '#BE123C',
              800: '#9F1239',
              900: '#881337',
            },
            canvas: '#FFF9FA',
            borderPink: '#FCE4E8'
          },
          fontFamily: {
            sans: ['system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
          }
        }
      }
    }
  </script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    body {
      background-color: #FFF9FA;
      color: #1E293B;
    }
    .custom-scrollbar::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #FCD3DC;
      border-radius: 9999px;
    }
    /* Base theme styles for Top Navbar & Services Catalog */
    .navbar-action-btn {
      background: linear-gradient(135deg, #FF3B69, #E11D48);
      color: #FFFFFF;
    }
    .navbar-action-btn:hover {
      background: #E11D48;
    }
    .navbar-notif-badge {
      background: #FF3B69;
      color: #FFFFFF;
    }
    .service-header-action-btn {
      background: linear-gradient(135deg, #FF3B69, #E11D48);
      color: #FFFFFF;
    }
    .service-category-pill {
      background: #FFF0F3;
      color: #475569;
    }
    .service-category-pill:hover {
      background: #FFE2E8;
      color: #E11D48;
    }
    .service-category-pill.active {
      background: #FF3B69;
      color: #FFFFFF;
    }
    .service-item-icon {
      background: #FFF0F3;
      color: #FF3B69;
    }
    .service-item-cat {
      background: #FFF0F3;
      color: #E11D48;
    }
    .service-item-badge {
      background: #FF3B69;
      color: #FFFFFF;
    }
    .service-item-rate {
      color: #E11D48;
    }
    .service-item-btn {
      background: #FF3B69;
      color: #FFFFFF;
    }
    .service-item-btn:hover {
      background: #E11D48;
    }
    .service-empty-btn {
      background: #FF3B69;
      color: #FFFFFF;
    }
  </style>
  <?php render_theme_head_tags(); ?>
</head>
<body class="min-h-screen bg-[#FFF9FA] text-slate-800 antialiased flex flex-col <?= get_theme_body_class() ?>">

<div class="flex flex-1 min-h-screen">
  <!-- Sidebar -->
  <aside id="main-sidebar" class="w-64 bg-white border-r border-[#FCE4E8] flex flex-col justify-between hidden lg:flex shrink-0">
    <div class="p-5">
      <!-- Logo -->
      <a href="/dashboard" class="flex items-center gap-3 mb-6 group">
        <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 font-black text-lg group-hover:scale-105 transition-transform">
          <?= strtoupper(substr(get_site_name(), 0, 1)) ?>
        </div>
        <div>
          <span class="text-xl font-bold tracking-tight text-rose-600 block leading-tight"><?= e(get_site_name()) ?></span>
          <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block"><?= e(get_setting('site_tagline', 'Social Media Services')) ?></span>
        </div>
      </a>

      <!-- User Profile Card in Sidebar -->
      <a href="/profile" class="flex items-center justify-between p-3 rounded-2xl border border-[#FCE4E8] bg-rose-50/30 mb-6 hover:bg-rose-50 transition-colors">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-rose-400 to-rose-600 flex items-center justify-center text-white font-bold text-sm shadow-sm overflow-hidden">
            <?php if (!empty($user['avatar'])): ?>
              <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['full_name']) ?>" class="w-full h-full object-cover">
            <?php else: ?>
              <?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) ?>
            <?php endif; ?>
          </div>
          <div class="overflow-hidden">
            <div class="font-semibold text-sm text-slate-800 truncate"><?= e($user['full_name'] ?: $user['username']) ?></div>
            <div class="text-xs text-slate-500 truncate">@<?= e($user['username']) ?></div>
            <?php if (!empty($user['is_verified'])): ?>
              <div class="inline-flex items-center gap-1 text-[10px] font-medium text-rose-600 bg-rose-100/60 px-1.5 py-0.5 rounded-full mt-0.5">
                <i data-lucide="check" class="w-2.5 h-2.5"></i> Verified User
              </div>
            <?php endif; ?>
          </div>
        </div>
        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 shrink-0"></i>
      </a>

      <!-- Navigation Links -->
      <nav class="space-y-1">
        <a href="/dashboard" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'dashboard' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'dashboard' ? 'bg-indigo-100 text-indigo-600 border border-indigo-200' : 'bg-indigo-50 border border-indigo-100/80 text-indigo-500' ?>">
            <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
          </div>
          <span>Dashboard</span>
        </a>
        <a href="/order" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'order' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'order' ? 'bg-rose-100 text-rose-600 border border-rose-200' : 'bg-rose-50 border border-rose-100/80 text-rose-500' ?>">
            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
          </div>
          <span>New Order</span>
        </a>
        <a href="/orders" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'orders' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'orders' ? 'bg-sky-100 text-sky-600 border border-sky-200' : 'bg-sky-50 border border-sky-100/80 text-sky-500' ?>">
            <i data-lucide="clipboard-list" class="w-3.5 h-3.5"></i>
          </div>
          <span>Order History</span>
        </a>
        <a href="/mass-order" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'mass-order' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'mass-order' ? 'bg-purple-100 text-purple-600 border border-purple-200' : 'bg-purple-50 border border-purple-100/80 text-purple-500' ?>">
            <i data-lucide="layers" class="w-3.5 h-3.5"></i>
          </div>
          <span>Mass Order</span>
        </a>
        <a href="/drip-feed" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'drip-feed' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'drip-feed' ? 'bg-cyan-100 text-cyan-600 border border-cyan-200' : 'bg-cyan-50 border border-cyan-100/80 text-cyan-500' ?>">
            <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
          </div>
          <span>Drip-Feed</span>
        </a>
        <a href="/refills" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'refills' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'refills' ? 'bg-teal-100 text-teal-600 border border-teal-200' : 'bg-teal-50 border border-teal-100/80 text-teal-500' ?>">
            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
          </div>
          <span>Auto Refill</span>
        </a>
        <a href="/flash-sales" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'flash-sales' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'flash-sales' ? 'bg-amber-100 text-amber-600 border border-amber-200' : 'bg-amber-50 border border-amber-100/80 text-amber-500' ?>">
            <i data-lucide="zap" class="w-3.5 h-3.5 fill-amber-400/20"></i>
          </div>
          <span>Flash Deals</span>
        </a>
        <a href="/services" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'services' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'services' ? 'bg-violet-100 text-violet-600 border border-violet-200' : 'bg-violet-50 border border-violet-100/80 text-violet-500' ?>">
            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
          </div>
          <span>Services</span>
        </a>
        <a href="/wallet" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'wallet' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'wallet' ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : 'bg-emerald-50 border border-emerald-100/80 text-emerald-500' ?>">
            <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
          </div>
          <span>Wallet</span>
        </a>
        <a href="/referrals" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= in_array($activePage, ['referrals', 'refer-earn']) ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= in_array($activePage, ['referrals', 'refer-earn']) ? 'bg-pink-100 text-pink-600 border border-pink-200' : 'bg-pink-50 border border-pink-100/80 text-pink-500' ?>">
            <i data-lucide="gift" class="w-3.5 h-3.5"></i>
          </div>
          <span>Refer & Earn</span>
        </a>
        <a href="/add-funds" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'add-funds' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'add-funds' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-emerald-50 border border-emerald-100/80 text-emerald-600' ?>">
            <i data-lucide="circle-dollar-sign" class="w-3.5 h-3.5"></i>
          </div>
          <span>Add Funds</span>
        </a>
        <a href="/support" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'support' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'support' ? 'bg-orange-100 text-orange-600 border border-orange-200' : 'bg-orange-50 border border-orange-100/80 text-orange-500' ?>">
            <i data-lucide="headset" class="w-3.5 h-3.5"></i>
          </div>
          <span>Support Tickets</span>
        </a>
        <a href="/notifications" class="group flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'notifications' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'notifications' ? 'bg-blue-100 text-blue-600 border border-blue-200' : 'bg-blue-50 border border-blue-100/80 text-blue-500' ?>">
              <i data-lucide="bell" class="w-3.5 h-3.5"></i>
            </div>
            <span class="truncate">Notifications</span>
          </div>
          <?php if ($unreadCount > 0): ?>
            <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-500 text-white shrink-0"><?= $unreadCount ?></span>
          <?php endif; ?>
        </a>
        <a href="/profile" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'profile' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'profile' ? 'bg-slate-200 text-slate-800 border border-slate-300' : 'bg-slate-100 border border-slate-200/80 text-slate-600' ?>">
            <i data-lucide="user" class="w-3.5 h-3.5"></i>
          </div>
          <span>Profile</span>
        </a>
        <a href="/logout" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-rose-500 hover:bg-rose-50 transition-colors">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 bg-rose-50 border border-rose-100/80 text-rose-500">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
          </div>
          <span>Logout</span>
        </a>
      </nav>
    </div>

    <!-- Sidebar Bottom Upgrade Card & Theme -->
    <div class="p-5 border-t border-[#FCE4E8] space-y-4">
      <div class="p-4 rounded-2xl bg-gradient-to-b from-[#FFF5F7] to-[#FFE6EC] border border-[#FCD3DC] text-center">
        <div class="w-9 h-9 rounded-xl bg-rose-500 text-white flex items-center justify-center mx-auto mb-2.5 shadow-sm">
          <i data-lucide="crown" class="w-5 h-5"></i>
        </div>
        <h4 class="font-bold text-sm text-slate-800 mb-1">Upgrade Your Account</h4>
        <p class="text-xs text-slate-500 mb-3 leading-relaxed">Get better rates, more services and exclusive features.</p>
        <a href="/add-funds" class="block w-full py-2 px-3 text-xs font-semibold rounded-xl bg-rose-500 hover:bg-rose-600 text-white shadow-sm transition-colors">
          Upgrade Now
        </a>
      </div>

      <div class="flex items-center justify-between text-xs text-slate-600 px-1">
        <span>Theme: Light</span>
        <button type="button" class="w-9 h-5 bg-rose-500 rounded-full p-0.5 flex items-center justify-end transition-colors" title="Theme switcher">
          <span class="w-4 h-4 rounded-full bg-white shadow-sm block"></span>
        </button>
      </div>

      <div class="text-[11px] text-slate-400 text-center">
        &copy; <?= date('Y') ?> <?= e(get_site_name()) ?>. All rights reserved.
      </div>
    </div>
  </aside>

  <!-- Mobile Sidebar Overlay & Drawer -->
  <div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden" onclick="toggleMobileSidebar()"></div>
  <aside id="mobile-sidebar" class="fixed inset-y-0 left-0 w-72 bg-white z-50 p-5 flex flex-col justify-between overflow-y-auto transform -translate-x-full transition-transform duration-200 lg:hidden">
    <div>
      <div class="flex items-center justify-between mb-6">
        <a href="/dashboard" class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center font-black text-sm">
            <?= strtoupper(substr(get_site_name(), 0, 1)) ?>
          </div>
          <span class="text-lg font-bold text-rose-600"><?= e(get_site_name()) ?></span>
        </a>
        <button onclick="toggleMobileSidebar()" class="p-2 text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <nav class="space-y-1">
        <a href="/dashboard" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'dashboard' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'dashboard' ? 'bg-indigo-100 text-indigo-600 border border-indigo-200' : 'bg-indigo-50 border border-indigo-100/80 text-indigo-500' ?>">
            <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
          </div>
          <span>Dashboard</span>
        </a>
        <a href="/order" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'order' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'order' ? 'bg-rose-100 text-rose-600 border border-rose-200' : 'bg-rose-50 border border-rose-100/80 text-rose-500' ?>">
            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
          </div>
          <span>New Order</span>
        </a>
        <a href="/orders" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'orders' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'orders' ? 'bg-sky-100 text-sky-600 border border-sky-200' : 'bg-sky-50 border border-sky-100/80 text-sky-500' ?>">
            <i data-lucide="clipboard-list" class="w-3.5 h-3.5"></i>
          </div>
          <span>Order History</span>
        </a>
        <a href="/mass-order" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'mass-order' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'mass-order' ? 'bg-purple-100 text-purple-600 border border-purple-200' : 'bg-purple-50 border border-purple-100/80 text-purple-500' ?>">
            <i data-lucide="layers" class="w-3.5 h-3.5"></i>
          </div>
          <span>Mass Order</span>
        </a>
        <a href="/drip-feed" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'drip-feed' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'drip-feed' ? 'bg-cyan-100 text-cyan-600 border border-cyan-200' : 'bg-cyan-50 border border-cyan-100/80 text-cyan-500' ?>">
            <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
          </div>
          <span>Drip-Feed</span>
        </a>
        <a href="/refills" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'refills' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'refills' ? 'bg-teal-100 text-teal-600 border border-teal-200' : 'bg-teal-50 border border-teal-100/80 text-teal-500' ?>">
            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
          </div>
          <span>Auto Refill</span>
        </a>
        <a href="/flash-sales" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'flash-sales' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'flash-sales' ? 'bg-amber-100 text-amber-600 border border-amber-200' : 'bg-amber-50 border border-amber-100/80 text-amber-500' ?>">
            <i data-lucide="zap" class="w-3.5 h-3.5 fill-amber-400/20"></i>
          </div>
          <span>Flash Deals</span>
        </a>
        <a href="/services" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'services' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'services' ? 'bg-violet-100 text-violet-600 border border-violet-200' : 'bg-violet-50 border border-violet-100/80 text-violet-500' ?>">
            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
          </div>
          <span>Services</span>
        </a>
        <a href="/wallet" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'wallet' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'wallet' ? 'bg-emerald-100 text-emerald-600 border border-emerald-200' : 'bg-emerald-50 border border-emerald-100/80 text-emerald-500' ?>">
            <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
          </div>
          <span>Wallet</span>
        </a>
        <a href="/referrals" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= in_array($activePage, ['referrals', 'refer-earn']) ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= in_array($activePage, ['referrals', 'refer-earn']) ? 'bg-pink-100 text-pink-600 border border-pink-200' : 'bg-pink-50 border border-pink-100/80 text-pink-500' ?>">
            <i data-lucide="gift" class="w-3.5 h-3.5"></i>
          </div>
          <span>Refer & Earn</span>
        </a>
        <a href="/add-funds" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'add-funds' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'add-funds' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-emerald-50 border border-emerald-100/80 text-emerald-600' ?>">
            <i data-lucide="circle-dollar-sign" class="w-3.5 h-3.5"></i>
          </div>
          <span>Add Funds</span>
        </a>
        <a href="/support" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'support' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'support' ? 'bg-orange-100 text-orange-600 border border-orange-200' : 'bg-orange-50 border border-orange-100/80 text-orange-500' ?>">
            <i data-lucide="headset" class="w-3.5 h-3.5"></i>
          </div>
          <span>Support Tickets</span>
        </a>
        <a href="/notifications" class="group flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'notifications' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'notifications' ? 'bg-blue-100 text-blue-600 border border-blue-200' : 'bg-blue-50 border border-blue-100/80 text-blue-500' ?>">
              <i data-lucide="bell" class="w-3.5 h-3.5"></i>
            </div>
            <span class="truncate">Notifications</span>
          </div>
          <?php if ($unreadCount > 0): ?>
            <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-500 text-white shrink-0"><?= $unreadCount ?></span>
          <?php endif; ?>
        </a>
        <a href="/profile" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'profile' ? 'bg-[#FFE8EC] text-rose-600 font-bold' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 <?= $activePage === 'profile' ? 'bg-slate-200 text-slate-800 border border-slate-300' : 'bg-slate-100 border border-slate-200/80 text-slate-600' ?>">
            <i data-lucide="user" class="w-3.5 h-3.5"></i>
          </div>
          <span>Profile</span>
        </a>
        <a href="/logout" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-rose-500 hover:bg-rose-50 transition-colors">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 bg-rose-50 border border-rose-100/80 text-rose-500">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
          </div>
          <span>Logout</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- Main Content Wrapper -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Modern Premium SaaS Top Navbar -->
    <header class="user-top-navbar bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-3 sm:px-5 lg:px-8 py-2 sm:py-2.5 flex items-center justify-between gap-2.5 sm:gap-4 sticky top-0 z-30 transition-all">
      <div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0 max-w-lg">
        <button type="button" onclick="toggleMobileSidebar()" class="navbar-menu-toggle p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors lg:hidden shrink-0" aria-label="Open navigation menu">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <!-- Modern Compact Search Bar with Keyboard Hint -->
        <div class="relative w-full min-w-0">
          <i data-lucide="search" class="navbar-search-icon w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none transition-colors"></i>
          <input 
            type="text" 
            id="top-service-search"
            placeholder="Search services, platforms, IDs..." 
            class="navbar-search-input w-full pl-9 sm:pl-10 pr-12 py-1.5 sm:py-2 text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-full focus:outline-none focus:ring-2 focus:ring-slate-200 transition-all text-slate-700 placeholder-slate-400"
            onkeydown="if(event.key==='Enter') window.location.href='/services?search='+encodeURIComponent(this.value)"
          >
          <span class="navbar-search-kbd hidden sm:inline-flex items-center absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-semibold text-slate-400 bg-white border border-slate-200 rounded shadow-2xs pointer-events-none">
            ↵
          </span>
        </div>
      </div>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
        <!-- Currency Selector -->
        <div class="relative" id="currency-dropdown-wrapper">
          <button 
            type="button" 
            onclick="toggleCurrencyDropdown()"
            class="navbar-currency-btn flex items-center gap-1.5 px-2.5 py-1.5 rounded-full border border-slate-200 bg-white text-[11px] sm:text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs"
            title="Switch Currency"
          >
            <span class="text-xs"><?= $userCurrency === 'INR' ? '🇮🇳' : ($userCurrency === 'USD' ? '🇺🇸' : ($userCurrency === 'EUR' ? '🇪🇺' : '🇬🇧')) ?></span>
            <span class="font-bold"><?= e($userCurrency) ?></span>
            <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400 transition-transform"></i>
          </button>
          
          <div id="currency-dropdown" class="navbar-currency-dropdown hidden absolute right-0 mt-2 w-40 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-40">
            <div class="px-3 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Select Currency</div>
            <?php foreach ($currencies as $c): ?>
              <button 
                type="button" 
                onclick="changeCurrency('<?= e($c['code']) ?>')" 
                class="w-full text-left px-3 py-1.5 text-xs flex items-center justify-between hover:bg-slate-50 transition-colors <?= $userCurrency === $c['code'] ? 'text-slate-900 font-bold bg-slate-50' : 'text-slate-600' ?>"
              >
                <span class="flex items-center gap-1.5">
                  <span><?= $c['code'] === 'INR' ? '🇮🇳' : ($c['code'] === 'USD' ? '🇺🇸' : ($c['code'] === 'EUR' ? '🇪🇺' : '🇬🇧')) ?></span>
                  <span><?= e($c['name']) ?></span>
                </span>
                <span class="font-mono text-[11px] text-slate-400"><?= e($c['symbol']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Wallet Balance Chip -->
        <a href="/wallet" class="navbar-wallet-chip hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50 transition-all shadow-2xs group" title="View Wallet Balance">
          <div class="navbar-wallet-icon w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 transition-transform group-hover:scale-105">
            <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
          </div>
          <div class="text-left">
            <span class="navbar-wallet-text block text-xs font-bold text-slate-800 leading-tight"><?= format_price($user['balance']) ?></span>
            <span class="block text-[9px] text-slate-400 uppercase font-medium leading-none">Wallet</span>
          </div>
        </a>

        <!-- Add Funds Action Button -->
        <a href="/add-funds" class="navbar-action-btn inline-flex items-center gap-1 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full font-bold text-[11px] sm:text-xs shadow-sm hover:shadow transition-all whitespace-nowrap">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i>
          <span>Add Funds</span>
        </a>

        <!-- Notification Bell with Dynamic Badge (Hides completely if unreadCount == 0) -->
        <a href="/notifications" class="navbar-icon-btn relative p-2 rounded-full text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors" title="Notifications">
          <i data-lucide="bell" class="w-4 h-4 sm:w-4.5 sm:h-4.5"></i>
          <?php if ($unreadCount > 0): ?>
            <span id="header-unread-badge" class="navbar-notif-badge absolute top-1 right-1 min-w-[15px] h-[15px] px-1 text-white text-[9px] font-extrabold rounded-full flex items-center justify-center ring-2 ring-white">
              <?= $unreadCount ?>
            </span>
          <?php endif; ?>
        </a>

        <!-- User Profile Quick Chip -->
        <a href="/profile" class="navbar-user-chip flex items-center gap-2 p-1 sm:px-2.5 sm:py-1 rounded-full border border-slate-200 bg-white hover:bg-slate-50 transition-colors" title="My Profile (<?= e($user['username']) ?>)">
          <div class="w-6 h-6 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-[11px] uppercase shrink-0">
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
          </div>
          <span class="text-xs font-semibold text-slate-700 hidden md:inline truncate max-w-[85px]"><?= e($user['username']) ?></span>
        </a>
      </div>
    </header>

    <!-- Main Page Content Section -->
    <main class="flex-1 p-4 lg:p-8">
