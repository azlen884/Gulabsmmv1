<?php
$pageTitle = 'Dashboard - RoseSMM';
$activePage = 'dashboard';
require_once __DIR__ . '/../layouts/user_header.php';

// Fetch database records for dashboard
$db = getDB();

// 1. Sliders from MySQL
$sliders = $db->query("SELECT * FROM sliders WHERE status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();
if (empty($sliders)) {
    $sliders = [
        [
            'id' => 1,
            'title' => 'Grow Your Social Media',
            'tagline' => 'Fast • Secure • Reliable',
            'subtitle' => 'Get real engagement and boost your online presence with our premium SMM services.',
            'button_text' => 'Explore Services →',
            'button_url' => '/services',
            'image_url' => '/assets/images/banner-hero-1.svg'
        ]
    ];
}

// 2. User statistics
$userId = $user['id'];
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

// Real percentage indicators calculated from user's actual database records
$completedPercentage = ($totalOrders > 0) ? round(($completedOrders / $totalOrders) * 100) : null;
$pendingPercentage = ($totalOrders > 0) ? round(($pendingOrders / $totalOrders) * 100) : null;

// Period-over-period change (last 7 days vs previous 7 days) from user's orders
$periodStatsStmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) AS recent_orders,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) AS prev_orders,
        COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN charge END), 0) AS recent_spend,
        COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN charge END), 0) AS prev_spend
    FROM orders
    WHERE user_id = ?
");
$periodStatsStmt->execute([$userId]);
$periodStats = $periodStatsStmt->fetch();

$recentOrders = (int)($periodStats['recent_orders'] ?? 0);
$prevOrders = (int)($periodStats['prev_orders'] ?? 0);
if ($prevOrders > 0) {
    $ordersPctChange = round((($recentOrders - $prevOrders) / $prevOrders) * 100);
} elseif ($recentOrders > 0) {
    $ordersPctChange = 100;
} else {
    $ordersPctChange = null;
}

$recentSpend = (float)($periodStats['recent_spend'] ?? 0);
$prevSpend = (float)($periodStats['prev_spend'] ?? 0);
if ($prevSpend > 0) {
    $spendPctChange = round((($recentSpend - $prevSpend) / $prevSpend) * 100);
} elseif ($recentSpend > 0) {
    $spendPctChange = 100;
} else {
    $spendPctChange = null;
}

// Real daily sales/orders statistics for the last 7 days from the MySQL database
$salesPeriodDays = [];
$totalPeriodSales = 0.0;
$totalPeriodCount = 0;
$maxDayAmount = 0.0;
$peakDay = null;

for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-$i days"));
    $salesPeriodDays[$dateKey] = [
        'date' => $dateKey,
        'label' => date('d M', strtotime($dateKey)),
        'amount' => 0.0,
        'count' => 0
    ];
}

$salesHistoryStmt = $db->prepare("
    SELECT DATE(created_at) AS order_date, 
           COALESCE(SUM(charge), 0) AS daily_total, 
           COUNT(*) AS daily_count
    FROM orders
    WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
$salesHistoryStmt->execute([$userId]);
$salesHistoryRows = $salesHistoryStmt->fetchAll();

foreach ($salesHistoryRows as $shRow) {
    $d = $shRow['order_date'];
    if (isset($salesPeriodDays[$d])) {
        $amt = (float)$shRow['daily_total'];
        $cnt = (int)$shRow['daily_count'];
        $salesPeriodDays[$d]['amount'] = $amt;
        $salesPeriodDays[$d]['count'] = $cnt;
        $totalPeriodSales += $amt;
        $totalPeriodCount += $cnt;
        if ($amt > $maxDayAmount) {
            $maxDayAmount = $amt;
            $peakDay = $salesPeriodDays[$d];
        }
    }
}

if (!$peakDay && !empty($salesPeriodDays)) {
    $peakDay = end($salesPeriodDays);
}

// 3. Recently Ordered
$recentOrdersStmt = $db->prepare("
    SELECT o.*, s.name AS service_name, c.name AS category_name, c.icon AS category_icon
    FROM orders o
    JOIN services s ON o.service_id = s.id
    JOIN categories c ON s.category_id = c.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
");
$recentOrdersStmt->execute([$userId]);
$recentOrders = $recentOrdersStmt->fetchAll();

// 4. Categories & Popular Services
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();
$services = $db->query("
    SELECT s.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
    FROM services s
    JOIN categories c ON s.category_id = c.id
    WHERE s.status = 'active'
    ORDER BY s.sort_order ASC
    LIMIT 8
")->fetchAll();
?>

<!-- Alert Container for Order Messages -->
<div id="dashboard-alert" class="hidden mb-6 p-4 rounded-2xl border text-sm flex items-center justify-between">
  <div class="flex items-center gap-2">
    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
    <span id="dashboard-alert-text"></span>
  </div>
  <button onclick="document.getElementById('dashboard-alert').classList.add('hidden')">
    <i data-lucide="x" class="w-4 h-4"></i>
  </button>
</div>

<!-- SECTION 1: Top Hero Banner & Balance Card -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
  <!-- Hero Banner Slider from MySQL (Image + Text Together per slide) -->
  <div id="hero-slider-wrapper" class="xl:col-span-2 relative overflow-hidden rounded-3xl border border-[#FCD3DC] shadow-sm min-h-[250px] sm:min-h-[260px] md:min-h-[270px] flex flex-col justify-between group">
    <!-- Slides Container -->
    <div id="hero-slider-track" class="relative w-full h-full min-h-[250px] sm:min-h-[260px] md:min-h-[270px] overflow-hidden">
      <?php foreach ($sliders as $idx => $slide): ?>
        <div 
          class="hero-slide absolute inset-0 w-full h-full p-5 sm:p-7 md:p-8 flex flex-col justify-center transition-all duration-700 ease-in-out <?= $idx === 0 ? 'opacity-100 z-10 translate-x-0' : 'opacity-0 z-0 pointer-events-none translate-x-8' ?>"
          data-slide-index="<?= $idx ?>"
          style="background: <?= $idx % 2 === 0 ? 'linear-gradient(135deg, #FFE8EC 0%, #FFDCE3 40%, #FFCFDA 100%)' : 'linear-gradient(135deg, #FFF0F3 0%, #FFE2E8 45%, #FCD3DC 100%)' ?>;"
        >
          <div class="flex items-center justify-between gap-3 sm:gap-6 w-full h-full relative z-10">
            <!-- Slide Text Content -->
            <div class="flex-1 min-w-0 text-left">
              <?php if (!empty($slide['tagline'])): ?>
                <div class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-0.5 sm:py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-600 text-[10px] sm:text-xs font-bold mb-2">
                  <i data-lucide="sparkles" class="w-3 h-3 sm:w-3.5 sm:h-3.5"></i>
                  <span class="truncate"><?= e($slide['tagline']) ?></span>
                </div>
              <?php endif; ?>

              <h2 class="text-lg sm:text-2xl md:text-3xl font-extrabold text-slate-800 tracking-tight leading-tight mb-1.5 sm:mb-2 break-words">
                <?= e($slide['title']) ?>
              </h2>

              <?php if (!empty($slide['subtitle'])): ?>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-3 sm:mb-5 line-clamp-2 sm:line-clamp-3 break-words max-w-prose">
                  <?= e($slide['subtitle']) ?>
                </p>
              <?php endif; ?>

              <div>
                <a href="<?= e($slide['button_url'] ?: '/services') ?>" class="inline-flex items-center gap-1.5 sm:gap-2 px-4 sm:px-6 py-1.5 sm:py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 active:scale-95 text-white text-xs sm:text-sm font-bold shadow-md hover:shadow transition-all">
                  <span><?= e($slide['button_text'] ?: 'Explore Services →') ?></span>
                </a>
              </div>
            </div>

            <!-- Slide Banner Image / Artwork (visible on mobile, tablet, and desktop) -->
            <div class="shrink-0 flex items-center justify-center sm:justify-end w-24 sm:w-44 md:w-56 lg:w-64 h-full max-h-32 sm:max-h-52 relative select-none pointer-events-none">
              <?php if (!empty($slide['image_url'])): ?>
                <img 
                  src="<?= e($slide['image_url']) ?>" 
                  alt="<?= e($slide['title']) ?>" 
                  class="w-full max-h-24 sm:max-h-44 md:max-h-48 object-contain drop-shadow-md transition-transform duration-700 hover:scale-105"
                  loading="lazy"
                >
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Navigation Controls (if more than 1 slide) -->
    <?php if (count($sliders) > 1): ?>
      <!-- Prev / Next Arrows -->
      <button 
        type="button" 
        id="hero-slider-prev" 
        class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-white/80 hover:bg-white text-slate-700 shadow-sm hover:shadow flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 focus:opacity-100" 
        aria-label="Previous Slide"
      >
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
      </button>
      <button 
        type="button" 
        id="hero-slider-next" 
        class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-white/80 hover:bg-white text-slate-700 shadow-sm hover:shadow flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 focus:opacity-100" 
        aria-label="Next Slide"
      >
        <i data-lucide="chevron-right" class="w-4 h-4"></i>
      </button>

      <!-- Pagination Indicators -->
      <div class="absolute bottom-3 right-5 sm:right-7 z-20 flex items-center gap-1.5" id="hero-slider-dots">
        <?php foreach ($sliders as $idx => $s): ?>
          <button 
            type="button" 
            class="hero-dot h-2 rounded-full transition-all duration-300 <?= $idx === 0 ? 'w-6 bg-rose-500' : 'w-2 bg-rose-300/80 hover:bg-rose-400' ?>" 
            data-slide-to="<?= $idx ?>" 
            aria-label="Go to slide <?= $idx + 1 ?>"
          ></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right Top Balance Card matching screenshot -->
  <div class="rounded-3xl bg-gradient-to-br from-[#FF4D79] via-[#FF3B69] to-[#FF2E63] text-white p-6 shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs uppercase tracking-wider text-rose-100 font-semibold">Your Balance</span>
        <div class="flex items-center gap-2">
          <button type="button" onclick="toggleBalanceVisibility()" class="p-1 text-rose-100 hover:text-white transition-colors" title="Toggle balance">
            <i data-lucide="eye" id="balance-eye-icon" class="w-4 h-4"></i>
          </button>
          <a href="/add-funds" class="px-3 py-1 rounded-full bg-white text-rose-600 font-bold text-xs shadow-sm hover:bg-rose-50 transition-colors">
            + Add Funds
          </a>
        </div>
      </div>

      <div class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-6" id="user-balance-display" data-original="<?= format_price($user['balance']) ?>">
        <?= format_price($user['balance']) ?>
      </div>
    </div>

    <!-- 4 Circular Outline Action Buttons matching screenshot -->
    <div class="grid grid-cols-4 gap-2 pt-4 border-t border-white/20 text-center">
      <a href="/add-funds" class="group flex flex-col items-center gap-1.5">
        <div class="w-10 h-10 rounded-full border border-white/40 flex items-center justify-center group-hover:bg-white/20 transition-colors">
          <i data-lucide="arrow-down-to-line" class="w-4 h-4"></i>
        </div>
        <span class="text-[11px] font-medium text-rose-100">Deposit</span>
      </a>
      <a href="/wallet" class="group flex flex-col items-center gap-1.5">
        <div class="w-10 h-10 rounded-full border border-white/40 flex items-center justify-center group-hover:bg-white/20 transition-colors">
          <i data-lucide="arrow-up-from-line" class="w-4 h-4"></i>
        </div>
        <span class="text-[11px] font-medium text-rose-100">Withdraw</span>
      </a>
      <a href="/transactions" class="group flex flex-col items-center gap-1.5">
        <div class="w-10 h-10 rounded-full border border-white/40 flex items-center justify-center group-hover:bg-white/20 transition-colors">
          <i data-lucide="receipt" class="w-4 h-4"></i>
        </div>
        <span class="text-[11px] font-medium text-rose-100">Transactions</span>
      </a>
      <a href="/orders" class="group flex flex-col items-center gap-1.5">
        <div class="w-10 h-10 rounded-full border border-white/40 flex items-center justify-center group-hover:bg-white/20 transition-colors">
          <i data-lucide="history" class="w-4 h-4"></i>
        </div>
        <span class="text-[11px] font-medium text-rose-100">History</span>
      </a>
    </div>
  </div>
</div>

<!-- SECTION 2: Four Statistics Cards (Real user data from MySQL) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
  <!-- Card 1: Total Orders -->
  <div class="bg-white p-5 rounded-2xl border border-[#FCE4E8] flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500">
        <i data-lucide="shopping-cart" class="w-6 h-6"></i>
      </div>
      <div>
        <div class="text-xs text-slate-400 font-medium">Total Orders</div>
        <div class="text-xl sm:text-2xl font-bold text-slate-800"><?= $totalOrders ?></div>
      </div>
    </div>
    <?php if ($ordersPctChange !== null && $totalOrders > 0): ?>
      <?php if ($ordersPctChange > 0): ?>
        <div class="text-xs font-bold text-emerald-500 flex items-center gap-0.5">
          <i data-lucide="arrow-up" class="w-3.5 h-3.5"></i> <?= $ordersPctChange ?>%
        </div>
      <?php elseif ($ordersPctChange < 0): ?>
        <div class="text-xs font-bold text-rose-500 flex items-center gap-0.5">
          <i data-lucide="arrow-down" class="w-3.5 h-3.5"></i> <?= abs($ordersPctChange) ?>%
        </div>
      <?php else: ?>
        <div class="text-xs font-bold text-slate-400 flex items-center gap-0.5">
          0%
        </div>
      <?php endif; ?>
    <?php else: ?>
      <span class="text-xs font-bold text-slate-300">—</span>
    <?php endif; ?>
  </div>

  <!-- Card 2: Completed -->
  <div class="bg-white p-5 rounded-2xl border border-[#FCE4E8] flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500">
        <i data-lucide="check-circle-2" class="w-6 h-6"></i>
      </div>
      <div>
        <div class="text-xs text-slate-400 font-medium">Completed</div>
        <div class="text-xl sm:text-2xl font-bold text-slate-800"><?= $completedOrders ?></div>
      </div>
    </div>
    <?php if ($completedPercentage !== null): ?>
      <div class="text-xs font-bold text-emerald-500 flex items-center gap-0.5" title="<?= $completedOrders ?> of <?= $totalOrders ?> completed">
        <i data-lucide="arrow-up" class="w-3.5 h-3.5"></i> <?= $completedPercentage ?>%
      </div>
    <?php else: ?>
      <span class="text-xs font-bold text-slate-300">—</span>
    <?php endif; ?>
  </div>

  <!-- Card 3: Pending -->
  <div class="bg-white p-5 rounded-2xl border border-[#FCE4E8] flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500">
        <i data-lucide="clock" class="w-6 h-6"></i>
      </div>
      <div>
        <div class="text-xs text-slate-400 font-medium">Pending</div>
        <div class="text-xl sm:text-2xl font-bold text-slate-800"><?= $pendingOrders ?></div>
      </div>
    </div>
    <?php if ($pendingPercentage !== null): ?>
      <div class="text-xs font-bold <?= $pendingPercentage > 0 ? 'text-amber-500' : 'text-slate-400' ?> flex items-center gap-0.5" title="<?= $pendingOrders ?> of <?= $totalOrders ?> pending">
        <?= $pendingPercentage ?>%
      </div>
    <?php else: ?>
      <span class="text-xs font-bold text-slate-300">—</span>
    <?php endif; ?>
  </div>

  <!-- Card 4: Total Spent -->
  <div class="bg-white p-5 rounded-2xl border border-[#FCE4E8] flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500">
        <i data-lucide="wallet" class="w-6 h-6"></i>
      </div>
      <div>
        <div class="text-xs text-slate-400 font-medium">Total Spent</div>
        <div class="text-xl sm:text-2xl font-bold text-slate-800"><?= format_price($totalSpent) ?></div>
      </div>
    </div>
    <?php if ($spendPctChange !== null && $totalSpent > 0): ?>
      <?php if ($spendPctChange > 0): ?>
        <div class="text-xs font-bold text-emerald-500 flex items-center gap-0.5">
          <i data-lucide="arrow-up" class="w-3.5 h-3.5"></i> <?= $spendPctChange ?>%
        </div>
      <?php elseif ($spendPctChange < 0): ?>
        <div class="text-xs font-bold text-rose-500 flex items-center gap-0.5">
          <i data-lucide="arrow-down" class="w-3.5 h-3.5"></i> <?= abs($spendPctChange) ?>%
        </div>
      <?php else: ?>
        <div class="text-xs font-bold text-slate-400 flex items-center gap-0.5">
          0%
        </div>
      <?php endif; ?>
    <?php else: ?>
      <span class="text-xs font-bold text-slate-300">—</span>
    <?php endif; ?>
  </div>
</div>

<!-- SECTION 3: Sales Overview, Quick Actions, and Recently Ordered (3 Columns) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
  <!-- 1. Sales Overview Chart (lg:col-span-5) -->
  <div class="lg:col-span-5 bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm flex flex-col justify-between">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="font-bold text-base text-slate-800">Sales Overview</h3>
        <span class="text-xs text-slate-400">Last 7 days • Real Volume: <strong class="text-slate-700"><?= format_price($totalPeriodSales) ?></strong></span>
      </div>
      <div class="text-xs font-semibold px-2.5 py-1 rounded-xl bg-rose-50 text-rose-600 border border-[#FCE4E8]">
        <?= $totalPeriodCount ?> <?= $totalPeriodCount === 1 ? 'Order' : 'Orders' ?>
      </div>
    </div>

    <?php if ($totalPeriodCount === 0 || $totalPeriodSales <= 0): ?>
      <!-- Appropriate Empty / Zero State based on actual database -->
      <div class="h-56 flex flex-col items-center justify-center text-center p-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-400 flex items-center justify-center mb-3">
          <i data-lucide="bar-chart-2" class="w-6 h-6"></i>
        </div>
        <div class="font-bold text-sm text-slate-700">No Sales in Last 7 Days</div>
        <p class="text-xs text-slate-400 mt-1 max-w-xs">Zero orders recorded in this period. Placed orders will automatically populate your real sales metrics here.</p>
      </div>
    <?php else: ?>
      <?php
        $dayList = array_values($salesPeriodDays);
        $chartMax = $maxDayAmount > 0 ? ceil($maxDayAmount * 1.25 * 10) / 10 : 10;
        $chartPoints = [];
        $svgCoords = [];
        $peakCoord = null;
        $totalDays = count($dayList);
        foreach ($dayList as $idx => $dItem) {
            $cx = 40 + ($idx * (390 / max(1, $totalDays - 1)));
            $cy = 140 - ($chartMax > 0 ? ($dItem['amount'] / $chartMax) * 110 : 0);
            $chartPoints[] = ['x' => $cx, 'y' => $cy, 'item' => $dItem];
            $svgCoords[] = "$cx,$cy";
            if ($peakDay && $dItem['date'] === $peakDay['date']) {
                $peakCoord = ['x' => $cx, 'y' => $cy, 'item' => $dItem];
            }
        }
        if (!$peakCoord && !empty($chartPoints)) {
            $peakCoord = end($chartPoints);
        }
        $areaPath = "M " . $chartPoints[0]['x'] . ",140 L " . implode(' L ', $svgCoords) . " L " . end($chartPoints)['x'] . ",140 Z";
        $linePath = "M " . implode(' L ', $svgCoords);
      ?>
      <div class="relative w-full h-56 pt-6">
        <!-- Peak indicator tooltip with real data -->
        <?php if ($peakCoord && $peakCoord['item']['amount'] > 0): ?>
          <div class="absolute top-1 bg-white border border-rose-200 px-2.5 py-1 rounded-full shadow-md text-[11px] font-bold text-slate-800 flex items-center gap-1 z-10 -translate-x-1/2" style="left: <?= round(($peakCoord['x'] / 450) * 100) ?>%;">
            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
            <span><?= e($peakCoord['item']['label']) ?></span>
            <span class="text-rose-600"><?= format_price($peakCoord['item']['amount']) ?></span>
          </div>
        <?php endif; ?>

        <svg class="w-full h-full overflow-visible" viewBox="0 0 450 180" preserveAspectRatio="none">
          <defs>
            <linearGradient id="realRoseGradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stop-color="#FF3B69" stop-opacity="0.35" />
              <stop offset="100%" stop-color="#FF3B69" stop-opacity="0.0" />
            </linearGradient>
          </defs>

          <!-- Y Axis Grid lines with real scales -->
          <line x1="30" y1="30" x2="440" y2="30" stroke="#F1F5F9" stroke-dasharray="3,3" />
          <text x="5" y="34" fill="#94A3B8" font-size="10"><?= format_price($chartMax) ?></text>

          <line x1="30" y1="67" x2="440" y2="67" stroke="#F1F5F9" stroke-dasharray="3,3" />
          <text x="5" y="71" fill="#94A3B8" font-size="10"><?= format_price($chartMax * 0.66) ?></text>

          <line x1="30" y1="103" x2="440" y2="103" stroke="#F1F5F9" stroke-dasharray="3,3" />
          <text x="5" y="107" fill="#94A3B8" font-size="10"><?= format_price($chartMax * 0.33) ?></text>

          <line x1="30" y1="140" x2="440" y2="140" stroke="#F1F5F9" stroke-dasharray="3,3" />
          <text x="15" y="144" fill="#94A3B8" font-size="10">0</text>

          <!-- Dynamic Area fill from real MySQL data -->
          <path d="<?= $areaPath ?>" fill="url(#realRoseGradient)" />

          <!-- Dynamic Line curve from real MySQL data -->
          <path d="<?= $linePath ?>" fill="none" stroke="#FF3B69" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

          <!-- Real Data Points -->
          <?php foreach ($chartPoints as $cp): ?>
            <circle cx="<?= $cp['x'] ?>" cy="<?= $cp['y'] ?>" r="<?= $cp['item']['amount'] > 0 ? '4' : '2.5' ?>" fill="<?= $cp['item']['amount'] > 0 ? '#FF3B69' : '#CBD5E1' ?>" stroke="#FFFFFF" stroke-width="1.5" />
          <?php endforeach; ?>

          <!-- Real X Axis labels -->
          <?php foreach ($chartPoints as $cp): ?>
            <text x="<?= $cp['x'] - 14 ?>" y="165" fill="#94A3B8" font-size="10"><?= e($cp['item']['label']) ?></text>
          <?php endforeach; ?>
        </svg>
      </div>
    <?php endif; ?>
  </div>

  <!-- 2. Quick Actions (lg:col-span-3) -->
  <div class="lg:col-span-3 bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm flex flex-col justify-between">
    <h3 class="font-bold text-base text-slate-800 mb-4">Quick Actions</h3>

    <div class="grid grid-cols-3 gap-3">
      <!-- 1. New Order -->
      <a href="/order" class="flex flex-col items-center justify-center p-3 rounded-2xl border border-[#FCE4E8] hover:bg-rose-50/50 hover:border-rose-300 transition-all text-center group">
        <div class="w-11 h-11 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-2 shadow-sm group-hover:scale-105 transition-transform">
          <i data-lucide="plus" class="w-5 h-5"></i>
        </div>
        <span class="text-xs font-semibold text-slate-700">New Order</span>
      </a>

      <!-- 2. Add Funds -->
      <a href="/add-funds" class="flex flex-col items-center justify-center p-3 rounded-2xl border border-[#FCE4E8] hover:bg-rose-50/50 hover:border-rose-300 transition-all text-center group">
        <div class="w-11 h-11 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-2 shadow-sm group-hover:scale-105 transition-transform">
          <i data-lucide="wallet" class="w-5 h-5"></i>
        </div>
        <span class="text-xs font-semibold text-slate-700">Add Funds</span>
      </a>

      <!-- 3. Support -->
      <a href="/support" class="flex flex-col items-center justify-center p-3 rounded-2xl border border-[#FCE4E8] hover:bg-rose-50/50 hover:border-rose-300 transition-all text-center group">
        <div class="w-11 h-11 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-2 shadow-sm group-hover:scale-105 transition-transform">
          <i data-lucide="headphones" class="w-5 h-5"></i>
        </div>
        <span class="text-xs font-semibold text-slate-700">Support</span>
      </a>

      <!-- 4. Referral -->
      <a href="/profile" class="flex flex-col items-center justify-center p-3 rounded-2xl border border-[#FCE4E8] hover:bg-rose-50/50 hover:border-rose-300 transition-all text-center group">
        <div class="w-11 h-11 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-2 shadow-sm group-hover:scale-105 transition-transform">
          <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <span class="text-xs font-semibold text-slate-700">Referral</span>
      </a>

      <!-- 5. API -->
      <a href="/profile#api" class="flex flex-col items-center justify-center p-3 rounded-2xl border border-[#FCE4E8] hover:bg-rose-50/50 hover:border-rose-300 transition-all text-center group">
        <div class="w-11 h-11 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-2 shadow-sm group-hover:scale-105 transition-transform">
          <i data-lucide="code" class="w-5 h-5"></i>
        </div>
        <span class="text-xs font-semibold text-slate-700">API</span>
      </a>

      <!-- 6. Services -->
      <a href="/services" class="flex flex-col items-center justify-center p-3 rounded-2xl border border-[#FCE4E8] hover:bg-rose-50/50 hover:border-rose-300 transition-all text-center group">
        <div class="w-11 h-11 rounded-2xl bg-rose-500 text-white flex items-center justify-center mb-2 shadow-sm group-hover:scale-105 transition-transform">
          <i data-lucide="grid" class="w-5 h-5"></i>
        </div>
        <span class="text-xs font-semibold text-slate-700">Services</span>
      </a>
    </div>
  </div>

  <!-- 3. Recently Ordered Card-style List (lg:col-span-4) - NO TABLE UI! -->
  <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm flex flex-col justify-between">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold text-base text-slate-800">Recently Ordered</h3>
      <a href="/orders" class="text-xs font-bold text-rose-500 hover:text-rose-600 transition-colors">View All</a>
    </div>

    <!-- Responsive Card-Style List matching screenshot -->
    <div class="space-y-2.5">
      <?php if (empty($recentOrders)): ?>
        <div class="text-xs text-slate-400 py-4 text-center">No recent orders yet.</div>
      <?php else: ?>
        <?php foreach ($recentOrders as $ro): ?>
          <a href="/orders" class="flex items-center justify-between p-2.5 rounded-2xl border border-slate-100 hover:border-rose-200 hover:bg-rose-50/20 transition-all group">
            <div class="flex items-center gap-3 overflow-hidden">
              <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 <?= $ro['category_slug'] === 'instagram' ? 'bg-rose-50 text-rose-500' : ($ro['category_slug'] === 'youtube' ? 'bg-red-50 text-red-500' : ($ro['category_slug'] === 'tiktok' ? 'bg-slate-900 text-white' : ($ro['category_slug'] === 'facebook' ? 'bg-blue-50 text-blue-600' : 'bg-sky-50 text-sky-500'))) ?>">
                <i data-lucide="<?= $ro['category_slug'] === 'youtube' ? 'youtube' : ($ro['category_slug'] === 'tiktok' ? 'music-2' : ($ro['category_slug'] === 'facebook' ? 'facebook' : ($ro['category_slug'] === 'twitter' ? 'twitter' : 'instagram'))) ?>" class="w-4 h-4"></i>
              </div>
              <div class="overflow-hidden">
                <div class="font-bold text-xs text-slate-800 truncate"><?= e($ro['service_name']) ?></div>
                <div class="text-[11px] text-slate-400 truncate"><?= e($ro['link']) ?></div>
              </div>
            </div>

            <div class="flex items-center gap-2.5 shrink-0 ml-2">
              <div class="text-right">
                <div class="text-xs font-bold text-slate-800">+<?= number_format($ro['quantity']) ?></div>
                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full <?= $ro['status'] === 'completed' ? 'bg-emerald-50 text-emerald-600' : ($ro['status'] === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600') ?>">
                  <?= ucfirst($ro['status']) ?>
                </span>
              </div>
              <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 group-hover:text-rose-500 transition-colors"></i>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- SECTION 4: Lower Area (Popular Services, Order Form, Special Offer & Support) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <!-- 1. Popular Services (lg:col-span-5) -->
  <div class="lg:col-span-5 bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-base text-slate-800">Popular Services</h3>
        <a href="/services" class="text-xs font-bold text-rose-500 hover:text-rose-600">View All</a>
      </div>

      <!-- Category Filter Tabs matching screenshot -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-2 mb-4 custom-scrollbar">
        <button type="button" onclick="filterPopularServices('all')" class="category-pill active shrink-0 px-3 py-1.5 rounded-full text-xs font-bold bg-rose-500 text-white transition-colors" data-category="all">
          All
        </button>
        <?php foreach ($categories as $cat): ?>
          <button type="button" onclick="filterPopularServices('<?= e($cat['slug']) ?>')" class="category-pill shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-50/50 text-slate-600 hover:bg-rose-100 hover:text-rose-600 transition-colors" data-category="<?= e($cat['slug']) ?>">
            <?= e($cat['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Popular Services Cards (Grid 2-column) matching screenshot -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="popular-services-grid">
        <?php foreach ($services as $srv): ?>
          <div class="service-card p-3.5 rounded-2xl border border-[#FCE4E8] bg-white hover:border-rose-300 transition-all flex flex-col justify-between relative group" data-category="<?= e($srv['category_slug']) ?>">
            <?php if (!empty($srv['badge'])): ?>
              <span class="absolute top-2.5 right-2.5 text-[9px] font-bold text-white bg-rose-500 px-2 py-0.5 rounded-full">
                <?= e($srv['badge']) ?>
              </span>
            <?php endif; ?>

            <div class="flex items-start gap-2.5 mb-3">
              <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0">
                <i data-lucide="<?= $srv['category_slug'] === 'youtube' ? 'youtube' : ($srv['category_slug'] === 'tiktok' ? 'music-2' : ($srv['category_slug'] === 'facebook' ? 'facebook' : ($srv['category_slug'] === 'twitter' ? 'twitter' : 'instagram'))) ?>" class="w-4 h-4"></i>
              </div>
              <div class="pr-12">
                <h4 class="font-bold text-xs text-slate-800 line-clamp-1"><?= e($srv['name']) ?></h4>
                <div class="text-[11px] text-slate-400 mt-0.5">Start from <span class="font-bold text-slate-700"><?= format_price($srv['rate']) ?></span></div>
              </div>
            </div>

            <button type="button" onclick="selectServiceForOrder(<?= $srv['id'] ?>, <?= $srv['category_id'] ?>, '<?= e(addslashes($srv['name'])) ?>', <?= $srv['rate'] ?>, <?= $srv['min_quantity'] ?>, <?= $srv['max_quantity'] ?>)" class="w-full py-1.5 px-3 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white font-bold text-xs transition-colors">
              Order Now
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 2. Real Working Order Form matching screenshot (lg:col-span-4) -->
  <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100">
          <i data-lucide="edit-3" class="w-5 h-5"></i>
        </div>
        <div>
          <h3 class="font-bold text-base text-slate-800">Order Form</h3>
          <p class="text-xs text-slate-400">Select a service, enter the link and place your order.</p>
        </div>
      </div>

      <!-- Real Order Form submitting via AJAX to /api/order/create -->
      <form id="dashboard-order-form" onsubmit="submitOrder(event)" class="space-y-3.5">
        <!-- Category Dropdown -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Select Category</label>
          <div class="relative">
            <select id="order-category" onchange="onCategoryChange(this.value)" class="w-full px-3.5 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-400">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Service Dropdown -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Select Service</label>
          <div class="relative">
            <select id="order-service" onchange="onServiceChange(this)" class="w-full px-3.5 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-400">
              <?php foreach ($services as $s): ?>
                <?php 
                  $srvBaseCurr = $s['currency'] ?? 'USD';
                  $convertedRate = convert_price($s['rate'], $srvBaseCurr, $userCurrency);
                  $formattedRate = format_price($s['rate'], $userCurrency, $srvBaseCurr);
                ?>
                <option value="<?= $s['id'] ?>" data-category="<?= $s['category_id'] ?>" data-rate="<?= $s['rate'] ?>" data-base-currency="<?= e($srvBaseCurr) ?>" data-converted-rate="<?= $convertedRate ?>" data-min="<?= $s['min_quantity'] ?>" data-max="<?= $s['max_quantity'] ?>">
                  <?= e($s['name']) ?> - <?= $formattedRate ?> per 1K
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Target Link -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Target Link</label>
          <div class="relative">
            <i data-lucide="link-2" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input 
              type="text" 
              id="order-link" 
              required
              placeholder="Enter Instagram username or profile link" 
              class="w-full pl-10 pr-3.5 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-400"
            >
          </div>
        </div>

        <!-- Quantity & Dynamic Price Calculation matching screenshot -->
        <div>
          <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
            <span class="font-semibold text-slate-600">Quantity</span>
            <span id="order-limits-text" class="text-[11px] text-slate-400">Min: 100 - Max: 100000</span>
          </div>
          <input 
            type="number" 
            id="order-quantity" 
            value="1000" 
            min="100" 
            max="100000" 
            required
            oninput="calculateEstimatedCost()" 
            class="w-full px-3.5 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-400"
          >
          <div class="text-right text-xs font-semibold text-slate-500 mt-1">
            Estimated Cost: <span id="estimated-cost-display" class="font-bold text-rose-600">--</span>
          </div>
        </div>

        <!-- Create Order Button matching screenshot -->
        <button 
          type="submit" 
          id="order-submit-btn"
          class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-xs sm:text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2"
        >
          <span>Create Order</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- 3. Promo Cards (Special Offer & Live Support) (lg:col-span-3) -->
  <div class="lg:col-span-3 space-y-6 flex flex-col justify-between">
    <!-- Special Offer Card matching screenshot -->
    <div class="p-6 rounded-3xl bg-gradient-to-br from-[#FFE3E8] to-[#FFD1DA] border border-[#FCD3DC] shadow-sm flex flex-col justify-between relative overflow-hidden">
      <div>
        <div class="flex items-center justify-between mb-3">
          <div class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center shadow-sm">
            <i data-lucide="gift" class="w-4 h-4"></i>
          </div>
          <div class="w-12 h-12 text-rose-300 opacity-60">
            <svg class="w-full h-full fill-current" viewBox="0 0 24 24"><path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.65-.5-.65C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76V14h2V8.76L15.38 12 17 10.83 14.92 8H20v6z"/></svg>
          </div>
        </div>

        <span class="text-xs uppercase font-bold text-rose-600 block mb-1">Special Offer</span>
        <h4 class="text-lg font-extrabold text-slate-800 mb-1">Get 10% Bonus</h4>
        <p class="text-xs text-slate-600 mb-4 leading-relaxed">
          Add funds now and get 10% bonus on every deposit automatically credited to your wallet.
        </p>
      </div>

      <a href="/add-funds" class="block w-full py-2.5 px-4 text-center rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors">
        Add Funds
      </a>
    </div>

    <!-- Live Support Card matching screenshot -->
    <div class="p-6 rounded-3xl bg-white border border-[#FCE4E8] shadow-sm flex flex-col justify-between">
      <div>
        <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mb-3">
          <i data-lucide="headphones" class="w-5 h-5"></i>
        </div>
        <h4 class="font-bold text-base text-slate-800 mb-1">Live Support</h4>
        <p class="text-xs text-slate-500 mb-4 leading-relaxed">
          Our support team is always here to help you with orders, services and custom requests.
        </p>
      </div>

      <a href="/support" class="block w-full py-2.5 px-4 text-center rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors">
        Open Ticket
      </a>
    </div>
  </div>
</div>

<!-- Interactive JavaScript for Dashboard -->
<script>
  // Filter popular services by category pill
  function filterPopularServices(categorySlug) {
    document.querySelectorAll('.category-pill').forEach(btn => {
      if (btn.getAttribute('data-category') === categorySlug) {
        btn.classList.add('bg-rose-500', 'text-white');
        btn.classList.remove('bg-rose-50/50', 'text-slate-600');
      } else {
        btn.classList.remove('bg-rose-500', 'text-white');
        btn.classList.add('bg-rose-50/50', 'text-slate-600');
      }
    });

    document.querySelectorAll('.service-card').forEach(card => {
      if (categorySlug === 'all' || card.getAttribute('data-category') === categorySlug) {
        card.classList.remove('hidden');
      } else {
        card.classList.add('hidden');
      }
    });
  }

  // Pre-fill order form when clicking "Order Now" on a service card
  function selectServiceForOrder(serviceId, categoryId, serviceName, rate, min, max) {
    const catSelect = document.getElementById('order-category');
    const srvSelect = document.getElementById('order-service');
    const qtyInput = document.getElementById('order-quantity');

    if (catSelect) catSelect.value = categoryId;
    onCategoryChange(categoryId);

    if (srvSelect) srvSelect.value = serviceId;
    if (qtyInput) {
      qtyInput.min = min;
      qtyInput.max = max;
      qtyInput.value = min;
    }
    document.getElementById('order-limits-text').textContent = `Min: ${min} - Max: ${max}`;
    calculateEstimatedCost();

    // Scroll to order form smoothly
    document.getElementById('dashboard-order-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // Filter service dropdown based on selected category
  function onCategoryChange(categoryId) {
    const srvSelect = document.getElementById('order-service');
    if (!srvSelect) return;
    let firstValid = null;

    Array.from(srvSelect.options).forEach(opt => {
      const optCat = opt.getAttribute('data-category');
      if (!categoryId || optCat == categoryId) {
        opt.style.display = '';
        if (!firstValid) firstValid = opt.value;
      } else {
        opt.style.display = 'none';
      }
    });

    if (firstValid) {
      srvSelect.value = firstValid;
      onServiceChange(srvSelect);
    }
  }

  // On service change update limits & cost
  function onServiceChange(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (!selectedOption) return;
    const min = selectedOption.getAttribute('data-min') || 100;
    const max = selectedOption.getAttribute('data-max') || 100000;
    const qtyInput = document.getElementById('order-quantity');
    if (qtyInput) {
      qtyInput.min = min;
      qtyInput.max = max;
      if (parseInt(qtyInput.value) < parseInt(min)) qtyInput.value = min;
    }
    document.getElementById('order-limits-text').textContent = `Min: ${min} - Max: ${max}`;
    calculateEstimatedCost();
  }

  // Calculate dynamic price based on quantity and service rate
  const dashboardCurrencySymbol = "<?= addslashes(get_currency_info($userCurrency)['symbol'] ?? '$') ?>";

  function calculateEstimatedCost() {
    const srvSelect = document.getElementById('order-service');
    const qtyInput = document.getElementById('order-quantity');
    const display = document.getElementById('estimated-cost-display');
    if (!srvSelect || !qtyInput || !display) return;

    const opt = srvSelect.options[srvSelect.selectedIndex];
    if (!opt) return;

    const convertedRate = parseFloat(opt.getAttribute('data-converted-rate')) || 0;
    const qty = parseInt(qtyInput.value) || 0;
    const cost = (convertedRate / 1000) * qty;

    display.textContent = dashboardCurrencySymbol + cost.toFixed(2);
  }

  // Submit order to real MySQL database via AJAX
  function submitOrder(e) {
    e.preventDefault();
    const btn = document.getElementById('order-submit-btn');
    const serviceId = document.getElementById('order-service').value;
    const link = document.getElementById('order-link').value.trim();
    const quantity = parseInt(document.getElementById('order-quantity').value);

    if (!link) {
      alert('Please enter a target link.');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Processing...';

    fetch('/api/order/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        service_id: serviceId,
        link: link,
        quantity: quantity
      })
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<span>Create Order</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
      if (window.lucide) lucide.createIcons();

      const alertBox = document.getElementById('dashboard-alert');
      const alertText = document.getElementById('dashboard-alert-text');

      if (data.success) {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-sm flex items-center justify-between';
        alertText.textContent = data.message || 'Order placed successfully!';
        alertBox.classList.remove('hidden');

        // Update balance displays
        if (data.new_balance) {
          const balDisp = document.getElementById('user-balance-display');
          if (balDisp) balDisp.textContent = data.new_balance;
        }

        // Reset form link
        document.getElementById('order-link').value = '';

        // Reload recently ordered section or page after 1.5s
        setTimeout(() => window.location.reload(), 1500);
      } else {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-rose-50 border-rose-200 text-rose-800 text-sm flex items-center justify-between';
        alertText.textContent = data.error || 'Failed to place order.';
        alertBox.classList.remove('hidden');
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<span>Create Order</span>';
      alert('Network or server error while placing order.');
    });
  }

  // Hero Banner Slider (Transitions Image + Text together)
  function initHeroSlider() {
    const wrapper = document.getElementById('hero-slider-wrapper');
    if (!wrapper) return;

    const slides = wrapper.querySelectorAll('.hero-slide');
    if (slides.length <= 1) return;

    const dots = wrapper.querySelectorAll('.hero-dot');
    const prevBtn = document.getElementById('hero-slider-prev');
    const nextBtn = document.getElementById('hero-slider-next');

    let currentSlide = 0;
    let autoSlideInterval = null;

    function goToSlide(newIndex) {
      if (newIndex < 0) newIndex = slides.length - 1;
      if (newIndex >= slides.length) newIndex = 0;

      slides.forEach((slide, idx) => {
        if (idx === newIndex) {
          slide.classList.remove('opacity-0', 'pointer-events-none', 'z-0', 'translate-x-8', '-translate-x-8');
          slide.classList.add('opacity-100', 'z-10', 'translate-x-0');
        } else {
          slide.classList.remove('opacity-100', 'z-10', 'translate-x-0');
          slide.classList.add('opacity-0', 'pointer-events-none', 'z-0');
          if (idx < newIndex) {
            slide.classList.add('-translate-x-8');
            slide.classList.remove('translate-x-8');
          } else {
            slide.classList.add('translate-x-8');
            slide.classList.remove('-translate-x-8');
          }
        }
      });

      dots.forEach((dot, idx) => {
        if (idx === newIndex) {
          dot.classList.remove('w-2', 'bg-rose-300/80');
          dot.classList.add('w-6', 'bg-rose-500');
        } else {
          dot.classList.remove('w-6', 'bg-rose-500');
          dot.classList.add('w-2', 'bg-rose-300/80');
        }
      });

      currentSlide = newIndex;
      if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
      }
    }

    function startAutoSlide() {
      stopAutoSlide();
      autoSlideInterval = setInterval(() => {
        goToSlide(currentSlide + 1);
      }, 5000);
    }

    function stopAutoSlide() {
      if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
        autoSlideInterval = null;
      }
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', (e) => {
        e.preventDefault();
        goToSlide(currentSlide - 1);
        startAutoSlide();
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', (e) => {
        e.preventDefault();
        goToSlide(currentSlide + 1);
        startAutoSlide();
      });
    }

    dots.forEach((dot, idx) => {
      dot.addEventListener('click', (e) => {
        e.preventDefault();
        goToSlide(idx);
        startAutoSlide();
      });
    });

    wrapper.addEventListener('mouseenter', stopAutoSlide);
    wrapper.addEventListener('mouseleave', startAutoSlide);

    // Touch swipe support
    let startX = 0;
    wrapper.addEventListener('touchstart', (e) => {
      startX = e.changedTouches[0].screenX;
    }, { passive: true });

    wrapper.addEventListener('touchend', (e) => {
      const endX = e.changedTouches[0].screenX;
      if (startX - endX > 40) {
        goToSlide(currentSlide + 1);
        startAutoSlide();
      } else if (endX - startX > 40) {
        goToSlide(currentSlide - 1);
        startAutoSlide();
      }
    }, { passive: true });

    startAutoSlide();
  }

  // Initial calculation on load
  document.addEventListener('DOMContentLoaded', () => {
    calculateEstimatedCost();
    initHeroSlider();
  });
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
