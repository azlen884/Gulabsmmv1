<?php
$pageTitle = 'Mass Order - SMM Pro';
$activePage = 'mass-order';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../../includes/MassOrderHelper.php';

$db = getDB();
$userCurrency = get_user_currency();
$services = $db->query("SELECT id, name, category_id, rate, min_quantity, max_quantity FROM services WHERE status = 'active' ORDER BY category_id ASC, id ASC")->fetchAll();

$userBalance = (float)$user['balance'];
$formattedBalance = format_price($userBalance, $userCurrency, 'USD');

// Past mass order batches
$batchesStmt = $db->prepare("SELECT * FROM mass_order_batches WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$batchesStmt->execute([$user['id']]);
$pastBatches = $batchesStmt->fetchAll();
?>

<div class="space-y-6 max-w-6xl mx-auto">
  <!-- Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="layers" class="w-6 h-6 text-[#FF2D78]"></i>
        Mass Order Submitter
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Submit multiple service orders concurrently in one single instant click.</p>
    </div>

    <!-- Balance Card -->
    <div class="flex items-center gap-4 p-3.5 smm-card">
      <div class="w-10 h-10 rounded-xl bg-[#FF2D78]/15 text-[#FF2D78] flex items-center justify-center font-bold">
        <i data-lucide="wallet" class="w-5 h-5"></i>
      </div>
      <div>
        <div class="text-[10px] uppercase font-bold text-[#9D9DB8] tracking-wider">Available Balance</div>
        <div class="text-base font-black text-white"><?= e($formattedBalance) ?></div>
      </div>
      <a href="/add-funds" class="ml-2 smm-btn-pink px-3 py-1.5 text-xs">
        + Add Funds
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Mass Order Form (2 cols) -->
    <div class="lg:col-span-2 space-y-6">
      <div class="smm-card p-6 sm:p-7 space-y-5">
        <form id="massOrderForm" class="space-y-5">
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="block text-xs font-bold text-white uppercase tracking-wider">
                Order Lines (Format: service_id | quantity | link)
              </label>
              <button type="button" id="insertSampleBtn" class="text-xs text-[#FF2D78] hover:underline font-semibold flex items-center gap-1">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Insert Sample
              </button>
            </div>

            <textarea 
              id="massOrderText" 
              name="orders_text" 
              rows="10" 
              placeholder="101 | 1000 | https://instagram.com/p/sample1&#10;102 | 5000 | https://tiktok.com/@sample2"
              class="smm-input font-mono text-xs leading-relaxed"
              required
            ></textarea>
          </div>

          <!-- Counter Bar -->
          <div class="flex items-center justify-between p-3.5 rounded-xl bg-[#161628] border border-white/5 text-xs">
            <span class="text-[#9D9DB8]">Orders to Process: <strong id="lineCount" class="text-white">0</strong></span>
            <span class="text-[11px] text-[#6C6C8A]">Strict format validation enforced</span>
          </div>

          <button 
            type="submit" 
            id="massSubmitBtn" 
            class="smm-btn-pink w-full py-3.5 text-sm font-bold flex items-center justify-center gap-2"
          >
            <i data-lucide="send" class="w-4 h-4"></i>
            <span>Submit Mass Orders</span>
          </button>
        </form>
      </div>

      <!-- Results Card (Hidden by default) -->
      <div id="resultsCard" class="smm-card p-6 hidden space-y-4">
        <div class="flex items-center justify-between border-b border-white/5 pb-3">
          <div>
            <h3 class="text-sm font-black text-white">Batch Processing Results</h3>
            <span id="resultsSubtext" class="text-xs text-[#9D9DB8]"></span>
          </div>
          <span id="batchStatusBadge" class="px-3 py-1 rounded-full text-xs font-bold"></span>
        </div>
        <div class="overflow-x-auto">
          <table class="smm-table">
            <thead>
              <tr>
                <th>Line</th>
                <th>Order ID</th>
                <th>Service</th>
                <th>Qty</th>
                <th>Charge</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="resultsTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Instructions & Quick Service List (1 col) -->
    <div class="space-y-4">
      <div class="smm-card p-5 space-y-3">
        <h3 class="text-xs font-black text-white uppercase tracking-wider flex items-center gap-2">
          <i data-lucide="info" class="w-4 h-4 text-[#FF2D78]"></i> Mass Order Syntax
        </h3>
        <p class="text-xs text-[#9D9DB8] leading-relaxed">
          Each order must be on its own line using vertical bar (<code>|</code>) separators:
        </p>
        <div class="p-3 rounded-xl bg-[#161628] border border-white/5 font-mono text-[11px] text-[#FF2D78] leading-tight">
          service_id | quantity | link
        </div>
        <p class="text-[11px] text-[#6C6C8A]">Example:</p>
        <div class="p-3 rounded-xl bg-[#161628] border border-white/5 font-mono text-[10px] text-[#9D9DB8] leading-tight space-y-1">
          <div>1 | 1000 | https://instagram.com/user</div>
          <div>5 | 500 | https://youtube.com/watch?v=xyz</div>
        </div>
      </div>

      <!-- Quick Services Directory Search -->
      <div class="smm-card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-black text-white uppercase tracking-wider">Service ID Finder</h3>
          <span class="text-[10px] text-[#9D9DB8]"><?= count($services) ?> Services</span>
        </div>
        <input 
          type="text" 
          id="serviceSearchInput" 
          placeholder="Filter by name or ID..." 
          class="smm-input text-xs py-1.5"
        >
        <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1 custom-scrollbar" id="servicesDirectoryList">
          <?php foreach ($services as $s): ?>
            <div 
              class="service-item p-2 rounded-xl bg-[#161628] border border-white/5 hover:border-[#FF2D78]/30 cursor-pointer transition-colors text-xs"
              data-id="<?= (int)$s['id'] ?>"
              data-name="<?= strtolower(e($s['name'])) ?>"
            >
              <div class="flex items-center justify-between">
                <span class="font-mono text-[#FF2D78] font-bold">#<?= (int)$s['id'] ?></span>
                <span class="text-[10px] text-[#9D9DB8]"><?= format_price($s['rate']) ?>/1k</span>
              </div>
              <div class="text-white font-medium text-[11px] truncate mt-0.5"><?= e($s['name']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const textarea = document.getElementById('massOrderText');
  const lineCountEl = document.getElementById('lineCount');
  const sampleBtn = document.getElementById('insertSampleBtn');
  const form = document.getElementById('massOrderForm');
  const submitBtn = document.getElementById('massSubmitBtn');
  const searchInput = document.getElementById('serviceSearchInput');
  const serviceItems = document.querySelectorAll('.service-item');
  const resultsCard = document.getElementById('resultsCard');
  const resultsSubtext = document.getElementById('resultsSubtext');
  const batchStatusBadge = document.getElementById('batchStatusBadge');
  const resultsTableBody = document.getElementById('resultsTableBody');

  function updateCounters() {
    const lines = textarea.value.split('\n').filter(l => l.trim().length > 0);
    lineCountEl.textContent = lines.length;
  }

  textarea.addEventListener('input', updateCounters);

  sampleBtn.addEventListener('click', function() {
    textarea.value = "1 | 1000 | https://instagram.com/sample_account\n2 | 500 | https://youtube.com/watch?v=example";
    updateCounters();
  });

  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    serviceItems.forEach(item => {
      const id = item.dataset.id;
      const name = item.dataset.name;
      if (id.includes(q) || name.includes(q)) {
        item.style.display = 'block';
      } else {
        item.style.display = 'none';
      }
    });
  });

  serviceItems.forEach(item => {
    item.addEventListener('click', function() {
      const id = this.dataset.id;
      const sampleLine = id + ' | 1000 | https://example.com/target';
      if (textarea.value.trim() === '') {
        textarea.value = sampleLine;
      } else {
        textarea.value = textarea.value.trim() + '\n' + sampleLine;
      }
      updateCounters();
    });
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const content = textarea.value.trim();
    if (!content) return;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Processing Orders...</span>';

    fetch('/api/order/mass', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orders_text: content })
    })
    .then(r => r.json())
    .then(data => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Mass Orders';
      if (window.lucide) lucide.createIcons();

      if (!data.success) {
        alert(data.error || 'Failed to process mass order.');
        return;
      }

      resultsCard.classList.remove('hidden');
      resultsSubtext.textContent = 'Batch #' + data.batch_id + ' · Charged ' + data.formatted_charge + ' · New Balance: ' + data.new_balance;

      if (data.failed_orders === 0) {
        batchStatusBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-[#10B981]/20 text-[#34D399] border border-[#10B981]/30';
        batchStatusBadge.textContent = 'All ' + data.successful_orders + ' Succeeded';
      } else {
        batchStatusBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-[#F59E0B]/20 text-[#FBBF24] border border-[#F59E0B]/30';
        batchStatusBadge.textContent = data.successful_orders + ' Passed / ' + data.failed_orders + ' Failed';
      }

      resultsTableBody.innerHTML = '';
      (data.results || []).forEach(res => {
        const tr = document.createElement('tr');
        if (res.status === 'success') {
          tr.innerHTML = `
            <td class="font-mono text-xs text-[#9D9DB8]">${res.line}</td>
            <td class="font-mono text-xs font-bold text-[#FF2D78]">#${res.order_id}</td>
            <td class="text-xs text-white">${res.service_name || '-'}</td>
            <td class="text-xs text-white">${res.quantity || '-'}</td>
            <td class="text-xs font-mono font-bold text-[#FF2D78]">$${parseFloat(res.charge).toFixed(4)}</td>
            <td><span class="smm-badge-completed">Success</span></td>
          `;
        } else {
          tr.innerHTML = `
            <td class="font-mono text-xs text-[#9D9DB8]">${res.line}</td>
            <td class="text-xs text-[#6C6C8A]">-</td>
            <td class="text-xs text-[#F87171]" colspan="3">${res.error}</td>
            <td><span class="smm-badge-canceled">Error</span></td>
          `;
        }
        resultsTableBody.appendChild(tr);
      });

      resultsCard.scrollIntoView({ behavior: 'smooth' });
    })
    .catch(() => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<span>Submit Mass Orders</span>';
      alert('Network error occurred.');
    });
  });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
