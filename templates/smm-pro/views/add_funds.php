<?php
$pageTitle = 'Add Funds - SMM Pro';
$activePage = 'add-funds';
require_once __DIR__ . '/../layouts/header.php';

$db = getDB();
$userId = (int)$user['id'];

// Real active payment gateways from MySQL
$gatewaysStmt = $db->query("SELECT * FROM payment_gateways WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
$activeGateways = $gatewaysStmt->fetchAll();

$bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
?>

<div class="max-w-4xl mx-auto space-y-6">
  <!-- Top Bar -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="credit-card" class="w-6 h-6 text-[#FF2D78]"></i>
        Add Funds
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Instant, automated wallet deposits with encrypted SSL security.</p>
    </div>
    <div class="px-4 py-2 rounded-2xl bg-[#131322] border border-white/10 text-xs font-bold text-white shadow-sm flex items-center gap-2">
      <span class="text-[#9D9DB8]">Current Balance:</span>
      <span class="text-[#FF2D78] font-black text-sm"><?= format_price($user['balance']) ?></span>
    </div>
  </div>

  <?php if ($bonusPercent > 0): ?>
    <!-- Bonus Banner -->
    <div class="p-5 rounded-3xl bg-gradient-to-r from-[#FF2D78]/20 via-[#D91B5C]/15 to-[#131322] border border-[#FF2D78]/40 flex items-center justify-between gap-4 shadow-lg shadow-[#FF2D78]/10">
      <div class="flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-2xl bg-[#FF2D78] text-white flex items-center justify-center shrink-0 shadow-md shadow-[#FF2D78]/40">
          <i data-lucide="gift" class="w-5 h-5"></i>
        </div>
        <div>
          <h3 class="font-black text-sm text-white"><?= number_format($bonusPercent, 0) ?>% Automatic Deposit Bonus Active!</h3>
          <p class="text-xs text-[#9D9DB8]">Every deposit receives an extra <?= number_format($bonusPercent, 0) ?>% automatically credited to your wallet balance.</p>
        </div>
      </div>
      <span class="hidden sm:inline-block px-3 py-1 rounded-full bg-[#FF2D78] text-white text-xs font-black shrink-0 shadow-md">
        +<?= number_format($bonusPercent, 0) ?>% Extra
      </span>
    </div>
  <?php endif; ?>

  <!-- Alert Box -->
  <div id="deposit-alert" class="hidden p-4 rounded-2xl border text-xs font-bold flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-2">
      <i id="deposit-alert-icon" data-lucide="info" class="w-4 h-4 shrink-0"></i>
      <span id="deposit-alert-text"></span>
    </div>
    <button onclick="document.getElementById('deposit-alert').classList.add('hidden')">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  </div>

  <?php if (empty($activeGateways)): ?>
    <div class="smm-card p-10 text-center max-w-lg mx-auto">
      <div class="w-14 h-14 rounded-2xl bg-white/5 text-[#FF2D78] flex items-center justify-center mx-auto mb-3">
        <i data-lucide="credit-card" class="w-7 h-7"></i>
      </div>
      <h2 class="text-base font-bold text-white mb-2">Gateways Under Maintenance</h2>
      <p class="text-xs text-[#9D9DB8] mb-5">Payment processing is temporarily offline. Please reach out to support for manual balance top-ups.</p>
      <a href="/support" class="smm-btn-pink px-5 py-2 text-xs">Contact Support</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Deposit Form (7 Cols) -->
      <div class="lg:col-span-7 smm-card p-6 sm:p-7 space-y-5">
        <form id="add-funds-form" onsubmit="handleDepositSubmit(event)" class="space-y-5">
          <!-- Payment Method Selection -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-3">1. Select Payment Gateway</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="payment-methods-grid">
              <?php foreach ($activeGateways as $idx => $gw): 
                $isFirst = ($idx === 0);
              ?>
                <label class="payment-method-card border <?= $isFirst ? 'border-2 border-[#FF2D78] bg-[#FF2D78]/10' : 'border-white/10 bg-[#161628]' ?> p-3.5 rounded-2xl cursor-pointer flex items-center justify-between transition-all hover:border-[#FF2D78]/50">
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
                    <div class="w-9 h-9 rounded-xl <?= $isFirst ? 'bg-[#FF2D78] text-white' : 'bg-white/5 text-[#9D9DB8]' ?> flex items-center justify-center shrink-0 shadow-sm">
                      <i data-lucide="credit-card" class="w-4.5 h-4.5"></i>
                    </div>
                    <div>
                      <span class="text-xs font-bold text-white block leading-tight"><?= e($gw['name']) ?></span>
                      <span class="text-[10px] text-[#9D9DB8] mt-0.5 block">
                        Min: $<?= number_format($gw['min_amount'], 2) ?> • <?= $gw['fee_percent'] > 0 ? number_format($gw['fee_percent'], 1) . '% Fee' : '0% Fee' ?>
                      </span>
                    </div>
                  </div>
                  <div class="w-4 h-4 rounded-full border-2 <?= $isFirst ? 'border-[#FF2D78] bg-[#FF2D78]' : 'border-white/20' ?> flex items-center justify-center">
                    <div class="w-1.5 h-1.5 rounded-full bg-white <?= $isFirst ? '' : 'hidden' ?>"></div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Amount Input -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-2">2. Deposit Amount ($ USD)</label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-white font-bold text-base">$</span>
              <input 
                type="number" 
                id="deposit-amount" 
                step="0.01" 
                min="1" 
                value="25.00" 
                oninput="updateDepositSummary()" 
                required 
                class="smm-input pl-8 text-base font-bold"
              >
            </div>
          </div>

          <!-- Manual Transfer Reference Input (shown only if manual gateway) -->
          <div id="manual-reference-group" class="hidden">
            <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-2">3. Transaction UTR / Reference ID</label>
            <input 
              type="text" 
              id="manual-reference-input" 
              placeholder="Enter bank transaction reference or UTR" 
              class="smm-input text-xs"
            >
          </div>

          <!-- Total Calculation Box -->
          <div class="p-4 rounded-2xl bg-[#18182D] border border-white/10 space-y-2 text-xs">
            <div class="flex justify-between text-[#9D9DB8]">
              <span>Deposit Amount:</span>
              <span class="text-white font-bold" id="summary-base-amt">$25.00</span>
            </div>
            <div class="flex justify-between text-[#9D9DB8]" id="summary-fee-row">
              <span>Gateway Fee:</span>
              <span class="text-[#FF2D78] font-bold" id="summary-fee-amt">$0.00</span>
            </div>
            <?php if ($bonusPercent > 0): ?>
              <div class="flex justify-between text-[#10B981]">
                <span>Bonus (+<?= number_format($bonusPercent, 0) ?>%):</span>
                <span class="font-bold" id="summary-bonus-amt">+$2.50</span>
              </div>
            <?php endif; ?>
            <div class="pt-2 border-t border-white/5 flex justify-between text-sm font-black">
              <span class="text-white">Total Credited:</span>
              <span class="text-[#34D399]" id="summary-credit-amt">$27.50</span>
            </div>
          </div>

          <button 
            type="submit" 
            id="deposit-submit-btn" 
            class="smm-btn-pink w-full py-3.5 text-sm font-black"
          >
            <span id="deposit-btn-text">Proceed to Payment</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
        </form>
      </div>

      <!-- Information & Security Card (5 Cols) -->
      <div class="lg:col-span-5 space-y-4">
        <div class="smm-card p-6 space-y-4">
          <h3 class="text-sm font-black text-white flex items-center gap-2 border-b border-white/5 pb-3">
            <i data-lucide="shield-check" class="w-4.5 h-4.5 text-[#10B981]"></i>
            Payment Security & Guarantee
          </h3>
          <ul class="text-xs text-[#9D9DB8] space-y-2.5">
            <li class="flex items-start gap-2">
              <i data-lucide="check" class="w-4 h-4 text-[#10B981] shrink-0 mt-0.5"></i>
              <span>All payments are encrypted via 256-bit bank-grade SSL security.</span>
            </li>
            <li class="flex items-start gap-2">
              <i data-lucide="check" class="w-4 h-4 text-[#10B981] shrink-0 mt-0.5"></i>
              <span>Funds are credited instantly upon successful gateway confirmation.</span>
            </li>
            <li class="flex items-start gap-2">
              <i data-lucide="check" class="w-4 h-4 text-[#10B981] shrink-0 mt-0.5"></i>
              <span>Zero hidden fees or recurring subscriptions. One-time credits only.</span>
            </li>
          </ul>
        </div>

        <div class="smm-card p-5 border-[#FF2D78]/20 bg-gradient-to-br from-[#131322] to-[#18182D]">
          <div class="text-xs font-black text-white uppercase tracking-wider mb-2 flex items-center gap-2">
            <i data-lucide="help-circle" class="w-4 h-4 text-[#FF2D78]"></i>
            Need Assistance?
          </div>
          <p class="text-xs text-[#9D9DB8] leading-relaxed mb-3">
            If your balance was not automatically credited within 5 minutes, our 24/7 billing team will verify it immediately.
          </p>
          <a href="/support" class="smm-btn-dark w-full py-2 text-xs">
            Open Support Ticket
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
const bonusPct = <?= (float)$bonusPercent ?>;

function selectPaymentMethod(radio) {
  document.querySelectorAll('.payment-method-card').forEach(card => {
    card.className = 'payment-method-card border border-white/10 bg-[#161628] p-3.5 rounded-2xl cursor-pointer flex items-center justify-between transition-all hover:border-[#FF2D78]/50';
    const dot = card.querySelector('.w-1\\.5');
    if (dot) dot.classList.add('hidden');
    const outer = card.querySelector('.w-4');
    if (outer) outer.className = 'w-4 h-4 rounded-full border-2 border-white/20 flex items-center justify-center';
  });

  const parentLabel = radio.closest('label');
  if (parentLabel) {
    parentLabel.className = 'payment-method-card border-2 border-[#FF2D78] bg-[#FF2D78]/10 p-3.5 rounded-2xl cursor-pointer flex items-center justify-between transition-all';
    const dot = parentLabel.querySelector('.w-1\\.5');
    if (dot) dot.classList.remove('hidden');
    const outer = parentLabel.querySelector('.w-4');
    if (outer) outer.className = 'w-4 h-4 rounded-full border-2 border-[#FF2D78] bg-[#FF2D78] flex items-center justify-center';
  }

  const manualGroup = document.getElementById('manual-reference-group');
  if (manualGroup) {
    if (radio.dataset.isManual === '1') {
      manualGroup.classList.remove('hidden');
    } else {
      manualGroup.classList.add('hidden');
    }
  }

  updateDepositSummary();
}

function updateDepositSummary() {
  const amt = parseFloat(document.getElementById('deposit-amount').value) || 0;
  const activeRadio = document.querySelector('input[name="gateway_code"]:checked');
  if (!activeRadio) return;

  const feePct = parseFloat(activeRadio.dataset.fee) || 0;
  const feeAmt = (amt * feePct) / 100;
  const bonusAmt = (amt * bonusPct) / 100;
  const creditAmt = amt + bonusAmt;

  document.getElementById('summary-base-amt').textContent = '$' + amt.toFixed(2);
  document.getElementById('summary-fee-amt').textContent = '$' + feeAmt.toFixed(2);
  const bonusEl = document.getElementById('summary-bonus-amt');
  if (bonusEl) bonusEl.textContent = '+$' + bonusAmt.toFixed(2);
  document.getElementById('summary-credit-amt').textContent = '$' + creditAmt.toFixed(2);
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
  let manualRef = '';
  if (activeRadio.dataset.isManual === '1') {
    manualRef = document.getElementById('manual-reference-input').value.trim();
    if (!manualRef) {
      alert('Please enter your transaction reference ID or UTR for verification.');
      document.getElementById('manual-reference-input').focus();
      return;
    }
  }

  btn.disabled = true;
  btn.innerHTML = '<span>Processing Payment...</span>';

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
    btn.innerHTML = '<span>Proceed to Payment</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
    if (window.lucide) lucide.createIcons();

    const alertBox = document.getElementById('deposit-alert');
    const alertText = document.getElementById('deposit-alert-text');

    if (data.success) {
      if (data.manual) {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-[#10B981]/15 border-[#10B981]/30 text-[#34D399] text-xs font-bold flex items-center justify-between';
        alertText.textContent = data.message || 'Deposit submitted for approval!';
        alertBox.classList.remove('hidden');
        setTimeout(() => { window.location.href = '/wallet'; }, 1500);
      } else if (data.redirect_url) {
        window.location.href = data.redirect_url;
      }
    } else {
      alertBox.className = 'mb-6 p-4 rounded-2xl border bg-[#EF4444]/15 border-[#EF4444]/30 text-[#F87171] text-xs font-bold flex items-center justify-between';
      alertText.textContent = data.error || 'Failed to initialize payment.';
      alertBox.classList.remove('hidden');
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<span>Proceed to Payment</span>';
    alert('Communication error contacting payment gateway.');
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const firstRadio = document.querySelector('input[name="gateway_code"]:checked');
  if (firstRadio) selectPaymentMethod(firstRadio);
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
