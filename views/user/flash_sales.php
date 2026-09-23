<?php
$pageTitle = 'Flash Sales & Limited Deals - RoseSMM';
$activePage = 'flash-sales';
require_once __DIR__ . '/../layouts/user_header.php';
require_once __DIR__ . '/../../includes/FlashSaleHelper.php';

$db = getDB();
$userCurrency = get_user_currency();
$activeSales = FlashSaleHelper::getActiveFlashSales();

// Fetch services that fall under active flash sales
$discountedServices = [];
if (!empty($activeSales)) {
    $services = $db->query("SELECT s.*, c.name AS category_name FROM services s LEFT JOIN categories c ON s.category_id = c.id WHERE s.status = 'active' ORDER BY s.category_id ASC, s.id ASC")->fetchAll();
    foreach ($services as $srv) {
        $srvBaseCurr = $srv['currency'] ?? 'USD';
        $rateUSD = convert_price($srv['rate'], $srvBaseCurr, 'USD');
        $saleInfo = FlashSaleHelper::getDiscountForService($srv['id'], $srv['category_id'], $rateUSD);
        if ($saleInfo['has_sale']) {
            $srv['sale_info'] = $saleInfo;
            $discountedServices[] = $srv;
        }
    }
}
?>

<div class="space-y-8 max-w-6xl mx-auto">
  <!-- Hero Banner -->
  <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-rose-600 via-pink-600 to-rose-700 text-white p-8 sm:p-10 shadow-lg border border-rose-500">
    <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
    <div class="relative z-10 max-w-2xl space-y-3">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-white text-xs font-black uppercase tracking-wider">
        <i data-lucide="zap" class="w-3.5 h-3.5 text-yellow-300 fill-yellow-300"></i>
        Exclusive Limited Deals
      </div>
      <h1 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight">
        Flash Sales & Instant Boost Disounts
      </h1>
      <p class="text-rose-100 text-xs sm:text-sm font-medium">
        Take advantage of time-limited price drops across select premium services before the countdown ends.
      </p>
    </div>
  </div>

  <!-- Active Sales Cards -->
  <?php if (empty($activeSales)): ?>
    <div class="bg-white rounded-3xl border border-[#FCE4E8] p-12 text-center shadow-sm">
      <div class="w-16 h-16 rounded-3xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center border border-rose-100 mb-4">
        <i data-lucide="clock" class="w-8 h-8"></i>
      </div>
      <h3 class="text-base font-black text-slate-800 mb-1">No Active Flash Sales Right Now</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto mb-5">
        Check back soon! Our team regularly launches high-voltage flash sales for top engagement services.
      </p>
      <a href="/services" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition-colors">
        Browse Standard Catalog
      </a>
    </div>
  <?php else: ?>
    <?php foreach ($activeSales as $sale): ?>
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
          <div>
            <div class="flex items-center gap-2.5 mb-1.5">
              <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-rose-500 text-white uppercase tracking-wider">
                <?= e($sale['badge_text'] ?: 'FLASH SALE') ?>
              </span>
              <h2 class="text-lg font-black text-slate-800"><?= e($sale['title']) ?></h2>
            </div>
            <p class="text-xs text-slate-500"><?= e($sale['description']) ?></p>
          </div>

          <!-- Live Countdown Timer -->
          <div class="flex items-center gap-2 bg-rose-50/70 border border-rose-200/60 px-4 py-2.5 rounded-2xl shrink-0">
            <i data-lucide="timer" class="w-5 h-5 text-rose-600 shrink-0"></i>
            <div class="text-xs font-bold text-slate-700">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Ends In:</span>
              <span class="countdown-timer font-mono font-black text-rose-600 text-sm" data-ends="<?= strtotime($sale['ends_at']) ?>">
                Loading...
              </span>
            </div>
          </div>
        </div>

        <!-- Matching Discounted Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach ($discountedServices as $srv): ?>
            <div class="p-4 rounded-2xl border border-slate-100 hover:border-rose-300 hover:shadow-md transition-all bg-gradient-to-b from-white to-rose-50/20 flex flex-col justify-between">
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                    <?= e($srv['category_name'] ?? 'General') ?>
                  </span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700">
                    SAVE <?= $srv['sale_info']['savings_percent'] ?>%
                  </span>
                </div>
                <h4 class="text-xs font-bold text-slate-800 leading-snug line-clamp-2">
                  <?= e($srv['name']) ?>
                </h4>
              </div>

              <div class="pt-4 mt-3 border-t border-slate-100 flex items-center justify-between">
                <div>
                  <span class="text-[10px] text-slate-400 line-through block">
                    <?= format_price($srv['sale_info']['original_rate'], $userCurrency, 'USD') ?>
                  </span>
                  <span class="text-sm font-black text-rose-600">
                    <?= format_price($srv['sale_info']['rate'], $userCurrency, 'USD') ?>
                    <span class="text-[10px] font-normal text-slate-400">/ 1k</span>
                  </span>
                </div>
                <a 
                  href="/order?service_id=<?= $srv['id'] ?>" 
                  class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors inline-flex items-center gap-1.5"
                >
                  <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Order
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  function updateTimers() {
    const now = Math.floor(Date.now() / 1000);
    document.querySelectorAll('.countdown-timer').forEach(el => {
      const endsAt = parseInt(el.dataset.ends, 10);
      let diff = endsAt - now;
      if (diff <= 0) {
        el.textContent = 'EXPIRED';
        return;
      }
      const days = Math.floor(diff / 86400);
      diff %= 86400;
      const hours = Math.floor(diff / 3600);
      diff %= 3600;
      const minutes = Math.floor(diff / 60);
      const seconds = diff % 60;

      let str = '';
      if (days > 0) str += days + 'd ';
      str += (hours < 10 ? '0' + hours : hours) + 'h : ';
      str += (minutes < 10 ? '0' + minutes : minutes) + 'm : ';
      str += (seconds < 10 ? '0' + seconds : seconds) + 's';
      el.textContent = str;
    });
  }

  updateTimers();
  setInterval(updateTimers, 1000);
});
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
