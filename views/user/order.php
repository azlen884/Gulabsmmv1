<?php
$pageTitle = 'New Order - RoseSMM';
$activePage = 'order';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();
$services = $db->query("
    SELECT s.*, c.name AS category_name, c.slug AS category_slug 
    FROM services s 
    JOIN categories c ON s.category_id = c.id 
    WHERE s.status = 'active' 
    ORDER BY c.sort_order ASC, s.sort_order ASC
")->fetchAll();

$preselectedServiceId = isset($_GET['service']) ? (int)$_GET['service'] : 0;
?>

<div class="max-w-4xl mx-auto">
  <div class="flex items-center justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Place a New Order</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">Select a service, enter your target link, and boost your engagement.</p>
    </div>
    <div class="px-3.5 py-1.5 rounded-full border border-[#FCE4E8] bg-white text-xs font-bold text-slate-700">
      Balance: <span class="text-rose-600 font-extrabold" id="order-page-balance"><?= format_price($user['balance']) ?></span>
    </div>
  </div>

  <!-- Alert Box -->
  <div id="new-order-alert" class="hidden mb-6 p-4 rounded-2xl border text-sm flex items-center justify-between">
    <div class="flex items-center gap-2">
      <i data-lucide="info" class="w-5 h-5"></i>
      <span id="new-order-alert-text"></span>
    </div>
    <button onclick="document.getElementById('new-order-alert').classList.add('hidden')">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Order Form Card (2 Cols) -->
    <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-3xl border border-[#FCE4E8] shadow-sm">
      <form id="standalone-order-form" onsubmit="handleNewOrderSubmit(event)" class="space-y-4">
        <!-- Category Selection -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Category</label>
          <select id="main-order-category" onchange="filterServicesByCategory(this.value)" class="w-full px-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-rose-400">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Service Selection -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Service</label>
          <select id="main-order-service" onchange="updateServiceDetails(this)" class="w-full px-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-rose-400">
            <?php foreach ($services as $srv): ?>
              <option 
                value="<?= $srv['id'] ?>" 
                data-category="<?= $srv['category_id'] ?>" 
                data-rate="<?= $srv['rate'] ?>" 
                data-min="<?= $srv['min_quantity'] ?>" 
                data-max="<?= $srv['max_quantity'] ?>"
                data-desc="<?= e(htmlspecialchars($srv['description'] ?: '')) ?>"
                <?= $preselectedServiceId === (int)$srv['id'] ? 'selected' : '' ?>
              >
                #<?= $srv['id'] ?> - <?= e($srv['name']) ?> (<?= format_price($srv['rate']) ?> / 1K)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Description Box -->
        <div id="service-desc-box" class="p-4 rounded-2xl bg-rose-50/40 border border-[#FCE4E8] text-xs text-slate-600 leading-relaxed">
          High quality, automated instant delivery. 100% safe profile boost.
        </div>

        <!-- Link -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Link / Target URL</label>
          <div class="relative">
            <i data-lucide="link" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
            <input 
              type="text" 
              id="main-order-link" 
              required 
              placeholder="https://instagram.com/username or post link" 
              class="w-full pl-11 pr-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-rose-400"
            >
          </div>
        </div>

        <!-- Quantity -->
        <div>
          <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
            <span class="font-bold text-slate-700">Quantity</span>
            <span id="main-order-limits" class="text-slate-400">Min: 100 - Max: 100,000</span>
          </div>
          <input 
            type="number" 
            id="main-order-quantity" 
            value="1000" 
            required
            oninput="recalculateMainOrderCost()" 
            class="w-full px-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs sm:text-sm font-medium text-slate-700 focus:outline-none focus:border-rose-400"
          >
        </div>

        <!-- Total Charge Display -->
        <div class="p-4 rounded-2xl bg-gradient-to-r from-rose-50 to-pink-50 border border-rose-100 flex items-center justify-between">
          <div>
            <div class="text-xs text-slate-500 font-medium">Total Charge</div>
            <div class="text-2xl font-extrabold text-rose-600" id="main-order-total-cost">$2.50</div>
          </div>
          <div class="text-right text-xs text-slate-400">
            Deducted from wallet balance
          </div>
        </div>

        <!-- Submit Button -->
        <button 
          type="submit" 
          id="main-order-submit-btn" 
          class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2"
        >
          <span>Submit Order</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </form>
    </div>

    <!-- Instructions & FAQ Side Panel (1 Col) -->
    <div class="space-y-6">
      <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm">
        <h3 class="font-bold text-sm text-slate-800 mb-3 flex items-center gap-2">
          <i data-lucide="help-circle" class="w-4 h-4 text-rose-500"></i>
          <span>Order Guidelines</span>
        </h3>
        <ul class="text-xs text-slate-500 space-y-2.5 leading-relaxed">
          <li class="flex items-start gap-2">
            <span class="text-rose-500 font-bold">•</span>
            <span>Make sure your social media account is set to <strong>Public</strong> before placing an order.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="text-rose-500 font-bold">•</span>
            <span>Do not place two orders on the same link simultaneously until the first order completes.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="text-rose-500 font-bold">•</span>
            <span>Always double-check the link format (e.g. video URL for views/likes, profile URL for followers).</span>
          </li>
        </ul>
      </div>

      <div class="p-6 rounded-3xl bg-gradient-to-br from-[#FFF5F7] to-[#FFE8EC] border border-[#FCD3DC] text-center">
        <h4 class="font-bold text-sm text-slate-800 mb-1">Need a Custom Order?</h4>
        <p class="text-xs text-slate-500 mb-4">Have special requirements or need custom drip-feed settings? Contact support.</p>
        <a href="/support" class="inline-block py-2.5 px-4 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold shadow-sm transition-colors">
          Contact Support
        </a>
      </div>
    </div>
  </div>
</div>

<script>
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

    document.getElementById('service-desc-box').textContent = desc;
    document.getElementById('main-order-limits').textContent = `Min: ${min.toLocaleString()} - Max: ${max.toLocaleString()}`;

    const qty = document.getElementById('main-order-quantity');
    qty.min = min;
    qty.max = max;
    if (parseInt(qty.value) < min) qty.value = min;

    recalculateMainOrderCost();
  }

  function recalculateMainOrderCost() {
    const srvSelect = document.getElementById('main-order-service');
    const opt = srvSelect.options[srvSelect.selectedIndex];
    if (!opt) return;

    const rate = parseFloat(opt.getAttribute('data-rate')) || 0;
    const qty = parseInt(document.getElementById('main-order-quantity').value) || 0;
    const cost = (rate / 1000) * qty;

    document.getElementById('main-order-total-cost').textContent = '$' + cost.toFixed(2);
  }

  function handleNewOrderSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('main-order-submit-btn');
    const serviceId = document.getElementById('main-order-service').value;
    const link = document.getElementById('main-order-link').value.trim();
    const quantity = parseInt(document.getElementById('main-order-quantity').value);

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Placing Order...';

    fetch('/api/order/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ service_id: serviceId, link: link, quantity: quantity })
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<span>Submit Order</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
      if (window.lucide) lucide.createIcons();

      const alertBox = document.getElementById('new-order-alert');
      const alertText = document.getElementById('new-order-alert-text');

      if (data.success) {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-sm flex items-center justify-between';
        alertText.textContent = data.message || 'Order placed successfully!';
        alertBox.classList.remove('hidden');

        if (data.new_balance) {
          document.getElementById('order-page-balance').textContent = data.new_balance;
        }

        setTimeout(() => {
          window.location.href = '/orders';
        }, 1200);
      } else {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-rose-50 border-rose-200 text-rose-800 text-sm flex items-center justify-between';
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
