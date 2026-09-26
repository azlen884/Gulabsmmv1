<?php
$pageTitle = 'New Order - SMM Pro';
$activePage = 'order';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../../includes/FlashSaleHelper.php';

$db = getDB();
$userCurrency = get_user_currency();
$activeSales = FlashSaleHelper::getActiveFlashSales();

$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();
$services = $db->query("
    SELECT s.*, c.name AS category_name, c.slug AS category_slug 
    FROM services s 
    JOIN categories c ON s.category_id = c.id 
    WHERE s.status = 'active' 
    ORDER BY c.sort_order ASC, s.sort_order ASC, s.id ASC
")->fetchAll();

$preselectedServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : (isset($_GET['service']) ? (int)$_GET['service'] : 0);
?>

<div class="max-w-4xl mx-auto space-y-6">
  <!-- Header Bar -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="plus-circle" class="w-6 h-6 text-[#FF2D78]"></i>
        Place New Order
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Configure your target link, volume, and automate instant delivery.</p>
    </div>
    <div class="px-4 py-2 rounded-2xl bg-[#131322] border border-white/10 text-xs font-bold text-white shadow-sm flex items-center gap-2">
      <span class="text-[#9D9DB8]">Balance:</span>
      <span class="text-[#FF2D78] font-black text-sm" id="order-page-balance"><?= format_price($user['balance']) ?></span>
    </div>
  </div>

  <!-- Alert Box -->
  <div id="new-order-alert" class="hidden p-4 rounded-2xl border text-xs font-bold flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-2.5">
      <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
      <span id="new-order-alert-text"></span>
    </div>
    <button type="button" onclick="document.getElementById('new-order-alert').classList.add('hidden')">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Order Form Card (7 Cols) -->
    <div class="lg:col-span-7 smm-card p-6 sm:p-7 space-y-5">
      <form id="standalone-order-form" onsubmit="handleNewOrderSubmit(event)" class="space-y-4">
        
        <!-- Category Selection -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-2">1. Select Category</label>
          <select id="main-order-category" onchange="filterServicesByCategory(this.value)" class="smm-input">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Service Selection -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-2">2. Select Service</label>
          <select id="main-order-service" onchange="updateServiceDetails(this)" class="smm-input">
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
                data-desc="<?= htmlspecialchars($srv['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-refill="<?= !empty($srv['refill']) ? '1' : '0' ?>"
                data-refill-days="<?= (int)($srv['refill_days'] ?? 30) ?>"
                data-cancel="<?= !empty($srv['cancel']) ? '1' : '0' ?>"
                <?= $preselectedServiceId === (int)$srv['id'] ? 'selected' : '' ?>
              >
                [ID: <?= $srv['id'] ?>] <?= e($srv['name']) ?> - <?= $formattedRate ?>/1k
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Link Input -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-2">3. Target Link</label>
          <input 
            type="url" 
            id="main-order-link" 
            required 
            placeholder="https://instagram.com/username or post link" 
            class="smm-input"
          >
          <span class="text-[11px] text-[#6C6C8A] mt-1 block">Ensure account is public during fulfillment.</span>
        </div>

        <!-- Quantity Input -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8]">4. Quantity</label>
            <span class="text-xs text-[#9D9DB8]">
              Min: <strong id="srv-min-display" class="text-white">10</strong> • 
              Max: <strong id="srv-max-display" class="text-white">100,000</strong>
            </span>
          </div>
          <input 
            type="number" 
            id="main-order-quantity" 
            required 
            value="1000" 
            min="1" 
            oninput="recalculateMainOrderCost()" 
            class="smm-input"
          >
        </div>

        <!-- Coupon Code Input -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-2">Discount Coupon (Optional)</label>
          <div class="flex gap-2">
            <input 
              type="text" 
              id="order-coupon-code" 
              placeholder="e.g. VIP50" 
              class="smm-input uppercase font-mono"
            >
            <button 
              type="button" 
              onclick="validateAndApplyCoupon()" 
              class="smm-btn-dark px-4 py-2 text-xs shrink-0"
            >
              Apply
            </button>
          </div>
          <div id="coupon-feedback" class="hidden text-[11px] mt-1.5 font-bold px-1"></div>
        </div>

        <!-- Drip-Feed Option Toggle -->
        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-3">
          <label class="flex items-center justify-between cursor-pointer">
            <span class="text-xs font-bold text-white flex items-center gap-2">
              <i data-lucide="repeat" class="w-4 h-4 text-[#FF2D78]"></i>
              Drip-Feed Automated Delivery
            </span>
            <input type="checkbox" id="dripfeed-toggle" onchange="toggleDripFields(this.checked)" class="w-4 h-4 rounded text-[#FF2D78] focus:ring-0">
          </label>
          <div id="drip-fields" class="hidden grid grid-cols-2 gap-3 pt-2 border-t border-white/5">
            <div>
              <label class="block text-[11px] text-[#9D9DB8] mb-1 font-semibold">Runs</label>
              <input type="number" id="drip-runs" value="2" min="2" oninput="recalculateMainOrderCost()" class="smm-input py-1.5 text-xs">
            </div>
            <div>
              <label class="block text-[11px] text-[#9D9DB8] mb-1 font-semibold">Interval (min)</label>
              <input type="number" id="drip-interval" value="60" min="10" class="smm-input py-1.5 text-xs">
            </div>
            <div class="col-span-2 text-[10px] text-[#FF2D78] font-semibold" id="drip-summary-text"></div>
          </div>
        </div>

        <!-- Total Price Summary Box -->
        <div class="p-4 rounded-2xl bg-[#18182D] border border-white/10 flex items-center justify-between">
          <div>
            <span class="text-xs font-bold text-[#9D9DB8] block">Total Amount</span>
            <div class="flex items-baseline gap-2">
              <span id="strikethrough-original" class="hidden text-xs text-[#6C6C8A] line-through"></span>
              <span id="main-order-total-cost" class="text-2xl font-black text-white">$0.00</span>
            </div>
            <span id="charge-subtext" class="text-[10px] text-[#9D9DB8]">Deducted directly from wallet balance</span>
          </div>

          <button 
            type="submit" 
            id="main-order-submit-btn" 
            class="smm-btn-pink px-6 py-3 text-sm"
          >
            <span>Submit Order</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
        </div>

      </form>
    </div>

    <!-- Service Details & Specs Card (5 Cols) -->
    <div class="lg:col-span-5 space-y-4">
      <div class="smm-card p-6 space-y-4">
        <h3 class="text-base font-black text-white flex items-center gap-2 border-b border-white/5 pb-3">
          <i data-lucide="info" class="w-4.5 h-4.5 text-[#FF2D78]"></i>
          Service Specifications
        </h3>

        <div class="space-y-3 text-xs">
          <div class="flex items-center justify-between py-1.5 border-b border-white/5">
            <span class="text-[#9D9DB8]">Service ID:</span>
            <strong id="spec-service-id" class="text-white font-mono font-bold">-</strong>
          </div>
          <div class="flex items-center justify-between py-1.5 border-b border-white/5">
            <span class="text-[#9D9DB8]">Price per 1,000:</span>
            <strong id="spec-service-rate" class="text-[#FF2D78] font-bold">-</strong>
          </div>
          <div class="flex items-center justify-between py-1.5 border-b border-white/5">
            <span class="text-[#9D9DB8]">Min Quantity:</span>
            <strong id="spec-service-min" class="text-white">-</strong>
          </div>
          <div class="flex items-center justify-between py-1.5 border-b border-white/5">
            <span class="text-[#9D9DB8]">Max Quantity:</span>
            <strong id="spec-service-max" class="text-white">-</strong>
          </div>
          <div class="flex items-center justify-between py-1.5 border-b border-white/5">
            <span class="text-[#9D9DB8]">Refill Guarantee:</span>
            <span id="spec-service-refill" class="text-[#10B981] font-bold">30 Days Refill</span>
          </div>
          <div class="flex items-center justify-between py-1.5 border-b border-white/5">
            <span class="text-[#9D9DB8]">Average Speed:</span>
            <strong class="text-white">Instant (0-10 min)</strong>
          </div>
        </div>

        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8] block mb-1">Description</span>
          <div id="spec-service-desc" class="p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs text-[#9D9DB8] leading-relaxed max-h-40 overflow-y-auto whitespace-pre-line">
            Select a service to view features and instructions.
          </div>
        </div>
      </div>

      <!-- Quick Tips Card -->
      <div class="smm-card p-5 border-[#FF2D78]/20 bg-gradient-to-br from-[#131322] to-[#18182D]">
        <div class="flex items-center gap-2 mb-2">
          <i data-lucide="shield-alert" class="w-4 h-4 text-[#FF2D78]"></i>
          <span class="text-xs font-black text-white uppercase tracking-wider">Order Guidelines</span>
        </div>
        <ul class="text-[11px] text-[#9D9DB8] space-y-1.5 list-disc list-inside">
          <li>Ensure the social media profile or post is set to public.</li>
          <li>Do not change username while the order is in progress.</li>
          <li>Do not submit a second order for the same link until the first is completed.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
  const userCurrencySymbol = "<?= e(get_currency_info($userCurrency)['symbol'] ?? '$') ?>";
  let appliedCoupon = null;

  function filterServicesByCategory(catId) {
    const srvSelect = document.getElementById('main-order-service');
    let firstVisible = null;

    for (let opt of srvSelect.options) {
      if (opt.getAttribute('data-category') == catId) {
        opt.style.display = '';
        if (!firstVisible) firstVisible = opt;
      } else {
        opt.style.display = 'none';
      }
    }

    if (firstVisible) {
      srvSelect.value = firstVisible.value;
      updateServiceDetails(srvSelect);
    }
  }

  function updateServiceDetails(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt) return;

    const min = parseInt(opt.getAttribute('data-min')) || 10;
    const max = parseInt(opt.getAttribute('data-max')) || 100000;
    const desc = opt.getAttribute('data-desc') || 'No description provided for this service.';
    const refill = opt.getAttribute('data-refill') === '1';
    const refillDays = opt.getAttribute('data-refill-days') || '30';

    document.getElementById('spec-service-id').textContent = '#' + opt.value;
    document.getElementById('spec-service-min').textContent = min.toLocaleString();
    document.getElementById('spec-service-max').textContent = max.toLocaleString();
    document.getElementById('srv-min-display').textContent = min.toLocaleString();
    document.getElementById('srv-max-display').textContent = max.toLocaleString();
    document.getElementById('spec-service-desc').textContent = desc;

    const refillSpan = document.getElementById('spec-service-refill');
    if (refill) {
      refillSpan.className = 'text-[#10B981] font-bold';
      refillSpan.textContent = refillDays + ' Days Guaranteed';
    } else {
      refillSpan.className = 'text-[#6C6C8A] font-semibold';
      refillSpan.textContent = 'No Refill';
    }

    const convertedRate = parseFloat(opt.getAttribute('data-converted-rate')) || 0;
    document.getElementById('spec-service-rate').textContent = userCurrencySymbol + convertedRate.toFixed(2) + ' / 1k';

    const qtyInput = document.getElementById('main-order-quantity');
    qtyInput.min = min;
    qtyInput.max = max;
    if (parseInt(qtyInput.value) < min) qtyInput.value = min;

    recalculateMainOrderCost();
  }

  function toggleDripFields(checked) {
    const fields = document.getElementById('drip-fields');
    if (checked) {
      fields.classList.remove('hidden');
    } else {
      fields.classList.add('hidden');
    }
    recalculateMainOrderCost();
  }

  function recalculateMainOrderCost() {
    const srv = document.getElementById('main-order-service');
    const opt = srv.options[srv.selectedIndex];
    if (!opt) return;

    const convertedRate = parseFloat(opt.getAttribute('data-converted-rate')) || 0;
    const qty = parseInt(document.getElementById('main-order-quantity').value) || 0;

    let runs = 1;
    const isDrip = document.getElementById('dripfeed-toggle').checked;
    if (isDrip) {
      runs = Math.max(2, parseInt(document.getElementById('drip-runs').value) || 2);
      const totalQty = qty * runs;
      document.getElementById('drip-summary-text').textContent = `Total: ${totalQty.toLocaleString()} units delivered over ${runs} runs.`;
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
      document.getElementById('charge-subtext').textContent = 'Deducted directly from wallet balance';
    }

    document.getElementById('main-order-total-cost').textContent = userCurrencySymbol + finalCost.toFixed(2);
  }

  function validateAndApplyCoupon() {
    const code = document.getElementById('order-coupon-code').value.trim();
    const serviceId = document.getElementById('main-order-service').value;
    const qty = parseInt(document.getElementById('main-order-quantity').value) || 0;
    const feedback = document.getElementById('coupon-feedback');

    if (!code) {
      feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-[#F87171] block';
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
        feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-[#34D399] block';
        feedback.textContent = `Coupon applied! You save ${data.formatted_discount}.`;
        recalculateMainOrderCost();
      } else {
        appliedCoupon = null;
        feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-[#F87171] block';
        feedback.textContent = data.error || 'Invalid coupon.';
        recalculateMainOrderCost();
      }
    })
    .catch(() => {
      feedback.className = 'text-[11px] mt-1.5 font-bold px-1 text-[#F87171] block';
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
    btn.innerHTML = '<span>Processing...</span>';

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
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-[#10B981]/15 border-[#10B981]/30 text-[#34D399] text-xs font-bold flex items-center justify-between';
        alertText.textContent = data.message || 'Order placed successfully!';
        alertBox.classList.remove('hidden');

        if (data.new_balance) {
          document.getElementById('order-page-balance').textContent = data.new_balance;
        }

        setTimeout(() => {
          window.location.href = isDrip ? '/drip-feed' : '/orders';
        }, 1200);
      } else {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-[#EF4444]/15 border-[#EF4444]/30 text-[#F87171] text-xs font-bold flex items-center justify-between';
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

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
