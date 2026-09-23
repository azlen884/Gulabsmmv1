<?php
$pageTitle = 'New Order - RoseSMM';
$activePage = 'order';
require_once __DIR__ . '/../layouts/user_header.php';
require_once __DIR__ . '/../../includes/FlashSaleHelper.php';

$db = getDB();
$userCurrency = get_user_currency();
$activeSales = FlashSaleHelper::getActiveFlashSales();

$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();
$services = $db->query("
    SELECT s.*, c.name AS category_name, c.slug AS category_slug 
    FROM services s 
    JOIN categories c ON s.category_id = c.id 
    WHERE s.status = 'active' 
    ORDER BY c.sort_order ASC, s.sort_order ASC
")->fetchAll();

$preselectedServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : (isset($_GET['service']) ? (int)$_GET['service'] : 0);
?>

<div class="max-w-4xl mx-auto space-y-6">
  <!-- Active Flash Sale Banner (if live) -->
  <?php if (!empty($activeSales)): ?>
    <?php $topSale = $activeSales[0]; ?>
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-600 via-pink-600 to-rose-700 p-4 text-white shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0">
          <i data-lucide="zap" class="w-5 h-5 text-yellow-300 fill-yellow-300"></i>
        </div>
        <div>
          <div class="text-xs font-black uppercase tracking-wider text-rose-200"><?= e($topSale['badge_text'] ?: 'FLASH SALE') ?> ACTIVE</div>
          <div class="text-xs sm:text-sm font-bold"><?= e($topSale['title']) ?></div>
        </div>
      </div>
      <a href="/flash-sales" class="shrink-0 px-3.5 py-1.5 rounded-xl bg-white text-rose-600 hover:bg-rose-50 text-xs font-black shadow-sm transition-colors text-center">
        View Deals & Countdown →
      </a>
    </div>
  <?php endif; ?>

  <div class="flex items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight">Place a New Order</h1>
      <p class="text-xs text-slate-500 mt-1">Select a service, customize delivery options, and submit your order.</p>
    </div>
    <div class="px-3.5 py-1.5 rounded-2xl border border-[#FCE4E8] bg-white text-xs font-bold text-slate-700 shadow-sm">
      Balance: <span class="text-rose-600 font-black" id="order-page-balance"><?= format_price($user['balance']) ?></span>
    </div>
  </div>

  <!-- Alert Box -->
  <div id="new-order-alert" class="hidden p-4 rounded-2xl border text-xs font-semibold flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2.5">
      <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
      <span id="new-order-alert-text"></span>
    </div>
    <button type="button" onclick="document.getElementById('new-order-alert').classList.add('hidden')">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Order Form Card (2 Cols) -->
    <div class="lg:col-span-2 bg-white p-6 sm:p-7 rounded-3xl border border-[#FCE4E8] shadow-sm">
      <form id="standalone-order-form" onsubmit="handleNewOrderSubmit(event)" class="space-y-4">
        <!-- Category Selection -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Category</label>
          <select id="main-order-category" onchange="filterServicesByCategory(this.value)" class="w-full px-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-50">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Service Selection -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Service</label>
          <select id="main-order-service" onchange="updateServiceDetails(this)" class="w-full px-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-50">
            <?php foreach ($services as $srv): ?>
              <?php 
                $srvBaseCurr = $srv['currency'] ?? 'USD';
                $rateInUSD = convert_price($srv['rate'], $srvBaseCurr, 'USD');
                $saleCheck = FlashSaleHelper::getDiscountForService($srv['id'], $srv['category_id'], $rateInUSD);
                $effectiveRateUSD = $saleCheck['has_sale'] ? $saleCheck['rate'] : $rateInUSD;
                $convertedRate = convert_price($effectiveRateUSD, 'USD', $userCurrency);
                $formattedRate = format_price($effectiveRateUSD, $userCurrency, 'USD');
              ?>
              <option 
                value="<?= $srv['id'] ?>" 
                data-category="<?= $srv['category_id'] ?>" 
                data-rate="<?= $effectiveRateUSD ?>" 
                data-original-rate="<?= $rateInUSD ?>"
                data-has-sale="<?= $saleCheck['has_sale'] ? '1' : '0' ?>"
                data-sale-percent="<?= $saleCheck['savings_percent'] ?>"
                data-base-currency="USD"
                data-converted-rate="<?= $convertedRate ?>"
                data-min="<?= $srv['min_quantity'] ?>" 
                data-max="<?= $srv['max_quantity'] ?>"
                data-refill="<?= !empty($srv['refill_enabled']) ? '1' : '0' ?>"
                data-refill-days="<?= (int)($srv['refill_days'] ?? 30) ?>"
                data-dripfeed="<?= !empty($srv['dripfeed']) ? '1' : '0' ?>"
                data-desc="<?= e(htmlspecialchars($srv['description'] ?: '')) ?>"
                <?= $preselectedServiceId === (int)$srv['id'] ? 'selected' : '' ?>
              >
                #<?= $srv['id'] ?> - <?= e($srv['name']) ?> (<?= $formattedRate ?> / 1K)<?= $saleCheck['has_sale'] ? " [⚡ {$saleCheck['savings_percent']}% OFF]" : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Service Badges (Refill & Flash Sale) -->
        <div class="flex flex-wrap items-center gap-2" id="service-feature-badges">
          <span id="refill-badge" class="hidden px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 items-center gap-1.5">
            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
            <span id="refill-badge-text">30-Day Auto Refill Warranty</span>
          </span>
          <span id="sale-badge" class="hidden px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200 items-center gap-1.5">
            <i data-lucide="zap" class="w-3.5 h-3.5 text-rose-600"></i>
            <span id="sale-badge-text">⚡ Flash Sale Price Active</span>
          </span>
          <span id="drip-badge" class="hidden px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200 items-center gap-1.5">
            <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
            <span>Drip-Feed Supported</span>
          </span>
        </div>

        <!-- Description Box -->
        <div id="service-desc-box" class="p-3.5 rounded-2xl bg-rose-50/40 border border-[#FCE4E8] text-xs text-slate-600 leading-relaxed">
          High quality, automated instant delivery. 100% safe profile boost.
        </div>

        <!-- Link -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Link / Target URL</label>
          <div class="relative">
            <i data-lucide="link" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
            <input 
              type="text" 
              id="main-order-link" 
              required 
              placeholder="https://instagram.com/username or post link" 
              class="w-full pl-11 pr-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs font-medium text-slate-700 focus:outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-50"
            >
          </div>
        </div>

        <!-- Quantity -->
        <div>
          <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
            <span class="font-bold text-slate-700 uppercase tracking-wider">Quantity</span>
            <span id="main-order-limits" class="text-slate-400 font-semibold">Min: 100 - Max: 100,000</span>
          </div>
          <input 
            type="number" 
            id="main-order-quantity" 
            value="1000" 
            required
            oninput="recalculateMainOrderCost()" 
            class="w-full px-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs font-bold text-slate-700 focus:outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-50"
          >
        </div>

        <!-- Drip-Feed Section (Optional Toggle) -->
        <div id="dripfeed-wrapper" class="hidden p-4 rounded-2xl border border-blue-100 bg-blue-50/30 space-y-3">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" id="dripfeed-toggle" onchange="toggleDripfeedInputs(this.checked)" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500">
            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
              <i data-lucide="repeat" class="w-3.5 h-3.5 text-blue-600"></i> Enable Drip-Feed Delivery
            </span>
          </label>

          <div id="dripfeed-fields" class="hidden grid grid-cols-2 gap-3 pt-2">
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Runs (Times to repeat)</label>
              <input 
                type="number" 
                id="drip-runs" 
                value="2" 
                min="2" 
                max="100" 
                oninput="recalculateMainOrderCost()"
                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:border-rose-400"
              >
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Interval (Minutes)</label>
              <input 
                type="number" 
                id="drip-interval" 
                value="60" 
                min="1" 
                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:border-rose-400"
              >
            </div>
            <div class="col-span-2 text-[11px] text-blue-700 font-semibold" id="drip-summary-text">
              Total Quantity: 2,000 units delivered over 2 runs.
            </div>
          </div>
        </div>

        <!-- Coupon Code Box -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Coupon / Promo Code</label>
          <div class="flex items-center gap-2">
            <div class="relative flex-1">
              <i data-lucide="tag" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
              <input 
                type="text" 
                id="order-coupon-code" 
                placeholder="e.g. WELCOME10 or ROSE2OFF" 
                class="w-full pl-11 pr-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs font-bold text-slate-700 uppercase placeholder:normal-case focus:outline-none focus:border-rose-400"
              >
            </div>
            <button 
              type="button" 
              id="apply-coupon-btn" 
              onclick="validateAndApplyCoupon()" 
              class="px-4 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition-colors shrink-0 cursor-pointer"
            >
              Apply
            </button>
          </div>
          <div id="coupon-feedback" class="hidden text-[11px] mt-1.5 font-bold px-1"></div>
        </div>

        <!-- Total Charge Display -->
        <div class="p-4 rounded-2xl bg-gradient-to-r from-rose-50 to-pink-50 border border-rose-100 flex items-center justify-between">
          <div>
            <div class="text-xs text-slate-500 font-bold uppercase tracking-wider">Total Charge</div>
            <div class="flex items-baseline gap-2">
              <span class="text-2xl font-black text-rose-600" id="main-order-total-cost">--</span>
              <span id="strikethrough-original" class="hidden text-xs text-slate-400 line-through font-bold"></span>
            </div>
          </div>
          <div class="text-right text-xs text-slate-400 font-semibold" id="charge-subtext">
            Deducted from wallet balance
          </div>
        </div>

        <!-- Submit Button -->
        <button 
          type="submit" 
          id="main-order-submit-btn" 
          class="w-full py-3.5 px-6 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
        >
          <span>Submit Order</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </form>
    </div>

    <!-- Instructions & FAQ Side Panel (1 Col) -->
    <div class="space-y-6">
      <!-- Quick Features Info -->
      <div class="bg-white p-5 rounded-3xl border border-[#FCE4E8] shadow-sm space-y-3">
        <h3 class="font-bold text-xs text-slate-800 uppercase tracking-wider flex items-center gap-2">
          <i data-lucide="sparkles" class="w-4 h-4 text-rose-500"></i>
          <span>Built-In Protections</span>
        </h3>
        <div class="space-y-2 text-xs text-slate-600">
          <div class="flex items-start gap-2.5">
            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
            <span><strong>Auto-Refill:</strong> Covered orders can be refilled with 1 click from your Order History.</span>
          </div>
          <div class="flex items-start gap-2.5">
            <i data-lucide="wallet" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5"></i>
            <span><strong>Instant Refund:</strong> Canceled or partial orders automatically credit your wallet balance.</span>
          </div>
          <div class="flex items-start gap-2.5">
            <i data-lucide="repeat" class="w-4 h-4 text-purple-500 shrink-0 mt-0.5"></i>
            <span><strong>Drip-Feed:</strong> Split big orders over hours or days to mirror natural growth.</span>
          </div>
        </div>
      </div>

      <!-- Mass Order Prompt -->
      <div class="p-5 rounded-3xl bg-gradient-to-br from-[#FFF5F7] to-[#FFE8EC] border border-[#FCD3DC] space-y-2 text-center">
        <h4 class="font-bold text-xs uppercase tracking-wider text-rose-800">Need Multiple Orders at Once?</h4>
        <p class="text-xs text-slate-600 leading-relaxed">
          Use the bulk submitter to execute up to 100 links and services in a single click.
        </p>
        <a href="/mass-order" class="inline-flex items-center gap-1.5 py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition-colors mt-1">
          <i data-lucide="layers" class="w-3.5 h-3.5"></i>
          <span>Open Mass Order</span>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  let appliedCoupon = null;
  const userCurrencySymbol = "<?= addslashes(get_currency_info($userCurrency)['symbol'] ?? '$') ?>";

  function filterServicesByCategory(catId) {
    const srvSelect = document.getElementById('main-order-service');
    let first = null;
    Array.from(srvSelect.options).forEach(opt => {
      const c = opt.getAttribute('data-category');
      if (!catId || c == catId) {
        opt.style.display = '';
        if (!first) first = opt.value;
      } else {
        opt.style.display = 'none';
      }
    });
    if (first) {
      srvSelect.value = first;
      updateServiceDetails(srvSelect);
    }
  }

  function updateServiceDetails(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt) return;

    const min = parseInt(opt.getAttribute('data-min')) || 100;
    const max = parseInt(opt.getAttribute('data-max')) || 100000;
    const desc = opt.getAttribute('data-desc') || 'High quality automated service.';
    const hasRefill = opt.getAttribute('data-refill') === '1';
    const refillDays = opt.getAttribute('data-refill-days') || '30';
    const hasSale = opt.getAttribute('data-has-sale') === '1';
    const salePercent = opt.getAttribute('data-sale-percent') || '0';
    const hasDrip = opt.getAttribute('data-dripfeed') === '1';

    document.getElementById('service-desc-box').textContent = desc;
    document.getElementById('main-order-limits').textContent = `Min: ${min.toLocaleString()} - Max: ${max.toLocaleString()}`;

    // Refill badge
    const refillBadge = document.getElementById('refill-badge');
    if (hasRefill) {
      document.getElementById('refill-badge-text').textContent = refillDays + '-Day Refill Warranty';
      refillBadge.classList.remove('hidden');
      refillBadge.classList.add('inline-flex');
    } else {
      refillBadge.classList.add('hidden');
      refillBadge.classList.remove('inline-flex');
    }

    // Sale badge
    const saleBadge = document.getElementById('sale-badge');
    if (hasSale) {
      document.getElementById('sale-badge-text').textContent = '⚡ ' + salePercent + '% OFF Flash Sale Active';
      saleBadge.classList.remove('hidden');
      saleBadge.classList.add('inline-flex');
    } else {
      saleBadge.classList.add('hidden');
      saleBadge.classList.remove('inline-flex');
    }

    // Drip badge & wrapper
    const dripWrapper = document.getElementById('dripfeed-wrapper');
    const dripBadge = document.getElementById('drip-badge');
    if (hasDrip) {
      dripWrapper.classList.remove('hidden');
      dripBadge.classList.remove('hidden');
      dripBadge.classList.add('inline-flex');
    } else {
      dripWrapper.classList.add('hidden');
      dripBadge.classList.add('hidden');
      dripBadge.classList.remove('inline-flex');
      document.getElementById('dripfeed-toggle').checked = false;
      toggleDripfeedInputs(false);
    }

    const qty = document.getElementById('main-order-quantity');
    qty.min = min;
    qty.max = max;
    if (parseInt(qty.value) < min) qty.value = min;

    // Reset applied coupon on service change
    appliedCoupon = null;
    document.getElementById('coupon-feedback').classList.add('hidden');
    document.getElementById('order-coupon-code').value = '';

    recalculateMainOrderCost();
    if (window.lucide) lucide.createIcons();
  }

  function toggleDripfeedInputs(checked) {
    const fields = document.getElementById('dripfeed-fields');
    if (checked) {
      fields.classList.remove('hidden');
    } else {
      fields.classList.add('hidden');
    }
    recalculateMainOrderCost();
  }

  function recalculateMainOrderCost() {
    const srvSelect = document.getElementById('main-order-service');
    const opt = srvSelect.options[srvSelect.selectedIndex];
    if (!opt) return;

    const convertedRate = parseFloat(opt.getAttribute('data-converted-rate')) || 0;
    const qty = parseInt(document.getElementById('main-order-quantity').value) || 0;

    let runs = 1;
    const isDrip = document.getElementById('dripfeed-toggle').checked;
    if (isDrip) {
      runs = Math.max(2, parseInt(document.getElementById('drip-runs').value) || 2);
      const totalQty = qty * runs;
      document.getElementById('drip-summary-text').textContent = `Total Quantity: ${totalQty.toLocaleString()} units delivered over ${runs} runs.`;
    }

    const totalQty = qty * runs;
    const subtotal = (convertedRate / 1000) * totalQty;

    let finalCost = subtotal;
    const strikethrough = document.getElementById('strikethrough-original');

    if (appliedCoupon && appliedCoupon.valid) {
      const discount = appliedCoupon.discount_amount || 0;
      finalCost = Math.max(0, subtotal - discount);
      strikethrough.textContent = userCurrencySymbol + subtotal.toFixed(2);
      strikethrough.classList.remove('hidden');
      document.getElementById('charge-subtext').textContent = 'Coupon applied: -' + userCurrencySymbol + discount.toFixed(2);
    } else {
      strikethrough.classList.add('hidden');
      document.getElementById('charge-subtext').textContent = 'Deducted from wallet balance';
    }

    document.getElementById('main-order-total-cost').textContent = userCurrencySymbol + finalCost.toFixed(2);
  }

  function validateAndApplyCoupon() {
    const code = document.getElementById('order-coupon-code').value.trim();
    const serviceId = document.getElementById('main-order-service').value;
    const qty = parseInt(document.getElementById('main-order-quantity').value) || 0;
    const feedback = document.getElementById('coupon-feedback');

    if (!code) {
      feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-rose-600 block';
      feedback.textContent = 'Please enter a coupon code.';
      feedback.classList.remove('hidden');
      return;
    }

    fetch('/api/coupon/validate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: code, service_id: serviceId, quantity: qty })
    })
    .then(r => r.json())
    .then(data => {
      feedback.classList.remove('hidden');
      if (data.valid) {
        appliedCoupon = data;
        feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-emerald-600 block';
        feedback.textContent = `Coupon applied! You save ${data.formatted_discount}.`;
        recalculateMainOrderCost();
      } else {
        appliedCoupon = null;
        feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-rose-600 block';
        feedback.textContent = data.error || 'Invalid coupon.';
        recalculateMainOrderCost();
      }
    })
    .catch(() => {
      feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-rose-600 block';
      feedback.textContent = 'Failed to validate coupon.';
      feedback.classList.remove('hidden');
    });
  }

  function handleNewOrderSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('main-order-submit-btn');
    const serviceId = document.getElementById('main-order-service').value;
    const link = document.getElementById('main-order-link').value.trim();
    const quantity = parseInt(document.getElementById('main-order-quantity').value);
    const isDrip = document.getElementById('dripfeed-toggle').checked;
    const runs = isDrip ? parseInt(document.getElementById('drip-runs').value) : 1;
    const interval = isDrip ? parseInt(document.getElementById('drip-interval').value) : 0;
    const couponCode = (appliedCoupon && appliedCoupon.valid) ? appliedCoupon.code : '';

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Placing Order...';

    fetch('/api/order/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        service_id: serviceId,
        link: link,
        quantity: quantity,
        is_dripfeed: isDrip ? 1 : 0,
        runs: runs,
        interval: interval,
        coupon_code: couponCode
      })
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<span>Submit Order</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
      if (window.lucide) lucide.createIcons();

      const alertBox = document.getElementById('new-order-alert');
      const alertText = document.getElementById('new-order-alert-text');

      if (data.success) {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between';
        alertText.textContent = data.message || 'Order placed successfully!';
        alertBox.classList.remove('hidden');

        if (data.new_balance) {
          document.getElementById('order-page-balance').textContent = data.new_balance;
        }

        setTimeout(() => {
          window.location.href = isDrip ? '/drip-feed' : '/orders';
        }, 1200);
      } else {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-rose-50 border-rose-200 text-rose-800 text-xs font-bold flex items-center justify-between';
        alertText.textContent = data.error || 'Failed to place order.';
        alertBox.classList.remove('hidden');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<span>Submit Order</span>';
      alert('Network error while placing order.');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const srv = document.getElementById('main-order-service');
    if (srv) updateServiceDetails(srv);
  });
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
