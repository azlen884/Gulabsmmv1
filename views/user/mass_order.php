<?php
$pageTitle = 'Mass Order - RoseSMM';
$activePage = 'mass-order';
require_once __DIR__ . '/../layouts/user_header.php';
require_once __DIR__ . '/../../includes/MassOrderHelper.php';

$db = getDB();
$userCurrency = get_user_currency();
$services = $db->query("SELECT id, name, category_id, rate, currency, min_quantity, max_quantity FROM services WHERE status = 'active' ORDER BY category_id ASC, id ASC")->fetchAll();

$userBalance = (float)$user['balance'];
$formattedBalance = format_price($userBalance, $userCurrency, 'USD');

// Check past mass order batches for this user
$batchesStmt = $db->prepare("SELECT * FROM mass_order_batches WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$batchesStmt->execute([$user['id']]);
$pastBatches = $batchesStmt->fetchAll();
?>

<div class="space-y-6 max-w-6xl mx-auto">
  <!-- Page Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 border border-rose-200/60 text-rose-600 text-xs font-bold mb-2">
        <i data-lucide="layers" class="w-3.5 h-3.5"></i> High-Speed Bulk Placement
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Mass Order Submitter
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Submit hundreds of different service links in one single instant click.
      </p>
    </div>

    <!-- Balance Card -->
    <div class="flex items-center gap-4 p-3.5 bg-white rounded-2xl border border-[#FCE4E8] shadow-sm">
      <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center border border-rose-100 font-bold">
        <i data-lucide="wallet" class="w-5 h-5"></i>
      </div>
      <div>
        <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Available Balance</div>
        <div class="text-base font-black text-rose-600"><?= e($formattedBalance) ?></div>
      </div>
      <a href="/add-funds" class="ml-2 px-3 py-1.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold shadow-sm transition-colors">
        + Add Funds
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Mass Order Form -->
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
        <form id="massOrderForm" class="space-y-5">
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                Order Lines (One order per line)
              </label>
              <button type="button" id="insertSampleBtn" class="text-xs text-rose-500 hover:text-rose-600 font-semibold inline-flex items-center gap-1">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Insert Sample Data
              </button>
            </div>
            <textarea 
              id="massOrderInput" 
              name="orders_text" 
              rows="9" 
              placeholder="service_id | quantity | link&#10;1 | 1000 | https://instagram.com/p/sample1&#10;2 | 500 | https://instagram.com/p/sample2"
              class="w-full px-4 py-3 rounded-2xl border border-slate-200 focus:border-rose-400 focus:ring-4 focus:ring-rose-50 text-xs font-mono text-slate-800 transition-all placeholder:text-slate-400 custom-scrollbar resize-y"
              required
            ></textarea>
            <div class="flex items-center justify-between text-[11px] text-slate-500 mt-2 px-1">
              <span>Format: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-rose-600 font-bold">service_id | quantity | link</code></span>
              <span id="lineCounter">0 orders detected</span>
            </div>
          </div>

          <!-- Live Input Summary Card -->
          <div id="liveSummaryBox" class="p-4 rounded-2xl bg-rose-50/40 border border-rose-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-4">
              <div>
                <span class="text-slate-400 text-[10px] uppercase font-bold block">Lines</span>
                <span id="parsedLinesCount" class="font-bold text-slate-700">0</span>
              </div>
              <div class="h-6 w-px bg-rose-200"></div>
              <div>
                <span class="text-slate-400 text-[10px] uppercase font-bold block">Estimated Cost</span>
                <span id="estimatedCost" class="font-bold text-rose-600">$0.00</span>
              </div>
            </div>
            <div id="validationBadge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-600 font-semibold text-[11px]">
              <i data-lucide="info" class="w-3.5 h-3.5"></i> Ready to validate
            </div>
          </div>

          <div class="flex items-center gap-3">
            <button 
              type="submit" 
              id="submitMassBtn" 
              class="flex-1 py-3.5 px-6 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <i data-lucide="send" class="w-4 h-4"></i>
              <span>Submit Mass Orders</span>
            </button>
            <button 
              type="button" 
              id="clearBtn" 
              class="px-4 py-3.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors"
            >
              Clear
            </button>
          </div>
        </form>
      </div>

      <!-- Execution Results Modal / Card -->
      <div id="resultsCard" class="hidden bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-800">Batch Processing Summary</h3>
            <p id="resultsSubtext" class="text-xs text-slate-500"></p>
          </div>
          <span id="batchStatusBadge" class="px-3 py-1 rounded-full text-xs font-bold"></span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
                <th class="py-2.5 px-3">Line</th>
                <th class="py-2.5 px-3">Order ID</th>
                <th class="py-2.5 px-3">Service</th>
                <th class="py-2.5 px-3">Qty</th>
                <th class="py-2.5 px-3">Charge</th>
                <th class="py-2.5 px-3">Status</th>
              </tr>
            </thead>
            <tbody id="resultsTableBody" class="divide-y divide-slate-100"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right Column: Quick Service ID Lookup & Instructions -->
    <div class="space-y-6">
      <!-- Formatting Guide Card -->
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-5 shadow-sm space-y-3">
        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
          <i data-lucide="help-circle" class="w-4 h-4 text-rose-500"></i>
          Formatting Guidelines
        </h3>
        <p class="text-xs text-slate-500 leading-relaxed">
          Put each order on a new line using a pipe delimiter (<code class="text-rose-600 font-bold">|</code>).
        </p>
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 text-[11px] font-mono text-slate-600 space-y-1">
          <div>1 | 1000 | https://instagram.com/user1</div>
          <div>5 | 2500 | https://tiktok.com/@creator</div>
          <div>8 | 500 | https://youtube.com/watch?v=xyz</div>
        </div>
        <ul class="text-xs text-slate-500 space-y-1.5 list-disc list-inside">
          <li>Ensure service ID matches the list below.</li>
          <li>Quantity must satisfy service min/max.</li>
          <li>Target URLs must be public.</li>
        </ul>
      </div>

      <!-- Quick Services Directory -->
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
            <i data-lucide="search" class="w-4 h-4 text-rose-500"></i>
            Service ID Finder
          </h3>
          <span class="text-[10px] text-slate-400 font-bold"><?= count($services) ?> active</span>
        </div>
        <input 
          type="text" 
          id="serviceSearchInput" 
          placeholder="Filter service name or ID..."
          class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:border-rose-400 focus:ring-2 focus:ring-rose-50"
        >
        <div class="max-h-72 overflow-y-auto custom-scrollbar space-y-2 pr-1" id="servicesDirectoryList">
          <?php foreach ($services as $srv): ?>
            <div class="service-item p-2.5 rounded-xl border border-slate-100 hover:border-rose-200 hover:bg-rose-50/30 transition-all cursor-pointer" data-id="<?= $srv['id'] ?>" data-name="<?= strtolower(e($srv['name'])) ?>">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-rose-600">ID: <?= $srv['id'] ?></span>
                <span class="text-[11px] font-semibold text-slate-600">
                  <?= format_price($srv['rate'], $userCurrency, $srv['currency'] ?? 'USD') ?> / 1k
                </span>
              </div>
              <div class="text-xs font-medium text-slate-700 truncate mt-0.5"><?= e($srv['name']) ?></div>
              <div class="text-[10px] text-slate-400 mt-0.5">Min: <?= number_format($srv['min_quantity']) ?> · Max: <?= number_format($srv['max_quantity']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Past Mass Order Batches -->
  <?php if (!empty($pastBatches)): ?>
    <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm space-y-4">
      <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
        <i data-lucide="history" class="w-4 h-4 text-rose-500"></i>
        Recent Mass Order Batches
      </h3>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Batch ID</th>
              <th class="py-2.5 px-3">Total Orders</th>
              <th class="py-2.5 px-3">Success / Failed</th>
              <th class="py-2.5 px-3">Total Charge</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3">Placed At</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($pastBatches as $batch): ?>
              <tr>
                <td class="py-3 px-3 font-bold text-rose-600">#<?= $batch['id'] ?></td>
                <td class="py-3 px-3 font-semibold text-slate-700"><?= $batch['total_orders'] ?> orders</td>
                <td class="py-3 px-3">
                  <span class="text-emerald-600 font-bold"><?= $batch['successful_orders'] ?> passed</span>
                  <?php if ($batch['failed_orders'] > 0): ?>
                    · <span class="text-rose-500 font-bold"><?= $batch['failed_orders'] ?> failed</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-3 font-bold text-slate-800"><?= format_price($batch['total_charge'], $userCurrency, 'USD') ?></td>
                <td class="py-3 px-3">
                  <?php if ($batch['status'] === 'completed'): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Completed</span>
                  <?php elseif ($batch['status'] === 'partial'): ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Partial</span>
                  <?php else: ?>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Failed</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-3 text-slate-400"><?= date('M d, Y H:i', strtotime($batch['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const textarea = document.getElementById('massOrderInput');
  const lineCounter = document.getElementById('lineCounter');
  const parsedLinesCount = document.getElementById('parsedLinesCount');
  const form = document.getElementById('massOrderForm');
  const submitBtn = document.getElementById('submitMassBtn');
  const insertSampleBtn = document.getElementById('insertSampleBtn');
  const clearBtn = document.getElementById('clearBtn');
  const searchInput = document.getElementById('serviceSearchInput');
  const serviceItems = document.querySelectorAll('.service-item');

  const resultsCard = document.getElementById('resultsCard');
  const resultsTableBody = document.getElementById('resultsTableBody');
  const resultsSubtext = document.getElementById('resultsSubtext');
  const batchStatusBadge = document.getElementById('batchStatusBadge');

  function updateCounters() {
    const text = textarea.value.trim();
    if (!text) {
      lineCounter.textContent = '0 orders detected';
      parsedLinesCount.textContent = '0';
      return;
    }
    const lines = text.split('\n').filter(l => l.trim().length > 0);
    lineCounter.textContent = lines.length + ' orders detected';
    parsedLinesCount.textContent = lines.length;
  }

  textarea.addEventListener('input', updateCounters);

  insertSampleBtn.addEventListener('click', function() {
    <?php 
    $firstTwo = array_slice($services, 0, 2);
    $s1 = $firstTwo[0]['id'] ?? 1;
    $s2 = $firstTwo[1]['id'] ?? $s1;
    ?>
    textarea.value = "<?= $s1 ?> | 1000 | https://instagram.com/p/sample_post_1\n<?= $s2 ?> | 500 | https://instagram.com/p/sample_post_2";
    updateCounters();
  });

  clearBtn.addEventListener('click', function() {
    textarea.value = '';
    updateCounters();
    resultsCard.classList.add('hidden');
  });

  // Service lookup search filter
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

  // Clicking service item appends template to textarea
  serviceItems.forEach(item => {
    item.addEventListener('click', function() {
      const id = this.dataset.id;
      const sampleLine = id + ' | 1000 | https://example.com/link';
      if (textarea.value.trim() === '') {
        textarea.value = sampleLine;
      } else {
        textarea.value = textarea.value.trim() + '\n' + sampleLine;
      }
      updateCounters();
    });
  });

  // Form submission via AJAX
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const content = textarea.value.trim();
    if (!content) return;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing Orders...';
    lucide.createIcons();

    fetch('/api/order/mass', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orders_text: content })
    })
    .then(r => r.json())
    .then(data => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Mass Orders';
      lucide.createIcons();

      if (!data.success) {
        alert(data.error || 'Failed to process mass order.');
        return;
      }

      // Display results card
      resultsCard.classList.remove('hidden');
      resultsSubtext.textContent = 'Batch #' + data.batch_id + ' · Charged ' + data.formatted_charge + ' · Updated Balance: ' + data.new_balance;

      if (data.failed_orders === 0) {
        batchStatusBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
        batchStatusBadge.textContent = 'All ' + data.successful_orders + ' Succeeded';
      } else {
        batchStatusBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200';
        batchStatusBadge.textContent = data.successful_orders + ' Passed / ' + data.failed_orders + ' Failed';
      }

      resultsTableBody.innerHTML = '';
      (data.results || []).forEach(res => {
        const tr = document.createElement('tr');
        if (res.status === 'success') {
          tr.innerHTML = `
            <td class="py-2.5 px-3 text-slate-500 font-mono">${res.line}</td>
            <td class="py-2.5 px-3 font-bold text-rose-600">#${res.order_id}</td>
            <td class="py-2.5 px-3 font-medium text-slate-700">${res.service_name || '-'}</td>
            <td class="py-2.5 px-3 font-semibold">${res.quantity || '-'}</td>
            <td class="py-2.5 px-3 font-semibold">$${parseFloat(res.charge).toFixed(4)}</td>
            <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Success</span></td>
          `;
        } else {
          tr.innerHTML = `
            <td class="py-2.5 px-3 text-slate-500 font-mono">${res.line}</td>
            <td class="py-2.5 px-3 text-slate-400">-</td>
            <td class="py-2.5 px-3 font-medium text-rose-600" colspan="3">${res.error}</td>
            <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">Error</span></td>
          `;
        }
        resultsTableBody.appendChild(tr);
      });

      // Scroll to results
      resultsCard.scrollIntoView({ behavior: 'smooth' });
    })
    .catch(err => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Mass Orders';
      lucide.createIcons();
      alert('Network error occurred.');
    });
  });
});
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
