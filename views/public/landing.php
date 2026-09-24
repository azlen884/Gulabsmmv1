<?php
require_once __DIR__ . '/../../config/database.php';

// If authenticated user visits /, redirect to /dashboard (Prompt Rule 14)
if (is_logged_in()) {
    header("Location: /dashboard");
    exit;
}

$services = [];
$recentOrders = [];
try {
    $db = getDB();
    if ($db) {
        $stmt = $db->query("SELECT s.*, c.name AS category_name, c.slug AS category_slug FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status = 'active' ORDER BY s.sort_order ASC LIMIT 6");
        if ($stmt) {
            $services = $stmt->fetchAll();
        }

        // Fetch real existing orders from the database for the live activity showcase
        $orderStmt = $db->query("
            SELECT o.id, o.quantity, o.status, o.created_at, 
                   s.name AS service_name, 
                   c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
            FROM orders o
            JOIN services s ON o.service_id = s.id
            JOIN categories c ON s.category_id = c.id
            ORDER BY o.id DESC
            LIMIT 15
        ");
        if ($orderStmt) {
            $recentOrders = $orderStmt->fetchAll();
        }
    }
} catch (Exception $e) {
    // If services table is empty or error occurs, fail gracefully
    $services = [];
    $recentOrders = [];
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
  <style>
    /* Base theme styles for landing dynamic components */
    .landing-dashboard-card {
      background: linear-gradient(135deg, #FFE8EC 0%, #FFDCE3 50%, #FFCFDA 100%);
      border: 1px solid #FCD3DC;
    }
    .landing-dashboard-logo {
      background: #FF3B69;
      color: #FFFFFF;
    }
    .landing-dashboard-title {
      color: #1E293B;
    }
    .landing-dashboard-status {
      color: #E11D48;
    }
    .landing-dashboard-speed {
      background: #ECFDF5;
      color: #059669;
      border: 1px solid #A7F3D0;
    }
    .landing-activity-item {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.9);
    }
    .landing-activity-icon {
      background: #FFF0F3;
      color: #FF3B69;
    }
    .landing-activity-name {
      color: #1E293B;
    }
    .landing-activity-qty {
      color: #94A3B8;
    }
    .landing-promo-card {
      background: #FFFFFF;
      border: 1px solid #FCD3DC;
      color: #1E293B;
    }
    .landing-promo-btn {
      background: #FF3B69;
      color: #FFFFFF;
    }
    .landing-promo-btn:hover {
      background: #E11D48;
    }
    .landing-services-heading {
      color: #0F172A;
    }
    .landing-services-sub {
      color: #64748B;
    }
    .landing-service-card {
      background: #FFFFFF;
      border: 1px solid #FCE4E8;
    }
    .landing-service-card:hover {
      border-color: #F87171;
    }
    .landing-service-cat {
      background: #FFF0F3;
      color: #E11D48;
    }
    .landing-service-rate {
      color: #334155;
    }
    .landing-service-title {
      color: #1E293B;
    }
    .landing-service-desc {
      color: #64748B;
    }
    .landing-service-btn {
      background: #FFF0F3;
      color: #E11D48;
    }
    .landing-service-btn:hover {
      background: #FF3B69;
      color: #FFFFFF;
    }
  </style>
  <script src="https://unpkg.com/lucide@latest"></script>
  <?php render_theme_head_tags(); ?>
</head>
<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex flex-col justify-between <?= get_theme_body_class() ?>">

  <!-- Public Navigation (NO Currency Selector here per Prompt Rule 15!) -->
  <header class="bg-white/80 backdrop-blur-md border-b border-[#FCE4E8] px-3 sm:px-4 lg:px-12 py-3 sm:py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-2">
      <a href="/" class="flex items-center gap-2 sm:gap-3 shrink-0">
        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 shadow-sm shrink-0">
          <svg class="w-5 h-5 sm:w-6 sm:h-6 fill-current" viewBox="0 0 24 24">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
          </svg>
        </div>
        <div>
          <span class="text-lg sm:text-xl font-bold tracking-tight text-rose-600 block leading-tight">RoseSMM</span>
          <span class="text-[9px] sm:text-[10px] uppercase font-semibold tracking-wider text-slate-400 hidden sm:block">Social Media Services</span>
        </div>
      </a>

      <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
        <a href="#services" class="hover:text-rose-600 transition-colors">Services</a>
        <a href="#features" class="hover:text-rose-600 transition-colors">Why RoseSMM</a>
        <a href="/login" class="hover:text-rose-600 transition-colors">API Docs</a>
      </nav>

      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        <a href="/login" class="px-3.5 sm:px-5 py-1.5 sm:py-2 rounded-full border border-[#FCE4E8] text-xs font-bold text-slate-700 hover:bg-rose-50 transition-colors whitespace-nowrap">
          Sign In
        </a>
        <a href="/register" class="px-3.5 sm:px-5 py-1.5 sm:py-2 rounded-full bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white text-xs font-bold shadow-sm transition-all whitespace-nowrap">
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
          <div class="landing-dashboard-card rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center gap-3">
                <div class="landing-dashboard-logo w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-white shadow-sm shrink-0">
                  R
                </div>
                <div>
                  <div class="landing-dashboard-title font-bold text-sm text-slate-800">RoseSMM Dashboard</div>
                  <div class="landing-dashboard-status text-xs font-semibold flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Active & Live Orders</span>
                  </div>
                </div>
              </div>
              <span class="landing-dashboard-speed px-3 py-1 rounded-full font-bold text-xs flex items-center gap-1">
                <i data-lucide="zap" class="w-3 h-3"></i> High Speed
              </span>
            </div>

            <!-- Rotating Live Order Activity Stream (Uses 100% Real Database Orders) -->
            <div id="hero-orders-list" class="space-y-3 mb-6 relative min-h-[190px]">
              <?php 
              // Display first 3 real orders initially
              $initialOrders = !empty($recentOrders) ? array_slice($recentOrders, 0, 3) : [];
              foreach ($initialOrders as $ro): 
                $iconName = 'sparkles';
                $catSlug = strtolower($ro['category_slug'] ?? '');
                if (strpos($catSlug, 'youtube') !== false) $iconName = 'youtube';
                elseif (strpos($catSlug, 'tiktok') !== false) $iconName = 'music-2';
                elseif (strpos($catSlug, 'telegram') !== false) $iconName = 'send';
                elseif (strpos($catSlug, 'twitter') !== false || strpos($catSlug, 'x') !== false) $iconName = 'twitter';
                elseif (strpos($catSlug, 'facebook') !== false) $iconName = 'thumbs-up';
                elseif (strpos($catSlug, 'linkedin') !== false) $iconName = 'linkedin';
                elseif (strpos($catSlug, 'instagram') !== false) $iconName = 'instagram';

                $statusLabel = 'Completed';
                $statusColorClass = 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20';
                if ($ro['status'] === 'in_progress') {
                    $statusLabel = 'In Progress';
                    $statusColorClass = 'text-amber-600 bg-amber-500/10 border-amber-500/20';
                } elseif ($ro['status'] === 'pending' || $ro['status'] === 'processing') {
                    $statusLabel = 'Processing';
                    $statusColorClass = 'text-sky-600 bg-sky-500/10 border-sky-500/20';
                }
              ?>
                <div class="landing-activity-item p-3.5 rounded-2xl flex items-center justify-between shadow-sm gap-3 transition-all duration-300">
                  <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="landing-activity-icon w-8 h-8 rounded-xl flex items-center justify-center shrink-0">
                      <i data-lucide="<?= $iconName ?>" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="landing-activity-name font-bold text-xs truncate"><?= e($ro['service_name']) ?></div>
                      <div class="landing-activity-qty text-[10px] truncate">+<?= number_format($ro['quantity']) ?> delivered • Order #<?= $ro['id'] ?></div>
                    </div>
                  </div>
                  <span class="landing-activity-badge text-[11px] font-bold px-2.5 py-0.5 rounded-full border <?= $statusColorClass ?> shrink-0">
                    <?= $statusLabel ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="landing-promo-card p-4 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
              <div class="min-w-0 flex-1">
                <span class="text-[11px] font-bold block uppercase tracking-wider opacity-60">WALLET BONUS</span>
                <span class="text-sm font-extrabold break-words block">10% Extra on Every Deposit</span>
              </div>
              <a href="/register" class="landing-promo-btn px-4 py-2 rounded-xl text-xs font-bold shrink-0 whitespace-nowrap transition-all">
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
        <h2 class="landing-services-heading text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Popular SMM Services</h2>
        <p class="landing-services-sub text-sm text-slate-500">Unbeatable rates, genuine engagement, and non-drop guarantees across all platforms.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($services as $srv): ?>
          <div class="landing-service-card p-5 sm:p-6 rounded-3xl shadow-sm transition-all duration-200 flex flex-col justify-between overflow-hidden">
            <div class="min-w-0 mb-4">
              <div class="flex items-center justify-between gap-2 mb-3 min-w-0">
                <span class="landing-service-cat text-xs font-bold px-3 py-1 rounded-full truncate max-w-[60%]">
                  <?= e($srv['category_name']) ?>
                </span>
                <span class="landing-service-rate text-xs font-extrabold text-slate-700 shrink-0 whitespace-nowrap">
                  $<?= number_format($srv['rate'], 2) ?> / 1K
                </span>
              </div>
              <h3 class="landing-service-title font-bold text-base text-slate-800 mb-2 break-words"><?= e($srv['name']) ?></h3>
              <p class="landing-service-desc text-xs text-slate-500 line-clamp-3 break-words leading-relaxed"><?= e($srv['description']) ?></p>
            </div>
            <a href="/register" class="landing-service-btn block w-full py-2.5 rounded-xl font-bold text-xs text-center transition-all shrink-0">
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

  <style>
    @keyframes slideInDownSmooth {
      0% {
        opacity: 0;
        transform: translateY(-16px) scale(0.97);
      }
      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    .landing-activity-item-new {
      animation: slideInDownSmooth 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
  </style>

  <script>
    if (window.lucide) lucide.createIcons();

    // Live rotating activity stream using 100% real database orders
    (function initRealOrdersRotation() {
      const realOrders = <?= json_encode(array_values($recentOrders), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
      const container = document.getElementById('hero-orders-list');
      if (!container || !realOrders || realOrders.length === 0) return;

      let currentIndex = Math.min(3, realOrders.length - 1);

      function getCategoryIcon(catSlug) {
        const slug = (catSlug || '').toLowerCase();
        if (slug.includes('youtube')) return 'youtube';
        if (slug.includes('tiktok')) return 'music-2';
        if (slug.includes('telegram')) return 'send';
        if (slug.includes('twitter') || slug.includes('x')) return 'twitter';
        if (slug.includes('facebook')) return 'thumbs-up';
        if (slug.includes('linkedin')) return 'linkedin';
        if (slug.includes('instagram')) return 'instagram';
        return 'sparkles';
      }

      function getStatusInfo(status) {
        if (status === 'in_progress') {
          return { label: 'In Progress', colorClass: 'text-amber-600 bg-amber-500/10 border-amber-500/20' };
        }
        if (status === 'pending' || status === 'processing') {
          return { label: 'Processing', colorClass: 'text-sky-600 bg-sky-500/10 border-sky-500/20' };
        }
        return { label: 'Completed', colorClass: 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20' };
      }

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
      }

      setInterval(() => {
        if (realOrders.length <= 1) return;
        currentIndex = (currentIndex + 1) % realOrders.length;
        const order = realOrders[currentIndex];
        if (!order) return;

        const icon = getCategoryIcon(order.category_slug);
        const status = getStatusInfo(order.status);
        const qtyFormatted = Number(order.quantity || 0).toLocaleString();

        const newItem = document.createElement('div');
        newItem.className = 'landing-activity-item landing-activity-item-new p-3.5 rounded-2xl flex items-center justify-between shadow-sm gap-3 transition-all duration-300';
        newItem.innerHTML = `
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div class="landing-activity-icon w-8 h-8 rounded-xl flex items-center justify-center shrink-0">
              <i data-lucide="${icon}" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0 flex-1">
              <div class="landing-activity-name font-bold text-xs truncate">${escapeHtml(order.service_name)}</div>
              <div class="landing-activity-qty text-[10px] truncate">+${qtyFormatted} delivered • Order #${order.id}</div>
            </div>
          </div>
          <span class="landing-activity-badge text-[11px] font-bold px-2.5 py-0.5 rounded-full border ${status.colorClass} shrink-0">
            ${status.label}
          </span>
        `;

        container.insertBefore(newItem, container.firstChild);

        // Keep maximum 3 items visible
        while (container.children.length > 3) {
          container.removeChild(container.lastChild);
        }

        if (window.lucide) {
          lucide.createIcons({
            root: newItem
          });
        }
      }, 3500);
    })();
  </script>
</body>
</html>
