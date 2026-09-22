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
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

<div class="flex flex-1 min-h-screen">
  <!-- Admin Sidebar (Separate Admin Layout) -->
  <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between shrink-0 hidden lg:flex">
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
        <a href="/admin" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'dashboard' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
        </a>
        <a href="/admin/orders" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'orders' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="shopping-cart" class="w-4 h-4"></i> Orders
        </a>
        <a href="/admin/users" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'users' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="users" class="w-4 h-4"></i> Users
        </a>
        <a href="/admin/services" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'services' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="layers" class="w-4 h-4"></i> Services
        </a>
        <a href="/admin/categories" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'categories' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="folder" class="w-4 h-4"></i> Categories
        </a>
        <a href="/admin/providers" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'providers' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="server" class="w-4 h-4"></i> Providers
        </a>
        <a href="/admin/provider-services" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'provider-services' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="download" class="w-4 h-4"></i> Import Services
        </a>
        <a href="/admin/transactions" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'transactions' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="receipt" class="w-4 h-4"></i> Transactions
        </a>
        <a href="/admin/currencies" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'currencies' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="coins" class="w-4 h-4"></i> Currencies (INR/USD)
        </a>
        <a href="/admin/sliders" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'sliders' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="image" class="w-4 h-4"></i> Sliders & Banners
        </a>
        <a href="/admin/tickets" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'tickets' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="message-square" class="w-4 h-4"></i> Support Tickets
        </a>
        <a href="/admin/notifications" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'notifications' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="bell" class="w-4 h-4"></i> Broadcast Notifications
        </a>
        <a href="/admin/settings" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors <?= $adminPage === 'settings' ? 'bg-rose-600 text-white font-bold' : 'hover:bg-slate-800 hover:text-white' ?>">
          <i data-lucide="settings" class="w-4 h-4"></i> System Settings
        </a>
      </nav>
    </div>

    <!-- Admin User info in sidebar -->
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

  <!-- Admin Content Container -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Top Admin Header -->
    <header class="bg-white border-b border-slate-200 px-4 lg:px-8 py-3.5 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="/admin" class="lg:hidden font-bold text-rose-600">RoseSMM Admin</a>
        <span class="hidden lg:inline text-xs font-semibold text-slate-500">Administration Console</span>
      </div>

      <div class="flex items-center gap-4 text-xs font-semibold">
        <a href="/dashboard" class="text-slate-600 hover:text-rose-600 flex items-center gap-1.5" target="_blank">
          <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
          <span>View User Portal</span>
        </a>
        <a href="/logout" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 transition-colors">
          Logout
        </a>
      </div>
    </header>

    <main class="flex-1 p-4 lg:p-8">
