<?php
// Ensure database and session are loaded
require_once __DIR__ . '/../../../config/database.php';

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

$cssFile = '/templates/smm-pro/assets/css/smm-pro.css';
$cssVer = file_exists(__DIR__ . '/../assets/css/smm-pro.css') ? filemtime(__DIR__ . '/../assets/css/smm-pro.css') : time();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? (get_site_name() . ' - SMM Pro Dashboard')) ?></title>
  <meta name="description" content="SMM Pro - Elite Social Media Growth Dashboard. Fast, Automated, 100% Guaranteed.">
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            smm: {
              bg: '#08080E',
              sidebar: '#0B0B14',
              surface: '#131322',
              card: '#131322',
              hover: '#18182D',
              pink: '#FF2D78',
              'pink-dark': '#D91B5C',
              'pink-light': '#FF659F',
              text: '#FFFFFF',
              muted: '#9D9DB8',
              subtle: '#6C6C8A',
              border: 'rgba(255, 255, 255, 0.08)'
            }
          }
        }
      }
    }
  </script>
  
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <!-- Independent SMM Pro Stylesheet -->
  <link rel="stylesheet" href="<?= htmlspecialchars($cssFile . '?v=' . $cssVer, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="theme-smm-pro bg-[#08080E] text-white antialiased flex flex-col min-h-screen">

<div class="flex flex-1 min-h-screen">
  <!-- Desktop Left Sidebar -->
  <aside id="smm-pro-sidebar" class="w-64 bg-[#0B0B14] border-r border-white/5 flex flex-col justify-between hidden lg:flex shrink-0 z-30">
    <div class="p-5 flex flex-col h-full overflow-y-auto">
      <!-- Brand Logo -->
      <a href="/dashboard" class="flex items-center gap-3 mb-6 group">
        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-[#FF2D78] to-[#D91B5C] flex items-center justify-center text-white shadow-lg shadow-[#FF2D78]/30 font-black text-xl group-hover:scale-105 transition-transform">
          <i data-lucide="zap" class="w-5 h-5 fill-white text-white"></i>
        </div>
        <div>
          <div class="flex items-center gap-1.5">
            <span class="text-lg font-black tracking-tight text-white"><?= e(get_site_name()) ?></span>
            <span class="px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider bg-[#FF2D78]/20 border border-[#FF2D78]/40 text-[#FF2D78] rounded-md">PRO</span>
          </div>
          <span class="text-[10px] uppercase font-semibold tracking-wider text-[#9D9DB8] block">Social Media Services</span>
        </div>
      </a>

      <!-- User Mini Profile -->
      <a href="/profile" class="flex items-center justify-between p-3 rounded-2xl bg-[#131322] border border-white/5 hover:border-[#FF2D78]/30 transition-all mb-5 group">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-[#FF2D78] to-[#9333EA] flex items-center justify-center text-white font-black text-xs shrink-0 shadow-md">
            <?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) ?>
          </div>
          <div class="overflow-hidden">
            <div class="font-bold text-xs text-white truncate"><?= e($user['full_name'] ?: $user['username']) ?></div>
            <div class="text-[11px] text-[#9D9DB8] truncate">@<?= e($user['username']) ?></div>
          </div>
        </div>
        <span class="w-2 h-2 rounded-full bg-[#10B981] shadow-sm shadow-[#10B981]/50 shrink-0" title="Online"></span>
      </a>

      <!-- Navigation Links -->
      <nav class="space-y-1 flex-1">
        <a href="/dashboard" class="smm-nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
          <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
          <span>Dashboard</span>
        </a>
        <a href="/order" class="smm-nav-link <?= $activePage === 'order' ? 'active' : '' ?>">
          <i data-lucide="plus-circle" class="w-4 h-4 shrink-0"></i>
          <span>New Order</span>
        </a>
        <a href="/orders" class="smm-nav-link <?= $activePage === 'orders' ? 'active' : '' ?>">
          <i data-lucide="clipboard-list" class="w-4 h-4 shrink-0"></i>
          <span>Order History</span>
        </a>
        <a href="/services" class="smm-nav-link <?= $activePage === 'services' ? 'active' : '' ?>">
          <i data-lucide="sparkles" class="w-4 h-4 shrink-0"></i>
          <span>Services</span>
        </a>
        <a href="/mass-order" class="smm-nav-link <?= $activePage === 'mass-order' ? 'active' : '' ?>">
          <i data-lucide="layers" class="w-4 h-4 shrink-0"></i>
          <span>Mass Order</span>
        </a>
        <a href="/drip-feed" class="smm-nav-link <?= $activePage === 'drip-feed' ? 'active' : '' ?>">
          <i data-lucide="repeat" class="w-4 h-4 shrink-0"></i>
          <span>Drip Feed</span>
        </a>
        <a href="/refills" class="smm-nav-link <?= $activePage === 'refills' ? 'active' : '' ?>">
          <i data-lucide="refresh-cw" class="w-4 h-4 shrink-0"></i>
          <span>Refills</span>
        </a>
        <div class="pt-3 pb-1">
          <div class="text-[10px] font-bold uppercase tracking-wider text-[#6C6C8A] px-3">Billing & Support</div>
        </div>
        <a href="/add-funds" class="smm-nav-link <?= $activePage === 'add-funds' ? 'active' : '' ?>">
          <i data-lucide="credit-card" class="w-4 h-4 shrink-0"></i>
          <span>Add Funds</span>
        </a>
        <a href="/wallet" class="smm-nav-link <?= $activePage === 'wallet' ? 'active' : '' ?>">
          <i data-lucide="wallet" class="w-4 h-4 shrink-0"></i>
          <span>Wallet</span>
        </a>
        <a href="/support" class="smm-nav-link <?= $activePage === 'support' ? 'active' : '' ?>">
          <i data-lucide="help-circle" class="w-4 h-4 shrink-0"></i>
          <span>Support Tickets</span>
        </a>
        <a href="/referrals" class="smm-nav-link <?= $activePage === 'referrals' ? 'active' : '' ?>">
          <i data-lucide="share-2" class="w-4 h-4 shrink-0"></i>
          <span>Refer & Earn</span>
        </a>
        <a href="/profile" class="smm-nav-link <?= $activePage === 'profile' ? 'active' : '' ?>">
          <i data-lucide="settings" class="w-4 h-4 shrink-0"></i>
          <span>Settings & API</span>
        </a>
      </nav>

      <!-- Sidebar Promo Card -->
      <div class="smm-sidebar-bonus p-3.5 mt-5">
        <div class="flex items-center gap-2 mb-1.5">
          <div class="w-6 h-6 rounded-lg bg-[#FF2D78]/20 flex items-center justify-center text-[#FF2D78]">
            <i data-lucide="gift" class="w-3.5 h-3.5"></i>
          </div>
          <span class="text-xs font-bold text-white">Deposit Bonus</span>
        </div>
        <p class="text-[11px] text-[#9D9DB8] mb-3 leading-tight">Get +5% extra credits on all UPI & Crypto deposits today.</p>
        <a href="/add-funds" class="smm-btn-pink w-full py-1.5 text-xs text-center block">
          Add Funds Now
        </a>
      </div>

      <!-- Logout Link -->
      <div class="pt-4 border-t border-white/5 mt-4">
        <a href="/logout" class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-[#F87171] hover:bg-[#EF4444]/10 rounded-xl transition-colors">
          <i data-lucide="log-out" class="w-4 h-4"></i>
          <span>Log Out</span>
        </a>
      </div>
    </div>
  </aside>

  <!-- Mobile Sidebar Overlay & Drawer -->
  <div id="smm-mobile-drawer" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden transition-opacity">
    <div class="w-72 max-w-[80vw] h-full bg-[#0B0B14] border-r border-white/10 p-5 flex flex-col justify-between overflow-y-auto">
      <div>
        <div class="flex items-center justify-between mb-6">
          <a href="/dashboard" class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-[#FF2D78] to-[#D91B5C] flex items-center justify-center text-white font-black text-sm">
              <i data-lucide="zap" class="w-4 h-4 fill-white text-white"></i>
            </div>
            <span class="text-base font-black text-white"><?= e(get_site_name()) ?></span>
          </a>
          <button onclick="toggleSmmMobileMenu()" class="p-2 rounded-xl text-[#9D9DB8] hover:text-white bg-white/5">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
        
        <nav class="space-y-1">
          <a href="/dashboard" class="smm-nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> <span>Dashboard</span>
          </a>
          <a href="/order" class="smm-nav-link <?= $activePage === 'order' ? 'active' : '' ?>">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> <span>New Order</span>
          </a>
          <a href="/orders" class="smm-nav-link <?= $activePage === 'orders' ? 'active' : '' ?>">
            <i data-lucide="clipboard-list" class="w-4 h-4"></i> <span>Orders</span>
          </a>
          <a href="/services" class="smm-nav-link <?= $activePage === 'services' ? 'active' : '' ?>">
            <i data-lucide="sparkles" class="w-4 h-4"></i> <span>Services</span>
          </a>
          <a href="/add-funds" class="smm-nav-link <?= $activePage === 'add-funds' ? 'active' : '' ?>">
            <i data-lucide="credit-card" class="w-4 h-4"></i> <span>Add Funds</span>
          </a>
          <a href="/wallet" class="smm-nav-link <?= $activePage === 'wallet' ? 'active' : '' ?>">
            <i data-lucide="wallet" class="w-4 h-4"></i> <span>Wallet</span>
          </a>
          <a href="/support" class="smm-nav-link <?= $activePage === 'support' ? 'active' : '' ?>">
            <i data-lucide="help-circle" class="w-4 h-4"></i> <span>Support</span>
          </a>
          <a href="/profile" class="smm-nav-link <?= $activePage === 'profile' ? 'active' : '' ?>">
            <i data-lucide="settings" class="w-4 h-4"></i> <span>Profile</span>
          </a>
        </nav>
      </div>

      <a href="/logout" class="flex items-center gap-2 px-3 py-2 text-xs font-bold text-[#F87171] hover:bg-[#EF4444]/10 rounded-xl transition-colors mt-6">
        <i data-lucide="log-out" class="w-4 h-4"></i>
        <span>Log Out</span>
      </a>
    </div>
  </div>

  <!-- Main Content Column -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Top Luxury Navbar -->
    <header class="smm-navbar px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4 sticky top-0 z-20">
      <div class="flex items-center gap-3 flex-1 max-w-md">
        <!-- Mobile Drawer Toggle -->
        <button type="button" onclick="toggleSmmMobileMenu()" class="p-2 rounded-xl text-[#9D9DB8] hover:text-white hover:bg-white/5 lg:hidden">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <!-- Search Bar -->
        <div class="relative w-full">
          <i data-lucide="search" class="w-4 h-4 text-[#6C6C8A] absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
          <input 
            type="text" 
            placeholder="Search services, platforms, IDs..." 
            class="smm-search-input w-full pl-9 pr-10 py-2 text-xs sm:text-sm text-white placeholder-[#6C6C8A]"
            onkeydown="if(event.key==='Enter') window.location.href='/services?search='+encodeURIComponent(this.value)"
          >
          <span class="hidden sm:inline-flex items-center absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-bold text-[#6C6C8A] bg-white/5 border border-white/10 rounded">
            ↵
          </span>
        </div>
      </div>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        <!-- Currency Dropdown -->
        <div class="relative" id="smm-currency-wrapper">
          <button 
            type="button" 
            onclick="toggleSmmCurrencyDropdown()"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#131322] border border-white/10 text-xs font-bold text-white hover:border-[#FF2D78]/40 transition-colors"
          >
            <span><?= $userCurrency === 'INR' ? '🇮🇳' : ($userCurrency === 'USD' ? '🇺🇸' : ($userCurrency === 'EUR' ? '🇪🇺' : '🇬🇧')) ?></span>
            <span><?= e($userCurrency) ?></span>
            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-[#9D9DB8]"></i>
          </button>

          <div id="smm-currency-menu" class="hidden absolute right-0 mt-2 w-40 bg-[#131322] border border-white/10 rounded-2xl shadow-2xl py-1.5 z-40">
            <div class="px-3 py-1 text-[10px] font-bold text-[#6C6C8A] uppercase tracking-wider">Currency</div>
            <?php foreach ($currencies as $c): ?>
              <button 
                type="button" 
                onclick="switchSmmCurrency('<?= e($c['code']) ?>')" 
                class="w-full text-left px-3 py-1.5 text-xs flex items-center justify-between hover:bg-white/5 transition-colors <?= $userCurrency === $c['code'] ? 'text-[#FF2D78] font-bold bg-[#FF2D78]/10' : 'text-[#9D9DB8]' ?>"
              >
                <span><?= e($c['name']) ?></span>
                <span class="font-mono text-[11px] text-[#6C6C8A]"><?= e($c['symbol']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Wallet Balance Chip -->
        <div class="flex items-center gap-2 pl-3 pr-1.5 py-1 rounded-full bg-[#131322] border border-white/10 shadow-sm">
          <div class="text-left">
            <span class="text-[9px] uppercase font-bold text-[#9D9DB8] block leading-none">Balance</span>
            <span class="text-xs font-black text-white leading-tight block"><?= format_price($user['balance']) ?></span>
          </div>
          <a href="/add-funds" class="w-7 h-7 rounded-full bg-gradient-to-tr from-[#FF2D78] to-[#D91B5C] flex items-center justify-center text-white hover:scale-105 transition-transform shadow-md shadow-[#FF2D78]/30" title="Deposit Funds">
            <i data-lucide="plus" class="w-4 h-4"></i>
          </a>
        </div>

        <!-- Notifications Bell -->
        <a href="/notifications" class="relative p-2 rounded-full bg-[#131322] border border-white/10 text-[#9D9DB8] hover:text-white hover:border-[#FF2D78]/40 transition-colors">
          <i data-lucide="bell" class="w-4 h-4"></i>
          <?php if ($unreadCount > 0): ?>
            <span class="absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-[#FF2D78] text-white text-[9px] font-black flex items-center justify-center">
              <?= $unreadCount ?>
            </span>
          <?php endif; ?>
        </a>

        <!-- User Avatar -->
        <a href="/profile" class="flex items-center gap-2 p-1 pr-2.5 rounded-full bg-[#131322] border border-white/10 hover:border-[#FF2D78]/40 transition-colors">
          <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-[#FF2D78] to-[#9333EA] flex items-center justify-center text-white font-bold text-xs">
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
          </div>
          <span class="text-xs font-bold text-white hidden md:inline truncate max-w-[80px]"><?= e($user['username']) ?></span>
        </a>
      </div>
    </header>

    <!-- Main Body Canvas -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 relative">
      <!-- Ambient Pink Glow -->
      <div class="smm-hero-ambient"></div>
