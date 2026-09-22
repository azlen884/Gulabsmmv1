<?php
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Add Funds - RoseSMM';
$activePage = 'add-funds';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = (int)$user['id'];

// Fetch ONLY gateways that are currently turned ON (active) by Admin in MySQL
$gatewaysStmt = $db->query("SELECT * FROM payment_gateways WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
$activeGateways = $gatewaysStmt->fetchAll();

$bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
?>

<div class="max-w-4xl mx-auto">
  <div class="flex items-center justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Add Funds to Wallet</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">Select an active payment method to credit your balance securely.</p>
    </div>
    <div class="px-4 py-2 rounded-full border border-[#FCE4E8] bg-white text-xs font-bold text-slate-700 shadow-sm">
      Current Balance: <span class="text-rose-600 font-extrabold"><?= format_price($user['balance']) ?></span>
    </div>
  </div>

  <?php if ($bonusPercent > 0): ?>
    <!-- Bonus Banner -->
    <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-[#FFE8EC] to-[#FFD5DE] border border-[#FCD3DC] mb-6 flex items-center justify-between gap-4 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-sm">
          <i data-lucide="gift" class="w-6 h-6"></i>
        </div>
        <div>
          <h3 class="font-extrabold text-base text-slate-800"><?= number_format($bonusPercent, 0) ?>% Deposit Bonus Available!</h3>
          <p class="text-xs text-slate-600">Every deposit receives an extra <?= number_format($bonusPercent, 0) ?>% bonus credited directly to your account.</p>
        </div>
      </div>
      <span class="hidden sm:inline-block px-3 py-1 rounded-full bg-rose-500 text-white text-xs font-bold shrink-0">
        +<?= number_format($bonusPercent, 0) ?>% Bonus
      </span>
    </div>
  <?php endif; ?>

  <div id="deposit-alert" class="hidden mb-6 p-4 rounded-2xl border text-sm flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-2">
      <i id="deposit-alert-icon" data-lucide="info" class="w-5 h-5 shrink-0"></i>
      <span id="deposit-alert-text"></span>
    </div>
    <button onclick="document.getElementById('deposit-alert').classList.add('hidden')">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  </div>

  <?php if (empty($activeGateways)): ?>
    <!-- Empty State When NO Gateways Are Active (OFF in Admin) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-10 text-center shadow-sm max-w-lg mx-auto">
      <div class="w-16 h-16 rounded-3xl bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-4 border border-slate-200">
        <i data-lucide="credit-card" class="w-8 h-8"></i>
      </div>
      <h2 class="text-lg font-bold text-slate-800 mb-2">Deposits Currently Unavailable</h2>
      <p class="text-xs text-slate-500 leading-relaxed mb-6">
        Online deposit gateways are currently undergoing routine maintenance or are temporarily disabled by the administrator. Please check back shortly or contact our support team.
      </p>
      <div class="flex items-center justify-center gap-3">
        <a href="/support" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1.5">
          <i data-lucide="life-buoy" class="w-4 h-4"></i>
          <span>Contact Support</span>
        </a>
        <a href="/dashboard" class="px-5 py-2.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
          Return to Dashboard
        </a>
      </div>
    </div>

  <?php else: ?>
    <!-- Active Gateways Available from MySQL -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Deposit Form (2 Cols) -->
      <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-3xl border border-[#FCE4E8] shadow-sm">
        <form id="add-funds-form" onsubmit="handleDepositSubmit(event)" class="space-y-5">
          
          <!-- Payment Method Selection -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="block text-xs font-bold text-slate-700">Select Active Payment Method</label>
              <span class="text-[11px] text-slate-400"><?= count($activeGateways) ?> method(s) enabled</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="payment-methods-grid">
              <?php foreach ($activeGateways as $idx => $gw): 
                $isFirst = ($idx === 0);
              ?>
                <label class="payment-method-card border <?= $isFirst ? 'border-2 border-rose-500 bg-rose-50/30' : 'border-[#FCE4E8] bg-white' ?> p-4 rounded-2xl cursor-pointer flex items-center justify-between transition-all hover:border-rose-300">
                  <div class="flex items-center gap-3">
                    <input 
                      type="radio" 
                      name="gateway_code" 
                      value="<?= e($gw['code']) ?>" 
                      <?= $isFirst ? 'checked' : '' ?> 
                      class="hidden" 
                      onchange="selectPaymentMethod(this)"
                      data-min="<?= (float)$gw['min_amount'] ?>"
                      data-max="<?= (float)$gw['max_amount'] ?>"
                      data-fee="<?= (float)$gw['fee_percent'] ?>"
                      data-name="<?= e($gw['name']) ?>"
                      data-currency="<?= e($gw['currency']) ?>"
                      data-is-manual="<?= $gw['code'] === 'bank_transfer' ? '1' : '0' ?>"
                      data-parameters="<?= htmlspecialchars($gw['parameters'] ?? '', ENT_QUOTES) ?>"
                      data-desc="<?= htmlspecialchars($gw['description'] ?? '', ENT_QUOTES) ?>"
                    >
                    <div class="w-10 h-10 rounded-xl <?= $isFirst ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-600' ?> flex items-center justify-center shrink-0">
                      <?php if ($gw['code'] === 'stripe'): ?>
                        <i data-lucide="credit-card" class="w-5 h-5"></i>
                      <?php elseif ($gw['code'] === 'paypal'): ?>
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                      <?php elseif ($gw['code'] === 'bank_transfer'): ?>
                        <i data-lucide="building" class="w-5 h-5"></i>
                      <?php elseif ($gw['code'] === 'razorpay'): ?>
                        <i data-lucide="zap" class="w-5 h-5"></i>
                      <?php else: ?>
                        <i data-lucide="coins" class="w-5 h-5"></i>
                      <?php endif; ?>
                    </div>
                    <div>
                      <span class="text-xs font-bold text-slate-800 block leading-tight"><?= e($gw['name']) ?></span>
                      <span class="text-[10px] text-slate-400 mt-0.5 block">
                        Min: $<?= number_format($gw['min_amount'], 2) ?> • <?= $gw['fee_percent'] > 0 ? number_format($gw['fee_percent'], 1) . '% Fee' : '0% Fee' ?>
                      </span>
                    </div>
                  </div>
                  <div class="w-4 h-4 rounded-full border-2 <?= $isFirst ? 'border-rose-500 bg-rose-500' : 'border-slate-300' ?> flex items-center justify-center">
                    <div class="w-1.5 h-1.5 rounded-full bg-white <?= $isFirst ? '' : 'hidden' ?>"></div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Gateway Description / Notice Box -->
          <div id="gateway-notice" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
            <span id="gateway-notice-text" class="leading-relaxed"></span>
          </div>

          <!-- Bank Transfer Manual Details & Reference Field (shown only when manual transfer is selected) -->
          <div id="manual-transfer-container" class="hidden p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-3">
            <div class="font-bold flex items-center gap-1.5 text-amber-800">
              <i data-lucide="info" class="w-4 h-4"></i>
              <span>Manual Bank Deposit Instructions:</span>
            </div>
            <div id="manual-transfer-details" class="p-3 bg-white rounded-xl border border-amber-200 font-mono text-[11px] whitespace-pre-line text-slate-700"></div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Your Transaction Reference / UTR / Receipt Details *</label>
              <input 
                type="text" 
                id="manual-reference-input" 
                placeholder="e.g. UTR12345678 or Chase Ref: 987654" 
                class="w-full px-3.5 py-2.5 bg-white border border-amber-300 rounded-xl text-xs font-mono focus:outline-none focus:border-rose-400"
              >
            </div>
          </div>

          <!-- Amount Input -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-slate-700">Deposit Amount (USD)</label>
              <span id="amount-limits-label" class="text-[11px] text-slate-400">Min: $5.00 • Max: $5,000.00</span>
            </div>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-extrabold text-slate-400">$</span>
              <input 
                type="number" 
                id="deposit-amount" 
                value="25" 
                min="5" 
                step="any"
                required 
                oninput="calculateBonus()" 
                class="w-full pl-9 pr-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-sm font-bold text-slate-800 focus:outline-none focus:border-rose-400"
              >
            </div>

            <!-- Quick Amount Buttons -->
            <div class="flex items-center gap-2 mt-2 flex-wrap">
              <button type="button" onclick="setDepositAmount(10)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600 transition-colors">$10</button>
              <button type="button" onclick="setDepositAmount(25)" class="px-3 py-1 rounded-full text-xs font-bold border border-rose-400 text-rose-600 bg-rose-50">$25</button>
              <button type="button" onclick="setDepositAmount(50)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600 transition-colors">$50</button>
              <button type="button" onclick="setDepositAmount(100)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600 transition-colors">$100</button>
              <button type="button" onclick="setDepositAmount(250)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600 transition-colors">$250</button>
            </div>
          </div>

          <!-- Calculation summary -->
          <div class="p-4 rounded-2xl bg-rose-50/40 border border-[#FCE4E8] space-y-2 text-xs">
            <div class="flex items-center justify-between text-slate-600">
              <span>Deposit Amount:</span>
              <span id="summary-deposit-amount" class="font-bold text-slate-800">$25.00</span>
            </div>
            <div id="fee-summary-row" class="hidden flex items-center justify-between text-slate-500">
              <span>Gateway Fee:</span>
              <span id="summary-fee-amount" class="font-bold text-slate-700">$0.00</span>
            </div>
            <?php if ($bonusPercent > 0): ?>
              <div class="flex items-center justify-between text-emerald-600 font-bold">
                <span>Deposit Bonus (<?= number_format($bonusPercent, 0) ?>%):</span>
                <span id="summary-bonus-amount">+$2.50</span>
              </div>
            <?php endif; ?>
            <div class="border-t border-[#FCD3DC] pt-2 flex items-center justify-between text-sm font-extrabold text-slate-800">
              <span>Total Balance Credited:</span>
              <span id="summary-total-credited" class="text-rose-600 font-black">$27.50</span>
            </div>
          </div>

          <!-- Submit Button -->
          <button 
            type="submit" 
            id="deposit-submit-btn" 
            class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2"
          >
            <span id="deposit-btn-text">Proceed with Payment</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
        </form>
      </div>

      <!-- Security & Trust Information -->
      <div class="space-y-6">
        <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm">
          <h3 class="font-bold text-sm text-slate-800 mb-3 flex items-center gap-2">
            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i>
            <span>Verified Secure Payments</span>
          </h3>
          <p class="text-xs text-slate-500 leading-relaxed mb-4">
            All payments are processed securely through certified gateway infrastructure. Balances are credited instantly once verified.
          </p>
          <div class="flex flex-wrap gap-1.5 text-slate-400">
            <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">256-Bit SSL</span>
            <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">Real-Time Verification</span>
            <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">Anti-Fraud Protection</span>
          </div>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm">
          <h3 class="font-bold text-sm text-slate-800 mb-2">Need Deposit Assistance?</h3>
          <p class="text-xs text-slate-500 leading-relaxed mb-3">
            If your funds do not reflect within 5 minutes, our customer support team is available 24/7 to assist.
          </p>
          <a href="/support" class="text-xs font-bold text-rose-500 hover:text-rose-600 flex items-center gap-1">
            <span>Open Support Ticket</span>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  const bonusRate = <?= $bonusPercent / 100.0 ?>;
  let currentGateway = null;

  function selectPaymentMethod(radio) {
    document.querySelectorAll('.payment-method-card').forEach(card => {
      card.classList.remove('border-2', 'border-rose-500', 'bg-rose-50/30');
      card.classList.add('border-[#FCE4E8]', 'bg-white');
      const dot = card.querySelector('.rounded-full.border-2');
      if (dot) {
        dot.className = 'w-4 h-4 rounded-full border-2 border-slate-300 flex items-center justify-center';
        const inner = dot.querySelector('div');
        if (inner) inner.classList.add('hidden');
      }
    });

    const parentCard = radio.closest('.payment-method-card');
    parentCard.classList.remove('border-[#FCE4E8]', 'bg-white');
    parentCard.classList.add('border-2', 'border-rose-500', 'bg-rose-50/30');
    const dot = parentCard.querySelector('.rounded-full.border-2');
    if (dot) {
      dot.className = 'w-4 h-4 rounded-full border-2 border-rose-500 bg-rose-500 flex items-center justify-center';
      const inner = dot.querySelector('div');
      if (inner) inner.classList.remove('hidden');
    }

    currentGateway = {
      code: radio.value,
      name: radio.dataset.name,
      min: parseFloat(radio.dataset.min) || 5,
      max: parseFloat(radio.dataset.max) || 5000,
      fee: parseFloat(radio.dataset.fee) || 0,
      currency: radio.dataset.currency || 'USD',
      isManual: radio.dataset.isManual === '1',
      parameters: radio.dataset.parameters || '',
      desc: radio.dataset.desc || ''
    };

    // Update notice box
    document.getElementById('gateway-notice-text').textContent = currentGateway.desc || `Payments via ${currentGateway.name} are processed in ${currentGateway.currency}.`;

    // Update limits
    document.getElementById('deposit-amount').min = currentGateway.min;
    document.getElementById('deposit-amount').max = currentGateway.max;
    document.getElementById('amount-limits-label').textContent = `Min: $${currentGateway.min.toFixed(2)} • Max: $${currentGateway.max.toFixed(2)}`;

    // Show/hide manual bank container
    const manualContainer = document.getElementById('manual-transfer-container');
    if (currentGateway.isManual) {
      manualContainer.classList.remove('hidden');
      document.getElementById('manual-transfer-details').textContent = currentGateway.parameters || 'Please transfer the desired deposit amount to our verified account. Include your RoseSMM username as transfer memo.';
      document.getElementById('deposit-btn-text').textContent = 'Submit Manual Transfer Request';
    } else {
      manualContainer.classList.add('hidden');
      document.getElementById('deposit-btn-text').textContent = 'Proceed to Secure Payment';
    }

    calculateBonus();
  }

  function setDepositAmount(val) {
    document.getElementById('deposit-amount').value = val;
    calculateBonus();
  }

  function calculateBonus() {
    const amt = parseFloat(document.getElementById('deposit-amount').value) || 0;
    const bonus = amt * bonusRate;
    const total = amt + bonus;

    document.getElementById('summary-deposit-amount').textContent = '$' + amt.toFixed(2);
    const bonusElem = document.getElementById('summary-bonus-amount');
    if (bonusElem) bonusElem.textContent = '+$' + bonus.toFixed(2);
    document.getElementById('summary-total-credited').textContent = '$' + total.toFixed(2);

    // Fee display
    if (currentGateway && currentGateway.fee > 0) {
      const feeRow = document.getElementById('fee-summary-row');
      const feeAmt = (currentGateway.fee / 100.0) * amt;
      feeRow.classList.remove('hidden');
      document.getElementById('summary-fee-amount').textContent = '+$' + feeAmt.toFixed(2) + ` (${currentGateway.fee}%)`;
    } else {
      const feeRow = document.getElementById('fee-summary-row');
      if (feeRow) feeRow.classList.add('hidden');
    }
  }

  function handleDepositSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('deposit-submit-btn');
    const amt = parseFloat(document.getElementById('deposit-amount').value) || 0;
    const activeRadio = document.querySelector('input[name="gateway_code"]:checked');

    if (!activeRadio) {
      alert('Please select an active payment method.');
      return;
    }

    const gwCode = activeRadio.value;
    const minAmt = parseFloat(activeRadio.dataset.min) || 5;
    const maxAmt = parseFloat(activeRadio.dataset.max) || 5000;

    if (amt < minAmt) {
      alert(`Minimum deposit amount for this method is $${minAmt.toFixed(2)}`);
      return;
    }

    if (amt > maxAmt) {
      alert(`Maximum deposit amount for this method is $${maxAmt.toFixed(2)}`);
      return;
    }

    let manualRef = '';
    if (activeRadio.dataset.isManual === '1') {
      manualRef = document.getElementById('manual-reference-input').value.trim();
      if (!manualRef) {
        alert('Please enter your transaction reference ID, UTR, or sender name for verification.');
        document.getElementById('manual-reference-input').focus();
        return;
      }
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Initializing Payment...';

    fetch('/payment/initiate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        gateway_code: gwCode,
        amount: amt,
        manual_reference: manualRef
      })
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<span id="deposit-btn-text">Proceed to Payment</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
      if (window.lucide) lucide.createIcons();

      const alertBox = document.getElementById('deposit-alert');
      const alertText = document.getElementById('deposit-alert-text');
      const alertIcon = document.getElementById('deposit-alert-icon');

      if (data.success) {
        if (data.manual) {
          // Manual payment submitted for approval
          alertBox.className = 'mb-6 p-4 rounded-2xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm';
          alertText.textContent = data.message || 'Deposit submitted successfully! Awaiting verification.';
          alertBox.classList.remove('hidden');
          setTimeout(() => {
            window.location.href = data.redirect_url || '/wallet';
          }, 2000);
        } else if (data.redirect_url) {
          // Real gateway redirect
          window.location.href = data.redirect_url;
        }
      } else {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-rose-50 border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-sm';
        alertText.textContent = data.error || 'Failed to initialize payment gateway.';
        alertBox.classList.remove('hidden');
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<span>Proceed to Payment</span>';
      alert('Communication error while contacting payment gateway. Please try again.');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const firstRadio = document.querySelector('input[name="gateway_code"]:checked');
    if (firstRadio) {
      selectPaymentMethod(firstRadio);
    }
  });
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
