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
  <title><?= e($pageTitle ?? 'RoseSMM - Social Media Services') ?></title>
  <meta name="description" content="RoseSMM - Premium Social Media Marketing Services Panel. Fast, Secure, Reliable.">
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
  </style>
</head>
<body class="min-h-screen bg-[#FFF9FA] text-slate-800 antialiased flex flex-col">

<div class="flex flex-1 min-h-screen">
  <!-- Sidebar -->
  <aside id="main-sidebar" class="w-64 bg-white border-r border-[#FCE4E8] flex flex-col justify-between hidden lg:flex shrink-0">
    <div class="p-5">
      <!-- Logo -->
      <a href="/dashboard" class="flex items-center gap-3 mb-6 group">
        <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 group-hover:scale-105 transition-transform">
          <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
          </svg>
        </div>
        <div>
          <span class="text-xl font-bold tracking-tight text-rose-600 block leading-tight">RoseSMM</span>
          <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block">Social Media Services</span>
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
        <a href="/dashboard" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'dashboard' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="layout-grid" class="w-4 h-4"></i>
          <span>Dashboard</span>
        </a>
        <a href="/order" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'order' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="shopping-cart" class="w-4 h-4"></i>
          <span>New Order</span>
        </a>
        <a href="/orders" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'orders' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="clock" class="w-4 h-4"></i>
          <span>Order History</span>
        </a>
        <a href="/services" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'services' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="layers" class="w-4 h-4"></i>
          <span>Services</span>
        </a>
        <a href="/wallet" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'wallet' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="wallet" class="w-4 h-4"></i>
          <span>Wallet</span>
        </a>
        <a href="/add-funds" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'add-funds' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="plus-circle" class="w-4 h-4"></i>
          <span>Add Funds</span>
        </a>
        <a href="/support" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'support' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="message-square" class="w-4 h-4"></i>
          <span>Support Tickets</span>
        </a>
        <a href="/notifications" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'notifications' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <div class="flex items-center gap-3">
            <i data-lucide="bell" class="w-4 h-4"></i>
            <span>Notifications</span>
          </div>
          <?php if ($unreadCount > 0): ?>
            <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-500 text-white"><?= $unreadCount ?></span>
          <?php endif; ?>
        </a>
        <a href="/profile" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $activePage === 'profile' ? 'bg-[#FFE8EC] text-rose-600' : 'text-slate-600 hover:text-rose-600 hover:bg-rose-50/50' ?>">
          <i data-lucide="user" class="w-4 h-4"></i>
          <span>Profile</span>
        </a>
        <a href="/logout" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium text-rose-500 hover:bg-rose-50 transition-colors">
          <i data-lucide="log-out" class="w-4 h-4"></i>
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
        &copy; 2025 RoseSMM. All rights reserved.
      </div>
    </div>
  </aside>

  <!-- Mobile Sidebar Overlay & Drawer -->
  <div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden" onclick="toggleMobileSidebar()"></div>
  <aside id="mobile-sidebar" class="fixed inset-y-0 left-0 w-72 bg-white z-50 p-5 flex flex-col justify-between overflow-y-auto transform -translate-x-full transition-transform duration-200 lg:hidden">
    <div>
      <div class="flex items-center justify-between mb-6">
        <a href="/dashboard" class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center">
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
          </div>
          <span class="text-lg font-bold text-rose-600">RoseSMM</span>
        </a>
        <button onclick="toggleMobileSidebar()" class="p-2 text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <nav class="space-y-1">
        <a href="/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'dashboard' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="layout-grid" class="w-4 h-4"></i> Dashboard
        </a>
        <a href="/order" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'order' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="shopping-cart" class="w-4 h-4"></i> New Order
        </a>
        <a href="/orders" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'orders' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="clock" class="w-4 h-4"></i> Order History
        </a>
        <a href="/services" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'services' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="layers" class="w-4 h-4"></i> Services
        </a>
        <a href="/wallet" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'wallet' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="wallet" class="w-4 h-4"></i> Wallet
        </a>
        <a href="/add-funds" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'add-funds' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Funds
        </a>
        <a href="/support" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'support' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="message-square" class="w-4 h-4"></i> Support Tickets
        </a>
        <a href="/notifications" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'notifications' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="bell" class="w-4 h-4"></i> Notifications
        </a>
        <a href="/profile" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium <?= $activePage === 'profile' ? 'bg-rose-50 text-rose-600' : 'text-slate-600' ?>">
          <i data-lucide="user" class="w-4 h-4"></i> Profile
        </a>
        <a href="/logout" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-rose-500">
          <i data-lucide="log-out" class="w-4 h-4"></i> Logout
        </a>
      </nav>
    </div>
  </aside>

  <!-- Main Content Wrapper -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Top Navbar matching screenshot, responsive on mobile without overflow -->
    <header class="bg-white border-b border-[#FCE4E8] px-3 sm:px-4 lg:px-8 py-2.5 sm:py-3.5 flex items-center justify-between gap-2 sm:gap-4">
      <div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0 max-w-xl">
        <button type="button" onclick="toggleMobileSidebar()" class="p-1.5 sm:p-2 -ml-1 text-slate-500 hover:text-slate-700 lg:hidden shrink-0" aria-label="Open navigation menu">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <!-- Search Bar -->
        <div class="relative w-full min-w-0">
          <i data-lucide="search" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
          <input 
            type="text" 
            id="top-service-search"
            placeholder="Search services..." 
            class="w-full pl-8 sm:pl-10 pr-3 sm:pr-9 py-1.5 sm:py-2 text-xs sm:text-sm bg-rose-50/20 border border-[#FCE4E8] rounded-full focus:outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-100 transition-all text-slate-700 placeholder-slate-400"
            onkeydown="if(event.key==='Enter') window.location.href='/services?search='+encodeURIComponent(this.value)"
          >
          <i data-lucide="search" class="w-3 h-3 text-slate-300 absolute right-3 top-1/2 -translate-y-1/2 hidden sm:block"></i>
        </div>
      </div>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
        <!-- Notification Bell -->
        <a href="/notifications" class="relative p-1.5 sm:p-2 rounded-full text-slate-600 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Notifications">
          <i data-lucide="bell" class="w-4 h-4 sm:w-5 sm:h-5"></i>
          <?php if ($unreadCount > 0): ?>
            <span class="absolute top-0.5 right-0.5 w-3.5 h-3.5 bg-rose-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-2 ring-white">
              <?= $unreadCount ?>
            </span>
          <?php endif; ?>
        </a>

        <!-- Dark/Light Theme Icon -->
        <button type="button" class="p-1.5 sm:p-2 rounded-full text-slate-600 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Toggle Dark/Light">
          <i data-lucide="moon" class="w-4 h-4 sm:w-5 sm:h-5"></i>
        </button>

        <!-- Currency Selector (Available only for authenticated user as required!) -->
        <div class="relative" id="currency-dropdown-wrapper">
          <button 
            type="button" 
            onclick="toggleCurrencyDropdown()"
            class="flex items-center gap-1 px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-full border border-[#FCE4E8] text-[11px] sm:text-xs font-semibold text-slate-700 hover:bg-rose-50/50 transition-colors"
          >
            <span><?= $userCurrency === 'INR' ? '🇮🇳' : ($userCurrency === 'USD' ? '🇺🇸' : ($userCurrency === 'EUR' ? '🇪🇺' : '🇬🇧')) ?></span>
            <span><?= e($userCurrency) ?></span>
            <i data-lucide="chevron-down" class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-slate-400"></i>
          </button>
          
          <div id="currency-dropdown" class="hidden absolute right-0 mt-2 w-36 bg-white border border-[#FCE4E8] rounded-xl shadow-lg py-1 z-30">
            <?php foreach ($currencies as $c): ?>
              <button 
                type="button" 
                onclick="changeCurrency('<?= e($c['code']) ?>')" 
                class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-rose-50 transition-colors <?= $userCurrency === $c['code'] ? 'text-rose-600 font-bold bg-rose-50/60' : 'text-slate-700' ?>"
              >
                <span><?= e($c['name']) ?> (<?= e($c['symbol']) ?>)</span>
                <span><?= e($c['code']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Wallet Balance Chip matching screenshot -->
        <a href="/wallet" class="hidden md:flex items-center gap-2.5 px-3 py-1.5 rounded-full border border-[#FCE4E8] bg-white hover:bg-rose-50/30 transition-colors">
          <div class="w-6 h-6 rounded-full bg-rose-100 flex items-center justify-center text-rose-500">
            <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
          </div>
          <div class="text-left">
            <span class="block text-xs font-bold text-slate-800"><?= format_price($user['balance']) ?></span>
            <span class="block text-[9px] text-slate-400 uppercase font-medium leading-none">Wallet Balance</span>
          </div>
        </a>

        <!-- Add Funds Action Button matching screenshot -->
        <a href="/add-funds" class="inline-flex items-center gap-1 px-2.5 sm:px-4 py-1.5 sm:py-2 rounded-full bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white text-[11px] sm:text-xs font-bold shadow-sm hover:shadow transition-all whitespace-nowrap">
          <i data-lucide="plus" class="w-3 h-3 sm:w-3.5 sm:h-3.5"></i>
          <span>Add Funds</span>
        </a>
      </div>
    </header>

    <!-- Main Page Content Section -->
    <main class="flex-1 p-4 lg:p-8">
