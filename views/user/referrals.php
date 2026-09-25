<?php
$pageTitle = 'Refer & Earn - RoseSMM';
$activePage = 'referrals';
require_once __DIR__ . '/../layouts/user_header.php';
require_once __DIR__ . '/../../includes/ReferralHelper.php';

$userId = (int)$user['id'];
$userCurrency = $user['currency'] ?: 'USD';

// Get or create unique permanent referral code for the user
$userRefCode = ReferralHelper::getUserReferralCode($userId);
$referralUrl = ReferralHelper::getReferralUrl($userId);

// Fetch real MySQL referral statistics
$stats = ReferralHelper::getUserStats($userId);
$isProgramEnabled = $stats['is_enabled'];
$commissionPercent = $stats['commission_percent'];
$commissionEvent = $stats['commission_event'];
$totalReferrals = $stats['total_referrals'];
$activeReferrals = $stats['active_referrals'];
$totalEarnings = $stats['total_earnings'];
$referredUsers = $stats['referred_users'];
$transactions = $stats['transactions'];

$formattedEarnings = format_price($totalEarnings, $userCurrency, 'USD');
$formattedBalance = format_price($user['balance'], $userCurrency, 'USD');

// Encode share text
$siteBrand = get_site_name();
$shareText = "Join " . $siteBrand . ", the best social media marketing platform with instant automated delivery! Sign up with my referral link: " . $referralUrl;
$whatsappUrl = "https://api.whatsapp.com/send?text=" . urlencode($shareText);
$telegramUrl = "https://t.me/share/url?url=" . urlencode($referralUrl) . "&text=" . urlencode("Boost your social media with " . $siteBrand . "!");
$twitterUrl = "https://twitter.com/intent/tweet?text=" . urlencode($shareText);
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <div class="flex items-center gap-2 mb-1">
      <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Refer & Earn</h1>
      <?php if ($isProgramEnabled): ?>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          Active Program (<?= rtrim(rtrim(number_format($commissionPercent, 2), '0'), '.') ?>%)
        </span>
      <?php else: ?>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-200">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
          Program Paused
        </span>
      <?php endif; ?>
    </div>
    <p class="text-xs sm:text-sm text-slate-500">
      Invite friends and customers to <?= e($siteBrand) ?> and earn <strong><?= rtrim(rtrim(number_format($commissionPercent, 2), '0'), '.') ?>%</strong> real commission on their qualifying activity.
    </p>
  </div>

  <div class="flex items-center gap-2 w-full sm:w-auto">
    <a href="/wallet" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-white border border-[#FCE4E8] hover:bg-rose-50/50 text-slate-700 font-bold text-xs shadow-xs transition-colors w-full sm:w-auto">
      <i data-lucide="wallet" class="w-4 h-4 text-rose-500"></i>
      <span>Wallet: <?= $formattedBalance ?></span>
    </a>
  </div>
</div>

<?php if (!$isProgramEnabled): ?>
  <!-- Program Paused Alert -->
  <div class="mb-6 p-4 rounded-3xl bg-amber-50/80 border border-amber-200 text-amber-900 text-xs flex items-start gap-3 shadow-xs">
    <div class="w-8 h-8 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
      <i data-lucide="alert-triangle" class="w-4 h-4"></i>
    </div>
    <div class="min-w-0">
      <h4 class="font-bold text-sm text-amber-900 mb-0.5">Refer & Earn Program is Temporarily Paused</h4>
      <p class="text-amber-700 leading-relaxed">
        The administrator has temporarily paused new commission payouts. Your existing referrals, earnings, and wallet balances are safely preserved.
      </p>
    </div>
  </div>
<?php endif; ?>

<!-- Top Summary Cards -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

  <!-- Card 1: Referral Link & Code (Primary Action Card) -->
  <div class="lg:col-span-2 bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between gap-2 mb-4">
        <div class="flex items-center gap-2.5">
          <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center border border-rose-100 shrink-0">
            <i data-lucide="gift" class="w-5 h-5"></i>
          </div>
          <div>
            <h3 class="text-base font-black text-slate-800 tracking-tight">Your Unique Referral Link</h3>
            <p class="text-xs text-slate-400">Share this link to automatically track friends who register.</p>
          </div>
        </div>
        <span class="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-100 shrink-0">
          <?= rtrim(rtrim(number_format($commissionPercent, 2), '0'), '.') ?>% Commission
        </span>
      </div>

      <!-- Referral URL Copy Box -->
      <div class="mb-4">
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Referral URL</label>
        <div class="flex items-center gap-2">
          <div class="relative flex-1 min-w-0">
            <input 
              type="text" 
              id="referral-url-input" 
              readonly 
              value="<?= e($referralUrl) ?>" 
              class="w-full pl-3.5 pr-10 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs font-mono font-medium text-slate-700 select-all focus:outline-none focus:border-rose-400 truncate"
            >
            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
              <i data-lucide="link" class="w-4 h-4"></i>
            </div>
          </div>
          <button 
            type="button" 
            id="copy-url-btn" 
            onclick="copyReferralLink()"
            class="px-4 py-2.5 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-xs shadow-xs transition-all flex items-center gap-1.5 shrink-0 active:scale-95 cursor-pointer"
          >
            <i data-lucide="copy" class="w-3.5 h-3.5" id="copy-url-icon"></i>
            <span id="copy-url-text">Copy Link</span>
          </button>
        </div>
      </div>

      <!-- Referral Code Box & Quick Share -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100">
        <div>
          <span class="text-xs text-slate-400 font-semibold block mb-1">Your Referral Code</span>
          <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-800 font-mono font-bold text-sm tracking-wider border border-slate-200">
              <?= e($userRefCode) ?>
            </span>
            <button 
              type="button" 
              onclick="copyCodeOnly('<?= e($userRefCode) ?>')" 
              class="text-xs font-bold text-rose-600 hover:text-rose-700 p-1.5 rounded-lg hover:bg-rose-50 transition-colors"
              title="Copy code only"
            >
              <i data-lucide="copy" class="w-4 h-4"></i>
            </button>
          </div>
        </div>

        <!-- Social Sharing -->
        <div>
          <span class="text-xs text-slate-400 font-semibold block mb-1">Quick Share</span>
          <div class="flex items-center gap-2">
            <a 
              href="<?= e($whatsappUrl) ?>" 
              target="_blank" 
              rel="noopener noreferrer"
              class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border border-emerald-200 flex items-center justify-center transition-colors shadow-2xs"
              title="Share on WhatsApp"
            >
              <i data-lucide="message-circle" class="w-4 h-4"></i>
            </a>
            <a 
              href="<?= e($telegramUrl) ?>" 
              target="_blank" 
              rel="noopener noreferrer"
              class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 hover:bg-sky-100 border border-sky-200 flex items-center justify-center transition-colors shadow-2xs"
              title="Share on Telegram"
            >
              <i data-lucide="send" class="w-4 h-4"></i>
            </a>
            <a 
              href="<?= e($twitterUrl) ?>" 
              target="_blank" 
              rel="noopener noreferrer"
              class="w-9 h-9 rounded-xl bg-slate-100 text-slate-800 hover:bg-slate-200 border border-slate-200 flex items-center justify-center transition-colors shadow-2xs"
              title="Share on X (Twitter)"
            >
              <span class="font-bold text-xs">𝕏</span>
            </a>
            <button 
              type="button" 
              onclick="nativeShare()" 
              class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 flex items-center justify-center transition-colors shadow-2xs"
              title="Share via device"
            >
              <i data-lucide="share-2" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Earnings & Wallet Metric -->
  <div class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-3xl p-6 text-white shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-4">
        <span class="text-xs uppercase tracking-wider text-rose-100 font-semibold block">Total Referral Earnings</span>
        <div class="w-9 h-9 rounded-xl bg-white/15 backdrop-blur-xs flex items-center justify-center text-white">
          <i data-lucide="trending-up" class="w-4 h-4"></i>
        </div>
      </div>
      <div class="text-3xl sm:text-4xl font-black tracking-tight truncate mb-1">
        <?= $formattedEarnings ?>
      </div>
      <p class="text-xs text-rose-100/90 leading-relaxed">
        Real earnings credited directly to your <?= e($siteBrand) ?> wallet balance.
      </p>
    </div>

    <div class="mt-6 pt-4 border-t border-white/20 flex items-center justify-between text-xs">
      <div>
        <span class="text-rose-100/80 block text-[11px]">Available Wallet Balance</span>
        <span class="font-bold text-white text-sm"><?= $formattedBalance ?></span>
      </div>
      <a href="/wallet" class="px-3 py-1.5 rounded-full bg-white text-rose-600 font-bold text-xs hover:bg-rose-50 transition-colors shadow-xs">
        View Wallet &rarr;
      </a>
    </div>
  </div>

</div>

<!-- Real Stats Breakdown (3 Cards) -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <!-- Total Referred -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-5 shadow-sm flex items-center gap-4">
    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0">
      <i data-lucide="users" class="w-6 h-6"></i>
    </div>
    <div class="min-w-0">
      <span class="text-xs font-semibold text-slate-400 block truncate">Total Friends Referred</span>
      <div class="text-2xl font-black text-slate-800 tracking-tight"><?= number_format($totalReferrals) ?></div>
      <span class="text-[11px] text-slate-500 font-medium">Registered via your link</span>
    </div>
  </div>

  <!-- Active Referrals -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-5 shadow-sm flex items-center gap-4">
    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
      <i data-lucide="user-check" class="w-6 h-6"></i>
    </div>
    <div class="min-w-0">
      <span class="text-xs font-semibold text-slate-400 block truncate">Active Referrals</span>
      <div class="text-2xl font-black text-slate-800 tracking-tight"><?= number_format($activeReferrals) ?></div>
      <span class="text-[11px] text-emerald-600 font-medium">Placed orders or deposits</span>
    </div>
  </div>

  <!-- Commission Rate -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-5 shadow-sm flex items-center gap-4">
    <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
      <i data-lucide="percent" class="w-6 h-6"></i>
    </div>
    <div class="min-w-0">
      <span class="text-xs font-semibold text-slate-400 block truncate">Current Commission Rate</span>
      <div class="text-2xl font-black text-slate-800 tracking-tight"><?= rtrim(rtrim(number_format($commissionPercent, 2), '0'), '.') ?>%</div>
      <span class="text-[11px] text-slate-500 font-medium">
        On <?= $commissionEvent === 'both' ? 'orders & deposits' : ($commissionEvent === 'deposit' ? 'deposits' : 'all orders') ?>
      </span>
    </div>
  </div>
</div>

<!-- How It Works Card -->
<div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm mb-8">
  <div class="mb-4">
    <h3 class="text-base font-black text-slate-800 tracking-tight">How Refer & Earn Works</h3>
    <p class="text-xs text-slate-500">Three simple steps to generate passive earnings with <?= e($siteBrand) ?>.</p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-4 rounded-2xl bg-rose-50/30 border border-rose-100/80 flex items-start gap-3.5">
      <div class="w-8 h-8 rounded-xl bg-rose-500 text-white font-bold text-xs flex items-center justify-center shrink-0">
        1
      </div>
      <div>
        <h4 class="text-xs font-bold text-slate-800 mb-1">Share Your Link</h4>
        <p class="text-xs text-slate-500 leading-relaxed">
          Copy your referral link and send it to friends, followers, or clients on social media.
        </p>
      </div>
    </div>

    <div class="p-4 rounded-2xl bg-rose-50/30 border border-rose-100/80 flex items-start gap-3.5">
      <div class="w-8 h-8 rounded-xl bg-rose-500 text-white font-bold text-xs flex items-center justify-center shrink-0">
        2
      </div>
      <div>
        <h4 class="text-xs font-bold text-slate-800 mb-1">They Register</h4>
        <p class="text-xs text-slate-500 leading-relaxed">
          When they register an account, they are permanently attributed to your profile in our database.
        </p>
      </div>
    </div>

    <div class="p-4 rounded-2xl bg-rose-50/30 border border-rose-100/80 flex items-start gap-3.5">
      <div class="w-8 h-8 rounded-xl bg-rose-500 text-white font-bold text-xs flex items-center justify-center shrink-0">
        3
      </div>
      <div>
        <h4 class="text-xs font-bold text-slate-800 mb-1">Earn Automatic Wallet Credits</h4>
        <p class="text-xs text-slate-500 leading-relaxed">
          Whenever they place qualifying orders, <?= rtrim(rtrim(number_format($commissionPercent, 2), '0'), '.') ?>% commission is credited instantly to your wallet.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- History & Activity Section (Cards UI) -->
<div class="space-y-6">

  <!-- Section 1: Referral Earning Transactions -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-5">
      <div>
        <h3 class="text-base font-black text-slate-800 tracking-tight">Referral Commission History</h3>
        <p class="text-xs text-slate-500">Every commission earned from qualifying transactions.</p>
      </div>
      <span class="text-xs font-bold text-slate-400"><?= count($transactions) ?> Total Earning Events</span>
    </div>

    <?php if (empty($transactions)): ?>
      <!-- Zero State for Commissions -->
      <div class="p-10 rounded-2xl border border-dashed border-slate-200 text-center max-w-md mx-auto my-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-3">
          <i data-lucide="sparkles" class="w-6 h-6"></i>
        </div>
        <h4 class="text-sm font-bold text-slate-800 mb-1">No Referral Commissions Yet</h4>
        <p class="text-xs text-slate-400 mb-4 leading-relaxed">
          Share your referral link with friends. As soon as they make their first qualifying order or deposit, your earnings will appear right here.
        </p>
        <button 
          type="button" 
          onclick="copyReferralLink()"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition-colors cursor-pointer"
        >
          <i data-lucide="copy" class="w-3.5 h-3.5"></i>
          <span>Copy Your Referral Link</span>
        </button>
      </div>
    <?php else: ?>
      <!-- Responsive Cards Grid for Transactions -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($transactions as $tx): ?>
          <?php 
            $commAmountFormatted = format_price((float)$tx['commission_amount'], $userCurrency, $tx['currency'] ?: 'USD');
            $baseAmountFormatted = format_price((float)$tx['base_amount'], $userCurrency, $tx['currency'] ?: 'USD');
            $sourceLabel = ucfirst($tx['source_type'] ?? 'Order');
          ?>
          <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-white hover:border-rose-200 transition-all shadow-2xs">
            <div class="flex items-start justify-between gap-3 mb-2.5">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                  <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                </div>
                <div>
                  <div class="text-xs font-bold text-slate-800">
                    +<?= $commAmountFormatted ?> Commission
                  </div>
                  <div class="text-[11px] text-slate-500">
                    From @<?= e($tx['referred_username'] ?: 'User #' . $tx['referred_id']) ?>
                  </div>
                </div>
              </div>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                Completed
              </span>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-2.5 border-t border-slate-200/60 text-[11px]">
              <div>
                <span class="text-slate-400 block">Trigger Source</span>
                <span class="font-semibold text-slate-700"><?= e($sourceLabel) ?> #<?= e($tx['source_id']) ?></span>
              </div>
              <div>
                <span class="text-slate-400 block">Qualifying Base</span>
                <span class="font-semibold text-slate-700"><?= $baseAmountFormatted ?> (<?= (float)$tx['commission_percent'] ?>%)</span>
              </div>
              <div class="col-span-2 text-slate-400 text-[10px] pt-1">
                <?= date('M d, Y · h:i A', strtotime($tx['created_at'])) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Section 2: Referred Users List -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-5">
      <div>
        <h3 class="text-base font-black text-slate-800 tracking-tight">Your Referred Friends & Customers</h3>
        <p class="text-xs text-slate-500">Users who joined <?= e($siteBrand) ?> using your link.</p>
      </div>
      <span class="text-xs font-bold text-slate-400"><?= count($referredUsers) ?> Total Registered</span>
    </div>

    <?php if (empty($referredUsers)): ?>
      <div class="p-8 rounded-2xl border border-dashed border-slate-200 text-center max-w-md mx-auto my-2">
        <p class="text-xs text-slate-400">You haven't referred any users yet. Share your link to get started!</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($referredUsers as $refUser): ?>
          <?php 
            $commGen = format_price((float)$refUser['commission_generated'], $userCurrency, 'USD');
          ?>
          <div class="p-4 rounded-2xl border border-slate-100 bg-white shadow-2xs hover:border-rose-200 transition-all flex flex-col justify-between">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-rose-400 to-rose-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                <?= strtoupper(substr($refUser['username'], 0, 2)) ?>
              </div>
              <div class="min-w-0">
                <div class="font-bold text-xs text-slate-800 truncate">@<?= e($refUser['username']) ?></div>
                <div class="text-[11px] text-slate-400">Joined <?= date('M d, Y', strtotime($refUser['created_at'])) ?></div>
              </div>
            </div>

            <div class="pt-2 border-t border-slate-100 grid grid-cols-2 gap-2 text-[11px]">
              <div>
                <span class="text-slate-400 block text-[10px]">Orders Placed</span>
                <span class="font-bold text-slate-700"><?= (int)$refUser['total_orders'] ?></span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px]">Commission</span>
                <span class="font-bold text-emerald-600"><?= $commGen ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Copy and Share Scripts -->
<script>
function copyReferralLink() {
  const urlInput = document.getElementById('referral-url-input');
  const btnText = document.getElementById('copy-url-text');
  const btnIcon = document.getElementById('copy-url-icon');
  const textToCopy = urlInput.value;

  copyStringToClipboard(textToCopy, function() {
    btnText.innerText = 'Copied!';
    if (btnIcon) {
      btnIcon.setAttribute('data-lucide', 'check');
      if (window.lucide) lucide.createIcons();
    }
    setTimeout(function() {
      btnText.innerText = 'Copy Link';
      if (btnIcon) {
        btnIcon.setAttribute('data-lucide', 'copy');
        if (window.lucide) lucide.createIcons();
      }
    }, 2500);
  });
}

function copyCodeOnly(code) {
  copyStringToClipboard(code, function() {
    alert('Referral code copied: ' + code);
  });
}

function copyStringToClipboard(text, onSuccess) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(function() {
      if (onSuccess) onSuccess();
    }).catch(function() {
      fallbackCopyText(text, onSuccess);
    });
  } else {
    fallbackCopyText(text, onSuccess);
  }
}

function fallbackCopyText(text, onSuccess) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.left = '-999999px';
  textArea.style.top = '-999999px';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
    textArea.remove();
    if (onSuccess) onSuccess();
  } catch (err) {
    textArea.remove();
    prompt('Copy to clipboard:', text);
  }
}

function nativeShare() {
  const url = document.getElementById('referral-url-input').value;
  if (navigator.share) {
    navigator.share({
      title: 'Join ' + <?= json_encode($siteBrand) ?>,
      text: 'Boost your social media with ' + <?= json_encode($siteBrand) ?> + '. Use my referral link:',
      url: url
    }).catch(function() {});
  } else {
    copyReferralLink();
  }
}
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
