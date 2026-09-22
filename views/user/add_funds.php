<?php
$pageTitle = 'Add Funds - RoseSMM';
$activePage = 'add-funds';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = $user['id'];
?>

<div class="max-w-4xl mx-auto">
  <div class="flex items-center justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Add Funds to Wallet</h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1">Deposit securely to your account balance and receive an instant 10% bonus.</p>
    </div>
    <div class="px-4 py-2 rounded-full border border-[#FCE4E8] bg-white text-xs font-bold text-slate-700">
      Current Balance: <span class="text-rose-600 font-extrabold"><?= format_price($user['balance']) ?></span>
    </div>
  </div>

  <!-- Special Offer Bonus Banner -->
  <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-[#FFE8EC] to-[#FFD5DE] border border-[#FCD3DC] mb-6 flex items-center justify-between gap-4 shadow-sm">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-sm">
        <i data-lucide="gift" class="w-6 h-6"></i>
      </div>
      <div>
        <h3 class="font-extrabold text-base text-slate-800">Special 10% Deposit Bonus Active!</h3>
        <p class="text-xs text-slate-600">Every deposit of $10 or more receives an extra 10% credited directly into your wallet.</p>
      </div>
    </div>
    <span class="hidden sm:inline-block px-3 py-1 rounded-full bg-rose-500 text-white text-xs font-bold shrink-0">
      +10% Bonus
    </span>
  </div>

  <div id="deposit-alert" class="hidden mb-6 p-4 rounded-2xl border text-sm flex items-center justify-between">
    <div class="flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
      <span id="deposit-alert-text"></span>
    </div>
    <button onclick="document.getElementById('deposit-alert').classList.add('hidden')">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Deposit Form (2 Cols) -->
    <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-3xl border border-[#FCE4E8] shadow-sm">
      <form id="add-funds-form" onsubmit="handleDepositSubmit(event)" class="space-y-5">
        <!-- Payment Method Selection -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-2">Select Payment Method</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5" id="payment-methods-grid">
            <label class="payment-method-card border-2 border-rose-500 bg-rose-50/30 p-3 rounded-2xl cursor-pointer flex flex-col items-center justify-center text-center transition-all">
              <input type="radio" name="payment_method" value="Stripe / Card" checked class="hidden" onchange="selectPaymentMethod(this)">
              <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center mb-1.5">
                <i data-lucide="credit-card" class="w-4 h-4"></i>
              </div>
              <span class="text-xs font-bold text-slate-800">Credit / Debit Card</span>
              <span class="text-[10px] text-slate-400">Instant</span>
            </label>

            <label class="payment-method-card border border-[#FCE4E8] bg-white p-3 rounded-2xl cursor-pointer flex flex-col items-center justify-center text-center transition-all hover:border-rose-300">
              <input type="radio" name="payment_method" value="PayPal" class="hidden" onchange="selectPaymentMethod(this)">
              <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-1.5">
                <i data-lucide="wallet" class="w-4 h-4"></i>
              </div>
              <span class="text-xs font-bold text-slate-800">PayPal</span>
              <span class="text-[10px] text-slate-400">Instant</span>
            </label>

            <label class="payment-method-card border border-[#FCE4E8] bg-white p-3 rounded-2xl cursor-pointer flex flex-col items-center justify-center text-center transition-all hover:border-rose-300">
              <input type="radio" name="payment_method" value="UPI (India)" class="hidden" onchange="selectPaymentMethod(this)">
              <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-1.5">
                <i data-lucide="smartphone" class="w-4 h-4"></i>
              </div>
              <span class="text-xs font-bold text-slate-800">UPI / QR (INR)</span>
              <span class="text-[10px] text-slate-400">0% Fee</span>
            </label>

            <label class="payment-method-card border border-[#FCE4E8] bg-white p-3 rounded-2xl cursor-pointer flex flex-col items-center justify-center text-center transition-all hover:border-rose-300">
              <input type="radio" name="payment_method" value="USDT / Crypto" class="hidden" onchange="selectPaymentMethod(this)">
              <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-1.5">
                <i data-lucide="bitcoin" class="w-4 h-4"></i>
              </div>
              <span class="text-xs font-bold text-slate-800">Crypto (USDT)</span>
              <span class="text-[10px] text-slate-400">TRC20/BEP20</span>
            </label>

            <label class="payment-method-card border border-[#FCE4E8] bg-white p-3 rounded-2xl cursor-pointer flex flex-col items-center justify-center text-center transition-all hover:border-rose-300">
              <input type="radio" name="payment_method" value="Bank Wire" class="hidden" onchange="selectPaymentMethod(this)">
              <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-1.5">
                <i data-lucide="building" class="w-4 h-4"></i>
              </div>
              <span class="text-xs font-bold text-slate-800">Bank Transfer</span>
              <span class="text-[10px] text-slate-400">Within 1 Hour</span>
            </label>
          </div>
        </div>

        <!-- Amount Input -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Deposit Amount (USD)</label>
          <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-extrabold text-slate-400">$</span>
            <input 
              type="number" 
              id="deposit-amount" 
              value="50" 
              min="5" 
              step="any"
              required 
              oninput="calculateBonus()" 
              class="w-full pl-9 pr-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-sm font-bold text-slate-800 focus:outline-none focus:border-rose-400"
            >
          </div>
          <!-- Quick Amount Buttons -->
          <div class="flex items-center gap-2 mt-2">
            <button type="button" onclick="setDepositAmount(10)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600">$10</button>
            <button type="button" onclick="setDepositAmount(25)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600">$25</button>
            <button type="button" onclick="setDepositAmount(50)" class="px-3 py-1 rounded-full text-xs font-bold border border-rose-400 text-rose-600 bg-rose-50">$50</button>
            <button type="button" onclick="setDepositAmount(100)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600">$100</button>
            <button type="button" onclick="setDepositAmount(250)" class="px-3 py-1 rounded-full text-xs font-bold border border-slate-200 text-slate-600 hover:border-rose-400 hover:text-rose-600">$250</button>
          </div>
        </div>

        <!-- Calculation summary -->
        <div class="p-4 rounded-2xl bg-rose-50/40 border border-[#FCE4E8] space-y-2 text-xs">
          <div class="flex items-center justify-between text-slate-600">
            <span>Deposit Amount:</span>
            <span id="summary-deposit-amount" class="font-bold text-slate-800">$50.00</span>
          </div>
          <div class="flex items-center justify-between text-emerald-600 font-bold">
            <span>Special 10% Bonus:</span>
            <span id="summary-bonus-amount">+$5.00</span>
          </div>
          <div class="border-t border-[#FCD3DC] pt-2 flex items-center justify-between text-sm font-extrabold text-slate-800">
            <span>Total Balance Credited:</span>
            <span id="summary-total-credited" class="text-rose-600 font-black">$55.00</span>
          </div>
        </div>

        <!-- Submit Button -->
        <button 
          type="submit" 
          id="deposit-submit-btn" 
          class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2"
        >
          <span>Pay & Credit Balance Now</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </form>
    </div>

    <!-- Security Information (1 Col) -->
    <div class="space-y-6">
      <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm">
        <h3 class="font-bold text-sm text-slate-800 mb-3 flex items-center gap-2">
          <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i>
          <span>100% Secure Payment</span>
        </h3>
        <p class="text-xs text-slate-500 leading-relaxed mb-4">
          All transactions are encrypted with 256-bit SSL technology. Your funds are instantly available in your wallet upon completion.
        </p>
        <div class="flex items-center gap-2 text-slate-400">
          <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">VISA</span>
          <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">Mastercard</span>
          <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">UPI</span>
          <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">USDT</span>
        </div>
      </div>

      <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm">
        <h3 class="font-bold text-sm text-slate-800 mb-2">Need Payment Help?</h3>
        <p class="text-xs text-slate-500 leading-relaxed mb-3">
          If your deposit does not show immediately, please open a ticket with your transaction ID.
        </p>
        <a href="/support" class="text-xs font-bold text-rose-500 hover:text-rose-600 flex items-center gap-1">
          <span>Open Support Ticket</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  let selectedMethod = 'Stripe / Card';

  function selectPaymentMethod(radio) {
    selectedMethod = radio.value;
    document.querySelectorAll('.payment-method-card').forEach(card => {
      card.classList.remove('border-2', 'border-rose-500', 'bg-rose-50/30');
      card.classList.add('border', 'border-[#FCE4E8]', 'bg-white');
    });
    const parentCard = radio.closest('.payment-method-card');
    parentCard.classList.remove('border', 'border-[#FCE4E8]', 'bg-white');
    parentCard.classList.add('border-2', 'border-rose-500', 'bg-rose-50/30');
  }

  function setDepositAmount(val) {
    document.getElementById('deposit-amount').value = val;
    calculateBonus();
  }

  function calculateBonus() {
    const amt = parseFloat(document.getElementById('deposit-amount').value) || 0;
    const bonus = amt * 0.10;
    const total = amt + bonus;

    document.getElementById('summary-deposit-amount').textContent = '$' + amt.toFixed(2);
    document.getElementById('summary-bonus-amount').textContent = '+$' + bonus.toFixed(2);
    document.getElementById('summary-total-credited').textContent = '$' + total.toFixed(2);
  }

  function handleDepositSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('deposit-submit-btn');
    const amt = parseFloat(document.getElementById('deposit-amount').value) || 0;

    if (amt < 5) {
      alert('Minimum deposit amount is $5.00');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Processing Deposit...';

    fetch('/api/funds/add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        amount: amt,
        payment_method: selectedMethod
      })
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<span>Pay & Credit Balance Now</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>';
      if (window.lucide) lucide.createIcons();

      const alertBox = document.getElementById('deposit-alert');
      const alertText = document.getElementById('deposit-alert-text');

      if (data.success) {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-sm flex items-center justify-between';
        alertText.textContent = data.message || 'Deposit processed successfully! Your wallet has been credited.';
        alertBox.classList.remove('hidden');

        setTimeout(() => {
          window.location.href = '/wallet';
        }, 1500);
      } else {
        alertBox.className = 'mb-6 p-4 rounded-2xl border bg-rose-50 border-rose-200 text-rose-800 text-sm flex items-center justify-between';
        alertText.textContent = data.error || 'Failed to process deposit.';
        alertBox.classList.remove('hidden');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<span>Pay & Credit Balance Now</span>';
      alert('Network error while processing deposit.');
    });
  }

  document.addEventListener('DOMContentLoaded', calculateBonus);
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
