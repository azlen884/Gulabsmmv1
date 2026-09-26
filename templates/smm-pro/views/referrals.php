<?php
$pageTitle = 'Refer & Earn - SMM Pro';
$activePage = 'referrals';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../../includes/ReferralHelper.php';

$userId = (int)$user['id'];
$userCurrency = $user['currency'] ?: 'USD';

$userRefCode = ReferralHelper::getUserReferralCode($userId);
$referralUrl = ReferralHelper::getReferralUrl($userId);

$stats = ReferralHelper::getUserStats($userId);
$isProgramEnabled = $stats['is_enabled'];
$commissionPercent = $stats['commission_percent'];
$totalReferrals = $stats['total_referrals'];
$totalEarnings = $stats['total_earnings'];
$referredUsers = $stats['referred_users'];
$transactions = $stats['transactions'];

$formattedEarnings = format_price($totalEarnings, $userCurrency, 'USD');
?>

<div class="space-y-6 max-w-6xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="share-2" class="w-6 h-6 text-[#FF2D78]"></i>
        Affiliate & Referrals
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Earn <?= number_format($commissionPercent, 0) ?>% recurring commissions on all deposits and orders placed by referred users.</p>
    </div>
    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-[#10B981]/15 text-[#34D399] border border-[#10B981]/30">
      <span class="w-2 h-2 rounded-full bg-[#10B981] animate-ping"></span>
      <?= number_format($commissionPercent, 0) ?>% Commission Active
    </span>
  </div>

  <!-- Referral Link Box -->
  <div class="smm-card p-6 sm:p-7 space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-black text-white uppercase tracking-wider">Your Exclusive Affiliate Referral Link</h3>
      <span class="text-xs text-[#9D9DB8]">Permanent Tracking Cookie</span>
    </div>
    <div class="flex gap-2">
      <input type="text" id="ref-link-input" readonly value="<?= e($referralUrl) ?>" class="smm-input font-mono text-xs text-white">
      <button type="button" onclick="copyRefLink()" id="copy-ref-btn" class="smm-btn-pink px-5 py-2 text-xs font-bold shrink-0">
        <i data-lucide="copy" class="w-4 h-4"></i>
        <span id="copy-ref-text">Copy Link</span>
      </button>
    </div>
  </div>

  <!-- Referral Stats Grid -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
    <div class="smm-card p-6 flex flex-col justify-between">
      <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8]">Total Referrals</span>
      <div class="text-2xl sm:text-3xl font-black text-white mt-2"><?= number_format($totalReferrals) ?> Users</div>
    </div>
    <div class="smm-card p-6 flex flex-col justify-between">
      <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8]">Total Earned</span>
      <div class="text-2xl sm:text-3xl font-black text-[#FF2D78] font-mono mt-2"><?= e($formattedEarnings) ?></div>
    </div>
    <div class="smm-card p-6 flex flex-col justify-between">
      <span class="text-xs font-bold uppercase tracking-wider text-[#9D9DB8]">Commission Rate</span>
      <div class="text-2xl sm:text-3xl font-black text-[#10B981] mt-2"><?= number_format($commissionPercent, 0) ?>%</div>
    </div>
  </div>

  <!-- Referred Users Table -->
  <div class="smm-card overflow-hidden">
    <div class="p-5 border-b border-white/5 flex items-center justify-between">
      <h3 class="text-base font-black text-white flex items-center gap-2">
        <i data-lucide="users" class="w-5 h-5 text-[#FF2D78]"></i>
        Referred Accounts
      </h3>
      <span class="text-xs text-[#9D9DB8]"><?= count($referredUsers) ?> User(s)</span>
    </div>

    <div class="overflow-x-auto">
      <table class="smm-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Joined Date</th>
            <th>Commission Generated</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($referredUsers)): ?>
            <tr>
              <td colspan="3" class="text-center py-10 text-[#9D9DB8]">
                No referred users yet. Share your referral link above to start earning!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($referredUsers as $ru): ?>
              <tr>
                <td class="font-bold text-white text-xs">@<?= e($ru['username']) ?></td>
                <td class="text-xs text-[#9D9DB8]"><?= date('M d, Y', strtotime($ru['created_at'])) ?></td>
                <td class="font-bold text-[#FF2D78] font-mono"><?= format_price($ru['earnings_generated'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function copyRefLink() {
  const input = document.getElementById('ref-link-input');
  input.select();
  navigator.clipboard.writeText(input.value).then(() => {
    const text = document.getElementById('copy-ref-text');
    text.textContent = 'Copied!';
    setTimeout(() => { text.textContent = 'Copy Link'; }, 2000);
  });
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
