<?php
$pageTitle = 'Dashboard - SMM Pro';
$activePage = 'dashboard';
require_once __DIR__ . '/../layouts/header.php';

$db = getDB();
$userId = $user['id'];

// Real user metrics from MySQL database
$totalOrdersStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$totalOrdersStmt->execute([$userId]);
$totalOrders = (int)$totalOrdersStmt->fetchColumn();

$completedOrdersStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
$completedOrdersStmt->execute([$userId]);
$completedOrders = (int)$completedOrdersStmt->fetchColumn();

$pendingOrdersStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending', 'processing', 'in_progress')");
$pendingOrdersStmt->execute([$userId]);
$pendingOrders = (int)$pendingOrdersStmt->fetchColumn();

$totalSpentStmt = $db->prepare("SELECT COALESCE(SUM(charge), 0) FROM orders WHERE user_id = ?");
$totalSpentStmt->execute([$userId]);
$totalSpent = (float)$totalSpentStmt->fetchColumn();

$completedPct = ($totalOrders > 0) ? round(($completedOrders / $totalOrders) * 100) : 0;

// Real 7-day spending & order activity from MySQL
$salesPeriodDays = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-$i days"));
    $salesPeriodDays[$dateKey] = [
        'date' => $dateKey,
        'label' => date('D', strtotime($dateKey)),
        'amount' => 0.0,
        'count' => 0
    ];
}

$chartStmt = $db->prepare("
    SELECT DATE(created_at) AS order_date, COUNT(*) as order_count, COALESCE(SUM(charge), 0) as total_charge
    FROM orders
    WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
$chartStmt->execute([$userId]);
while ($row = $chartStmt->fetch()) {
    $d = $row['order_date'];
    if (isset($salesPeriodDays[$d])) {
        $salesPeriodDays[$d]['amount'] = (float)$row['total_charge'];
        $salesPeriodDays[$d]['count'] = (int)$row['order_count'];
    }
}

// Popular / Trending Services from MySQL
$popularServices = $db->query("
    SELECT s.*, c.name as category_name 
    FROM services s 
    LEFT JOIN categories c ON s.category_id = c.id 
    WHERE s.status = 'active' 
    ORDER BY s.id ASC 
    LIMIT 6
")->fetchAll();

// Recent Orders from MySQL
$recentOrdersStmt = $db->prepare("
    SELECT o.*, s.name as service_name 
    FROM orders o 
    LEFT JOIN services s ON o.service_id = s.id 
    WHERE o.user_id = ? 
    ORDER BY o.id DESC 
    LIMIT 6
");
$recentOrdersStmt->execute([$userId]);
$recentOrders = $recentOrdersStmt->fetchAll();
?>

<div class="space-y-8 max-w-7xl mx-auto">

  <!-- ========================================================================= -->
  <!-- 1. SMM PRO HERO BANNER — EXACT REFERENCE VISUAL REPRODUCTION -->
  <!-- ========================================================================= -->
  <section class="relative rounded-3xl bg-gradient-to-br from-[#121222] via-[#0E0E1B] to-[#0A0A14] border border-white/10 p-6 sm:p-8 lg:p-10 overflow-hidden shadow-2xl">
    <!-- Deep Pink Radial Glow Aura behind Hero -->
    <div class="absolute -right-20 -top-20 w-[600px] h-[600px] bg-gradient-to-br from-[#FF2D78]/25 via-[#D91B5C]/15 to-transparent rounded-full blur-[90px] pointer-events-none"></div>
    <div class="absolute -left-20 -bottom-20 w-[400px] h-[400px] bg-[#9333EA]/10 rounded-full blur-[80px] pointer-events-none"></div>

    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
      
      <!-- Hero Left: Typography, Badges & CTA Buttons -->
      <div class="lg:col-span-7 space-y-6">
        <!-- Glowing Pill Badge -->
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#18182D] border border-[#FF2D78]/40 shadow-lg shadow-[#FF2D78]/15">
          <span class="w-2 h-2 rounded-full bg-[#FF2D78] animate-ping"></span>
          <span class="text-xs font-black tracking-wide text-white uppercase flex items-center gap-1.5">
            <i data-lucide="flame" class="w-3.5 h-3.5 text-[#FF2D78]"></i>
            #1 Rated SMM Growth Platform
          </span>
        </div>

        <!-- Headline Typography -->
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight leading-[1.12]">
          Skyrocket Your <span class="bg-gradient-to-r from-[#FF2D78] via-[#FF659F] to-[#FF2D78] bg-clip-text text-transparent">Social Reach</span> With Next-Gen SMM
        </h1>

        <!-- Supporting Subtitle -->
        <p class="text-sm sm:text-base text-[#9D9DB8] max-w-xl leading-relaxed">
          The cheapest, fastest, and most reliable automated growth services for Instagram, TikTok, YouTube, Telegram, and X. Instant automated delivery with high retention guarantee.
        </p>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-3.5 pt-2">
          <a href="/order" class="smm-btn-pink px-7 py-3.5 text-sm sm:text-base">
            <i data-lucide="zap" class="w-4 h-4 fill-white"></i>
            <span>Start New Order</span>
          </a>
          <a href="/add-funds" class="smm-btn-dark px-6 py-3.5 text-sm sm:text-base">
            <i data-lucide="credit-card" class="w-4 h-4 text-[#FF2D78]"></i>
            <span>Add Funds</span>
          </a>
        </div>

        <!-- Trust Highlights Row -->
        <div class="grid grid-cols-3 gap-3 pt-4 border-t border-white/5 max-w-lg">
          <div class="flex items-center gap-2 text-xs text-[#9D9DB8]">
            <div class="w-6 h-6 rounded-lg bg-[#FF2D78]/15 flex items-center justify-center text-[#FF2D78] shrink-0">
              <i data-lucide="clock" class="w-3.5 h-3.5"></i>
            </div>
            <div>
              <strong class="block text-white font-bold">0.05s Start</strong>
              <span class="text-[10px] text-[#6C6C8A]">Instant Auto API</span>
            </div>
          </div>
          <div class="flex items-center gap-2 text-xs text-[#9D9DB8]">
            <div class="w-6 h-6 rounded-lg bg-[#10B981]/15 flex items-center justify-center text-[#10B981] shrink-0">
              <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
            </div>
            <div>
              <strong class="block text-white font-bold">Guaranteed</strong>
              <span class="text-[10px] text-[#6C6C8A]">Refill Protected</span>
            </div>
          </div>
          <div class="flex items-center gap-2 text-xs text-[#9D9DB8]">
            <div class="w-6 h-6 rounded-lg bg-[#3B82F6]/15 flex items-center justify-center text-[#60A5FA] shrink-0">
              <i data-lucide="headphones" class="w-3.5 h-3.5"></i>
            </div>
            <div>
              <strong class="block text-white font-bold">24/7 Support</strong>
              <span class="text-[10px] text-[#6C6C8A]">Live Priority Care</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Hero Right: 3D Social Media Phone Visual Artwork with Floating Badges -->
      <div class="lg:col-span-5 relative flex items-center justify-center min-h-[380px] sm:min-h-[440px]">
        
        <!-- Glowing Circular Backdrop for Phone -->
        <div class="absolute w-72 h-72 sm:w-84 sm:h-84 rounded-full bg-gradient-to-tr from-[#FF2D78]/30 to-[#9333EA]/20 blur-3xl smm-pulse-glow pointer-events-none"></div>

        <!-- 3D Tilted Smartphone Mockup -->
        <div class="relative w-64 sm:w-72 bg-[#0A0A12] border-4 border-[#25253A] rounded-[2.5rem] p-3 shadow-2xl shadow-black/80 ring-1 ring-white/10 transform rotate-[-4deg] hover:rotate-0 transition-transform duration-500 z-10">
          
          <!-- Phone Speaker / Dynamic Island Notch -->
          <div class="w-24 h-4 bg-[#181828] rounded-full mx-auto mb-2 flex items-center justify-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-white/20"></span>
            <span class="w-2.5 h-1 rounded-full bg-white/30"></span>
          </div>

          <!-- Phone Screen Container -->
          <div class="rounded-[2rem] bg-gradient-to-b from-[#131326] to-[#0D0D18] p-3.5 space-y-3 border border-white/5">
            
            <!-- In-Phone Header -->
            <div class="flex items-center justify-between pb-2 border-b border-white/10 text-white">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-[#FF2D78] to-[#D91B5C] flex items-center justify-center text-[10px] font-black">
                  PRO
                </div>
                <span class="text-xs font-bold">ViralCampaign</span>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-[#10B981]/20 text-[#10B981] font-bold text-[9px] flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-[#10B981]"></span> LIVE
              </span>
            </div>

            <!-- In-Phone Analytics Stat Box -->
            <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1">
              <div class="flex justify-between items-center text-[10px] text-[#9D9DB8]">
                <span>Total Growth Surge</span>
                <span class="text-[#FF2D78] font-bold">+284.5%</span>
              </div>
              <div class="text-lg font-black text-white">128,490 <span class="text-xs font-semibold text-[#9D9DB8]">Followers</span></div>
              
              <!-- In-Phone Mini Sparkline Curve -->
              <svg viewBox="0 0 100 28" class="w-full h-7 mt-1 text-[#FF2D78] overflow-visible">
                <defs>
                  <linearGradient id="phoneGlow" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#FF2D78" stop-opacity="0.45" />
                    <stop offset="100%" stop-color="#FF2D78" stop-opacity="0.0" />
                  </linearGradient>
                </defs>
                <path d="M 0,22 Q 15,18 30,12 T 60,8 T 85,4 T 100,1 L 100,28 L 0,28 Z" fill="url(#phoneGlow)" />
                <path d="M 0,22 Q 15,18 30,12 T 60,8 T 85,4 T 100,1" fill="none" stroke="#FF2D78" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="100" cy="1" r="3" fill="#FFFFFF" stroke="#FF2D78" stroke-width="1.5" />
              </svg>
            </div>

            <!-- In-Phone Mini Order Feed Items -->
            <div class="space-y-1.5 text-[11px]">
              <div class="flex items-center justify-between p-2 rounded-lg bg-white/[0.03] border border-white/5">
                <div class="flex items-center gap-2">
                  <span class="w-5 h-5 rounded-md bg-gradient-to-tr from-[#E1306C] to-[#F77737] flex items-center justify-center text-white text-[9px]">
                    <i data-lucide="instagram" class="w-3 h-3"></i>
                  </span>
                  <span class="text-white font-medium">IG Reel Likes</span>
                </div>
                <span class="text-[#34D399] font-bold">+10,000</span>
              </div>
              <div class="flex items-center justify-between p-2 rounded-lg bg-white/[0.03] border border-white/5">
                <div class="flex items-center gap-2">
                  <span class="w-5 h-5 rounded-md bg-[#FF0000] flex items-center justify-center text-white text-[9px]">
                    <i data-lucide="play" class="w-3 h-3 fill-white"></i>
                  </span>
                  <span class="text-white font-medium">YT 4K Watch Time</span>
                </div>
                <span class="text-[#34D399] font-bold">+4,000h</span>
              </div>
              <div class="flex items-center justify-between p-2 rounded-lg bg-white/[0.03] border border-white/5">
                <div class="flex items-center gap-2">
                  <span class="w-5 h-5 rounded-md bg-[#000000] border border-white/20 flex items-center justify-center text-white text-[9px]">
                    <i data-lucide="music-2" class="w-3 h-3 text-[#00F2FE]"></i>
                  </span>
                  <span class="text-white font-medium">TikTok Viral Shares</span>
                </div>
                <span class="text-[#34D399] font-bold">+50,000</span>
              </div>
            </div>

            <div class="w-full py-1.5 rounded-lg bg-gradient-to-r from-[#FF2D78] to-[#D91B5C] text-center text-white font-black text-[10px] tracking-wide shadow-md shadow-[#FF2D78]/30">
              DELIVERED 100%
            </div>
          </div>
        </div>

        <!-- Floating Glassmorphic Badge: Top-Left (Instagram Heart + Likes) -->
        <div class="absolute -top-4 -left-4 sm:-left-8 smm-glass-badge p-2.5 sm:p-3 flex items-center gap-2.5 z-20 smm-float">
          <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-[#FF2D78] to-[#E1306C] flex items-center justify-center text-white shadow-md shadow-[#FF2D78]/40">
            <i data-lucide="heart" class="w-4 h-4 fill-white"></i>
          </div>
          <div>
            <div class="text-xs font-black text-white leading-tight">+15,450 Likes</div>
            <div class="text-[10px] text-[#9D9DB8] font-semibold">Instant High-Retention</div>
          </div>
        </div>

        <!-- Floating Glassmorphic Badge: Top-Right (YouTube Views) -->
        <div class="absolute top-8 -right-4 sm:-right-6 smm-glass-badge p-2.5 sm:p-3 flex items-center gap-2.5 z-20 smm-float-slow">
          <div class="w-8 h-8 rounded-xl bg-[#FF0000] flex items-center justify-center text-white shadow-md shadow-red-500/40">
            <i data-lucide="play" class="w-4 h-4 fill-white"></i>
          </div>
          <div>
            <div class="text-xs font-black text-white leading-tight">250K Views</div>
            <div class="text-[10px] text-[#34D399] font-bold">Monetization Ready</div>
          </div>
        </div>

        <!-- Floating Glassmorphic Badge: Bottom-Left (TikTok Retention) -->
        <div class="absolute bottom-6 -left-6 sm:-left-10 smm-glass-badge p-2.5 sm:p-3 flex items-center gap-2.5 z-20 smm-float-reverse">
          <div class="w-8 h-8 rounded-xl bg-[#0F0F1A] border border-white/20 flex items-center justify-center text-[#FF2D78] shadow-md shadow-black/50">
            <i data-lucide="zap" class="w-4 h-4 fill-[#FF2D78]"></i>
          </div>
          <div>
            <div class="text-xs font-black text-white leading-tight">0.01s Auto Start</div>
            <div class="text-[10px] text-[#9D9DB8] font-semibold">99.8% Retention</div>
          </div>
        </div>

        <!-- Floating Glassmorphic Badge: Bottom-Right (Order Completed) -->
        <div class="absolute -bottom-4 right-0 sm:right-2 smm-glass-badge p-2.5 sm:p-3 flex items-center gap-2.5 z-20 smm-float">
          <div class="w-8 h-8 rounded-xl bg-[#10B981] flex items-center justify-center text-white shadow-md shadow-[#10B981]/40">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
          </div>
          <div>
            <div class="text-xs font-black text-white leading-tight">Order Completed</div>
            <div class="text-[10px] text-[#9D9DB8]">Real Active Accounts</div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ========================================================================= -->
  <!-- 2. DASHBOARD STATISTICS CARDS (4 METRICS) -->
  <!-- ========================================================================= -->
  <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
    
    <!-- Stat 1: Total Balance -->
    <div class="smm-card p-5 sm:p-6 flex flex-col justify-between relative overflow-hidden group">
      <div class="flex items-start justify-between mb-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8] block mb-1">Account Balance</span>
          <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            <?= format_price($user['balance']) ?>
          </div>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-[#FF2D78]/15 border border-[#FF2D78]/30 flex items-center justify-center text-[#FF2D78] shadow-lg shadow-[#FF2D78]/20 group-hover:scale-105 transition-transform">
          <i data-lucide="wallet" class="w-5 h-5"></i>
        </div>
      </div>
      <div class="flex items-center justify-between pt-3 border-t border-white/5 text-xs">
        <span class="text-[#9D9DB8]">Available Credits</span>
        <a href="/add-funds" class="text-[#FF2D78] font-bold hover:underline flex items-center gap-1">
          <span>Deposit</span> <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
      </div>
    </div>

    <!-- Stat 2: Total Spent -->
    <div class="smm-card p-5 sm:p-6 flex flex-col justify-between relative overflow-hidden group">
      <div class="flex items-start justify-between mb-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8] block mb-1">Total Spent</span>
          <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            <?= format_price($totalSpent) ?>
          </div>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-purple-500/15 border border-purple-500/30 flex items-center justify-center text-purple-400 shadow-lg shadow-purple-500/20 group-hover:scale-105 transition-transform">
          <i data-lucide="credit-card" class="w-5 h-5"></i>
        </div>
      </div>
      <div class="flex items-center justify-between pt-3 border-t border-white/5 text-xs">
        <span class="text-[#9D9DB8]">Lifetime Expenditure</span>
        <span class="text-purple-400 font-bold"><?= $totalOrders ?> orders</span>
      </div>
    </div>

    <!-- Stat 3: Total Orders -->
    <div class="smm-card p-5 sm:p-6 flex flex-col justify-between relative overflow-hidden group">
      <div class="flex items-start justify-between mb-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8] block mb-1">Total Orders</span>
          <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            <?= number_format($totalOrders) ?>
          </div>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-blue-500/15 border border-blue-500/30 flex items-center justify-center text-blue-400 shadow-lg shadow-blue-500/20 group-hover:scale-105 transition-transform">
          <i data-lucide="shopping-bag" class="w-5 h-5"></i>
        </div>
      </div>
      <div class="flex items-center justify-between pt-3 border-t border-white/5 text-xs">
        <span class="text-[#9D9DB8]">Completed & Active</span>
        <a href="/orders" class="text-blue-400 font-bold hover:underline flex items-center gap-1">
          <span>View All</span> <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
      </div>
    </div>

    <!-- Stat 4: Completed Orders -->
    <div class="smm-card p-5 sm:p-6 flex flex-col justify-between relative overflow-hidden group">
      <div class="flex items-start justify-between mb-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8] block mb-1">Delivered Orders</span>
          <div class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            <?= number_format($completedOrders) ?>
          </div>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
          <i data-lucide="check-circle-2" class="w-5 h-5"></i>
        </div>
      </div>
      <div class="flex items-center justify-between pt-3 border-t border-white/5 text-xs">
        <span class="text-[#9D9DB8]">Success Rate</span>
        <span class="text-emerald-400 font-black"><?= $completedPct ?>%</span>
      </div>
    </div>

  </section>

  <!-- ========================================================================= -->
  <!-- 3. SPENDING & ACTIVITY CHART -->
  <!-- ========================================================================= -->
  <section class="smm-card p-6 sm:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-white/5">
      <div>
        <h2 class="text-lg font-black text-white flex items-center gap-2.5">
          <i data-lucide="trending-up" class="w-5 h-5 text-[#FF2D78]"></i>
          Spending & Order Activity
        </h2>
        <p class="text-xs text-[#9D9DB8] mt-0.5">Real-time breakdown of your orders over the past 7 days</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="px-3 py-1 rounded-full bg-[#18182D] border border-white/10 text-xs font-bold text-white">
          Last 7 Days
        </span>
      </div>
    </div>

    <!-- SVG Area Chart -->
    <?php
    $maxSpend = 0.01;
    foreach ($salesPeriodDays as $day) {
        if ($day['amount'] > $maxSpend) $maxSpend = $day['amount'];
    }
    $points = [];
    $idx = 0;
    $countDays = count($salesPeriodDays);
    foreach ($salesPeriodDays as $day) {
        $x = ($countDays > 1) ? round(($idx / ($countDays - 1)) * 100, 2) : 50;
        $y = round(80 - (($day['amount'] / $maxSpend) * 60), 2);
        $points[] = "$x,$y";
        $idx++;
    }
    $polyline = implode(' ', $points);
    $firstPoint = $points[0] ?? '0,80';
    $lastPoint = $points[count($points)-1] ?? '100,80';
    $areaPath = "M 0,80 L " . implode(' L ', $points) . " L 100,80 Z";
    ?>

    <div class="relative w-full h-44 sm:h-52 pt-2">
      <svg viewBox="0 0 100 85" preserveAspectRatio="none" class="w-full h-full overflow-visible">
        <defs>
          <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#FF2D78" stop-opacity="0.35" />
            <stop offset="100%" stop-color="#FF2D78" stop-opacity="0.0" />
          </linearGradient>
        </defs>
        <!-- Horizontal Guide Lines -->
        <line x1="0" y1="20" x2="100" y2="20" stroke="rgba(255,255,255,0.05)" stroke-dasharray="2 2" stroke-width="0.5" />
        <line x1="0" y1="50" x2="100" y2="50" stroke="rgba(255,255,255,0.05)" stroke-dasharray="2 2" stroke-width="0.5" />
        <line x1="0" y1="80" x2="100" y2="80" stroke="rgba(255,255,255,0.1)" stroke-width="0.5" />

        <!-- Area Fill -->
        <path d="<?= $areaPath ?>" fill="url(#chartGradient)" />

        <!-- Line Stroke -->
        <polyline points="<?= $polyline ?>" fill="none" stroke="#FF2D78" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

        <!-- Data Point Nodes -->
        <?php foreach ($points as $p): 
          $coords = explode(',', $p);
        ?>
          <circle cx="<?= $coords[0] ?>" cy="<?= $coords[1] ?>" r="2" fill="#FFFFFF" stroke="#FF2D78" stroke-width="1.5" />
        <?php endforeach; ?>
      </svg>
    </div>

    <!-- Date Axis Labels -->
    <div class="flex items-center justify-between text-xs text-[#9D9DB8] pt-3 border-t border-white/5 font-semibold">
      <?php foreach ($salesPeriodDays as $day): ?>
        <div class="text-center">
          <span class="block text-white font-bold"><?= e($day['label']) ?></span>
          <span class="text-[10px] text-[#6C6C8A]"><?= format_price($day['amount']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ========================================================================= -->
  <!-- 4. POPULAR SERVICES GRID -->
  <!-- ========================================================================= -->
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-black text-white flex items-center gap-2">
          <i data-lucide="flame" class="w-5 h-5 text-[#FF2D78]"></i>
          Trending & Popular Services
        </h2>
        <p class="text-xs text-[#9D9DB8]">Top performing high-speed services trusted by over 50,000 customers</p>
      </div>
      <a href="/services" class="text-xs font-bold text-[#FF2D78] hover:underline flex items-center gap-1">
        <span>View Full Catalog</span> <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
      <?php if (empty($popularServices)): ?>
        <div class="col-span-3 text-center py-10 text-[#9D9DB8] smm-card">
          No services currently available.
        </div>
      <?php else: ?>
        <?php foreach ($popularServices as $svc): ?>
          <div class="smm-card p-5 flex flex-col justify-between hover:border-[#FF2D78]/40 transition-all">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-[#18182D] border border-white/10 text-[#FF2D78]">
                  <?= e($svc['category_name'] ?? 'General') ?>
                </span>
                <span class="text-xs font-black text-white bg-[#FF2D78]/15 px-2 py-0.5 rounded-md border border-[#FF2D78]/30">
                  <?= format_price($svc['price_per_k'] ?? $svc['rate']) ?> <span class="text-[10px] text-[#9D9DB8]">/1k</span>
                </span>
              </div>
              <h3 class="text-sm font-bold text-white line-clamp-2 mb-2 leading-snug">
                <?= e($svc['name']) ?>
              </h3>
              <div class="flex items-center gap-3 text-[11px] text-[#9D9DB8] mb-4">
                <span>Min: <strong class="text-white"><?= number_format($svc['min_quantity']) ?></strong></span>
                <span>•</span>
                <span>Max: <strong class="text-white"><?= number_format($svc['max_quantity']) ?></strong></span>
              </div>
            </div>

            <div class="pt-3 border-t border-white/5 flex items-center justify-between">
              <span class="text-[10px] text-[#10B981] font-bold flex items-center gap-1">
                <i data-lucide="zap" class="w-3 h-3 fill-[#10B981]"></i> Instant Start
              </span>
              <a href="/order?service_id=<?= (int)$svc['id'] ?>" class="smm-btn-pink px-3.5 py-1.5 text-xs">
                <span>Order Now</span>
                <i data-lucide="arrow-right" class="w-3 h-3"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- ========================================================================= -->
  <!-- 5. RECENT ORDERS TABLE -->
  <!-- ========================================================================= -->
  <section class="smm-card overflow-hidden">
    <div class="p-5 sm:p-6 flex items-center justify-between border-b border-white/5">
      <div>
        <h2 class="text-base font-black text-white flex items-center gap-2">
          <i data-lucide="history" class="w-5 h-5 text-[#FF2D78]"></i>
          Recent Orders
        </h2>
        <p class="text-xs text-[#9D9DB8]">Your latest order submissions and fulfillment status</p>
      </div>
      <a href="/orders" class="text-xs font-bold text-[#FF2D78] hover:underline flex items-center gap-1">
        <span>View Order History</span> <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
      </a>
    </div>

    <div class="overflow-x-auto">
      <table class="smm-table">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Service</th>
            <th>Quantity</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
            <tr>
              <td colspan="6" class="text-center py-8 text-[#9D9DB8]">
                No orders placed yet. <a href="/order" class="text-[#FF2D78] font-bold hover:underline">Create your first order</a>.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentOrders as $ord): 
              $status = strtolower($ord['status']);
              $badgeClass = 'smm-badge-pending';
              if ($status === 'completed') $badgeClass = 'smm-badge-completed';
              elseif (in_array($status, ['processing', 'in_progress'])) $badgeClass = 'smm-badge-in-progress';
              elseif (in_array($status, ['canceled', 'cancelled', 'failed'])) $badgeClass = 'smm-badge-canceled';
              elseif ($status === 'partial') $badgeClass = 'smm-badge-partial';
            ?>
              <tr>
                <td class="font-mono text-xs font-bold text-[#9D9DB8]">
                  #<?= (int)$ord['id'] ?>
                </td>
                <td class="font-medium text-white max-w-xs truncate" title="<?= e($ord['service_name'] ?? 'Service') ?>">
                  <?= e($ord['service_name'] ?? 'Custom Service') ?>
                </td>
                <td class="font-semibold text-white">
                  <?= number_format($ord['quantity']) ?>
                </td>
                <td class="font-bold text-[#FF2D78]">
                  <?= format_price($ord['charge']) ?>
                </td>
                <td>
                  <span class="<?= $badgeClass ?>">
                    <?= ucfirst($ord['status']) ?>
                  </span>
                </td>
                <td class="text-xs text-[#9D9DB8]">
                  <?= date('M d, H:i', strtotime($ord['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
