<?php
$pageTitle = 'Settings - Admin Console';
$adminPage = 'settings';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'site_name' => trim($_POST['site_name'] ?? 'RoseSMM'),
        'support_email' => trim($_POST['support_email'] ?? 'support@rosesmm.com'),
        'deposit_bonus_percent' => trim($_POST['deposit_bonus_percent'] ?? '10'),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
    ];

    foreach ($settings as $key => $val) {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $val, $val]);
    }
    $msg = "System settings updated successfully.";
}

$siteName = get_setting('site_name', 'RoseSMM');
$supportEmail = get_setting('support_email', 'support@rosesmm.com');
$bonusPct = get_setting('deposit_bonus_percent', '10');
$maintMode = get_setting('maintenance_mode', '0');
?>

<div class="max-w-3xl space-y-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">System Settings</h1>
    <p class="text-xs text-slate-500 mt-1">Configure global application variables, branding, deposit bonus rates, and operational modes.</p>
  </div>

  <?php if ($msg): ?>
    <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
    <form method="POST" class="space-y-5">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Platform Brand Name</label>
        <input 
          type="text" 
          name="site_name" 
          value="<?= e($siteName) ?>" 
          required 
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-rose-500"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Official Support Desk Email</label>
        <input 
          type="email" 
          name="support_email" 
          value="<?= e($supportEmail) ?>" 
          required 
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Automatic Deposit Bonus (%)</label>
        <div class="flex items-center gap-2">
          <input 
            type="number" 
            step="1" 
            min="0" 
            max="100" 
            name="deposit_bonus_percent" 
            value="<?= e($bonusPct) ?>" 
            required 
            class="w-32 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-rose-500"
          >
          <span class="text-xs text-slate-500 font-semibold">% added automatically on every user wallet deposit</span>
        </div>
      </div>

      <div class="pt-4 border-t border-slate-100">
        <label class="flex items-center gap-3 cursor-pointer">
          <input 
            type="checkbox" 
            name="maintenance_mode" 
            value="1" 
            <?= $maintMode === '1' ? 'checked' : '' ?> 
            class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500"
          >
          <div>
            <div class="text-xs font-bold text-slate-800">Maintenance Mode</div>
            <div class="text-[11px] text-slate-400">Lock non-admin access while performing database migrations or server maintenance.</div>
          </div>
        </label>
      </div>

      <div class="pt-2">
        <button 
          type="submit" 
          class="px-6 py-3 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors"
        >
          Save Configuration
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
