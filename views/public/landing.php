<?php
require_once __DIR__ . '/../../config/database.php';

// If authenticated user visits /, redirect to /dashboard (Prompt Rule 14)
if (is_logged_in()) {
    header("Location: /dashboard");
    exit;
}

$services = [];
try {
    $db = getDB();
    if ($db) {
        $stmt = $db->query("SELECT s.*, c.name AS category_name, c.slug AS category_slug FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status = 'active' ORDER BY s.sort_order ASC LIMIT 6");
        if ($stmt) {
            $services = $stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    // If services table is empty or error occurs, fail gracefully
    $services = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RoseSMM - Premium Social Media Marketing Services</title>
  <meta name="description" content="RoseSMM is the #1 automated SMM panel for Instagram, YouTube, TikTok, Facebook, and Twitter. Instant delivery, high retention, 24/7 support.">
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
            }
          }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex flex-col justify-between">

  <!-- Public Navigation (NO Currency Selector here per Prompt Rule 15!) -->
  <header class="bg-white/80 backdrop-blur-md border-b border-[#FCE4E8] px-4 lg:px-12 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
      <a href="/" class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 shadow-sm">
          <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
          </svg>
        </div>
        <div>
          <span class="text-xl font-bold tracking-tight text-rose-600 block leading-tight">RoseSMM</span>
          <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block">Social Media Services</span>
        </div>
      </a>

      <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
        <a href="#services" class="hover:text-rose-600 transition-colors">Services</a>
        <a href="#features" class="hover:text-rose-600 transition-colors">Why RoseSMM</a>
        <a href="#tournaments" class="hover:text-rose-600 transition-colors">Tournaments</a>
        <a href="/login" class="hover:text-rose-600 transition-colors">API Docs</a>
      </nav>

      <div class="flex items-center gap-3">
        <a href="/login" class="px-5 py-2 rounded-full border border-[#FCE4E8] text-xs font-bold text-slate-700 hover:bg-rose-50 transition-colors">
          Sign In
        </a>
        <a href="/register" class="px-5 py-2 rounded-full bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white text-xs font-bold shadow-sm transition-all">
          Get Started
        </a>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <main>
    <section class="max-w-7xl mx-auto px-4 lg:px-12 py-12 lg:py-20">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-100 text-rose-600 text-xs font-bold mb-4">
            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
            <span>#1 Premier Social Media Marketing Panel</span>
          </div>

          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 tracking-tight leading-tight mb-4">
            Grow Your Online Presence <span class="text-rose-500 underline decoration-rose-200">Instantly</span>.
          </h1>

          <p class="text-base sm:text-lg text-slate-600 leading-relaxed mb-8 max-w-xl">
            Automated high-speed delivery for Instagram, YouTube, TikTok, Telegram and more. Real profiles, non-drop guarantee, and round-the-clock live support.
          </p>

          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 mb-10">
            <a href="/register" class="px-8 py-3.5 rounded-full bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all text-center">
              Start Boosting Today →
            </a>
            <a href="/login" class="px-8 py-3.5 rounded-full border border-[#FCE4E8] bg-white hover:bg-rose-50/50 text-slate-700 font-bold text-sm shadow-sm transition-all text-center">
              Explore Demo Account
            </a>
          </div>

          <!-- Fast Metrics -->
          <div class="grid grid-cols-3 gap-4 pt-6 border-t border-[#FCE4E8]">
            <div>
              <div class="text-2xl font-black text-slate-900">2.4M+</div>
              <div class="text-xs text-slate-400 font-medium">Orders Completed</div>
            </div>
            <div>
              <div class="text-2xl font-black text-rose-500">99.8%</div>
              <div class="text-xs text-slate-400 font-medium">Satisfaction Rate</div>
            </div>
            <div>
              <div class="text-2xl font-black text-slate-900">&lt; 5 min</div>
              <div class="text-xs text-slate-400 font-medium">Average Start Time</div>
            </div>
          </div>
        </div>

        <!-- Hero Card Mockup with 3D elements matching screenshot -->
        <div class="relative">
          <div class="rounded-3xl bg-gradient-to-br from-[#FFE8EC] via-[#FFDCE3] to-[#FFCFDA] border border-[#FCD3DC] p-8 shadow-xl relative overflow-hidden">
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-500 text-white flex items-center justify-center font-bold">
                  R
                </div>
                <div>
                  <div class="font-bold text-sm text-slate-800">RoseSMM Dashboard</div>
                  <div class="text-xs text-rose-600 font-semibold">Active & Live</div>
                </div>
              </div>
              <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 font-bold text-xs">
                ● High Speed
              </span>
            </div>

            <!-- Preview Card list -->
            <div class="space-y-3 mb-6">
              <div class="bg-white/90 backdrop-blur p-3.5 rounded-2xl border border-white flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                    <i data-lucide="instagram" class="w-4 h-4"></i>
                  </div>
                  <div>
                    <div class="font-bold text-xs text-slate-800">Instagram Followers</div>
                    <div class="text-[10px] text-slate-400">+5,000 delivered</div>
                  </div>
                </div>
                <span class="text-xs font-extrabold text-emerald-600">Completed</span>
              </div>

              <div class="bg-white/90 backdrop-blur p-3.5 rounded-2xl border border-white flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xl bg-red-50 text-red-500 flex items-center justify-center">
                    <i data-lucide="youtube" class="w-4 h-4"></i>
                  </div>
                  <div>
                    <div class="font-bold text-xs text-slate-800">YouTube High Retention Views</div>
                    <div class="text-[10px] text-slate-400">+25,000 delivered</div>
                  </div>
                </div>
                <span class="text-xs font-extrabold text-emerald-600">Completed</span>
              </div>

              <div class="bg-white/90 backdrop-blur p-3.5 rounded-2xl border border-white flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center">
                    <i data-lucide="music-2" class="w-4 h-4"></i>
                  </div>
                  <div>
                    <div class="font-bold text-xs text-slate-800">TikTok Likes & Shares</div>
                    <div class="text-[10px] text-slate-400">+10,000 delivered</div>
                  </div>
                </div>
                <span class="text-xs font-extrabold text-amber-600">In Progress</span>
              </div>
            </div>

            <div class="p-4 rounded-2xl bg-white border border-[#FCD3DC] flex items-center justify-between">
              <div>
                <span class="text-[11px] text-slate-400 font-bold block">WALLET BONUS</span>
                <span class="text-sm font-extrabold text-slate-800">10% Extra on Every Deposit</span>
              </div>
              <a href="/register" class="px-4 py-2 rounded-xl bg-rose-500 text-white text-xs font-bold hover:bg-rose-600">
                Claim Now
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Services Overview Section (Card layout, NO TABLES!) -->
    <section id="services" class="max-w-7xl mx-auto px-4 lg:px-12 py-16">
      <div class="text-center max-w-xl mx-auto mb-12">
        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Popular SMM Services</h2>
        <p class="text-sm text-slate-500">Unbeatable rates, genuine engagement, and non-drop guarantees across all platforms.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($services as $srv): ?>
          <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm hover:border-rose-300 transition-all flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold px-3 py-1 rounded-full bg-rose-50 text-rose-600">
                  <?= e($srv['category_name']) ?>
                </span>
                <span class="text-xs font-extrabold text-slate-700">
                  $<?= number_format($srv['rate'], 2) ?> / 1K
                </span>
              </div>
              <h3 class="font-bold text-base text-slate-800 mb-2"><?= e($srv['name']) ?></h3>
              <p class="text-xs text-slate-500 mb-4 line-clamp-2"><?= e($srv['description']) ?></p>
            </div>
            <a href="/register" class="block w-full py-2.5 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white font-bold text-xs text-center transition-colors">
              Get Started
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="bg-white border-y border-[#FCE4E8] py-16">
      <div class="max-w-7xl mx-auto px-4 lg:px-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="p-6 rounded-3xl bg-rose-50/40 border border-[#FCE4E8]">
            <div class="w-12 h-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-4">
              <i data-lucide="zap" class="w-6 h-6"></i>
            </div>
            <h3 class="font-bold text-base text-slate-800 mb-2">Automated Instant Start</h3>
            <p class="text-xs text-slate-600 leading-relaxed">Orders are automatically sent to our provider API clusters within seconds of placement.</p>
          </div>

          <div class="p-6 rounded-3xl bg-rose-50/40 border border-[#FCE4E8]">
            <div class="w-12 h-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-4">
              <i data-lucide="shield-check" class="w-6 h-6"></i>
            </div>
            <h3 class="font-bold text-base text-slate-800 mb-2">Safe & Compliant</h3>
            <p class="text-xs text-slate-600 leading-relaxed">No password required. We only need your public username or post link to deliver real boost.</p>
          </div>

          <div class="p-6 rounded-3xl bg-rose-50/40 border border-[#FCE4E8]">
            <div class="w-12 h-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-4">
              <i data-lucide="headphones" class="w-6 h-6"></i>
            </div>
            <h3 class="font-bold text-base text-slate-800 mb-2">24/7 Dedicated Support</h3>
            <p class="text-xs text-slate-600 leading-relaxed">Our support desk is staffed round-the-clock with ticketing and live assistance.</p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="bg-white border-t border-[#FCE4E8] py-8 px-4 lg:px-12">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-400">
      <div class="flex items-center gap-2">
        <span class="font-bold text-rose-600">RoseSMM</span>
        <span>• Social Media Services</span>
      </div>
      <div>
        &copy; 2025 RoseSMM. All rights reserved. Built with pure PHP, MySQL, and Tailwind CSS.
      </div>
      <div class="flex items-center gap-4 text-slate-500">
        <a href="/login" class="hover:text-rose-600">User Login</a>
        <a href="/admin/login" class="hover:text-rose-600">Admin Area</a>
        <a href="/install" class="hover:text-rose-600">Installer</a>
      </div>
    </div>
  </footer>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
