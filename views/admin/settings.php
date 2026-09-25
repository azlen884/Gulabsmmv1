<?php
$pageTitle = 'Settings - Admin Console';
$adminPage = 'settings';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteNameInput = trim($_POST['site_name'] ?? 'RoseSMM');
    if ($siteNameInput === '') $siteNameInput = 'SMM Panel';
    $settings = [
        'site_name' => $siteNameInput,
        'site_title' => $siteNameInput . ' - Social Media Services',
        'support_email' => trim($_POST['support_email'] ?? 'support@example.com'),
        'deposit_bonus_percent' => trim($_POST['deposit_bonus_percent'] ?? '10'),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
    ];

    $telegramEnabled = isset($_POST['telegram_popup_enabled']) ? '1' : '0';
    $rawTelegramUrl = trim($_POST['telegram_group_url'] ?? '');
    $sanitizedTelegramUrl = '';
    $telegramTitle = trim($_POST['telegram_popup_title'] ?? 'Stay Connected With Us');
    if ($telegramTitle === '') $telegramTitle = 'Stay Connected With Us';
    $telegramMessage = trim($_POST['telegram_popup_message'] ?? 'Join our official Telegram community for important updates, announcements, offers and latest news.');
    if ($telegramMessage === '') $telegramMessage = 'Join our official Telegram community for important updates, announcements, offers and latest news.';

    if (!empty($rawTelegramUrl)) {
        $validatedUrl = validate_telegram_url($rawTelegramUrl);
        if ($validatedUrl) {
            $sanitizedTelegramUrl = $validatedUrl;
        } else {
            $errorMsg = "Invalid Telegram URL. Please provide a valid HTTPS link (e.g. https://t.me/yourgroup).";
            $telegramEnabled = '0';
        }
    } else {
        if ($telegramEnabled === '1') {
            $errorMsg = "Telegram popup cannot be enabled without a valid Telegram Group URL.";
            $telegramEnabled = '0';
        }
    }

    $settings['telegram_popup_enabled'] = $telegramEnabled;
    $settings['telegram_group_url'] = $sanitizedTelegramUrl;
    $settings['telegram_popup_title'] = $telegramTitle;
    $settings['telegram_popup_message'] = $telegramMessage;

    // Notice Popup Settings
    $noticeEnabled = isset($_POST['notice_popup_enabled']) ? '1' : '0';
    $noticeTitle = trim($_POST['notice_popup_title'] ?? 'Important Notice');
    if ($noticeTitle === '') $noticeTitle = 'Important Notice';
    $noticeMessage = trim($_POST['notice_popup_message'] ?? 'Scheduled maintenance will be carried out tonight.');
    if ($noticeMessage === '') $noticeMessage = 'Scheduled maintenance will be carried out tonight.';

    $settings['notice_popup_enabled'] = $noticeEnabled;
    $settings['notice_popup_title'] = $noticeTitle;
    $settings['notice_popup_message'] = $noticeMessage;

    if (isset($_POST['decor_submitted'])) {
        $settings['decor_aurora_glow'] = isset($_POST['decor_aurora_glow']) ? '1' : '0';
        $settings['decor_light_streaks'] = isset($_POST['decor_light_streaks']) ? '1' : '0';
        $settings['decor_corner_glow'] = isset($_POST['decor_corner_glow']) ? '1' : '0';
    }

    foreach ($settings as $key => $val) {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $val, $val]);
    }

    if (isset($_POST['active_theme'])) {
        $chosenTheme = trim($_POST['active_theme']);
        set_active_theme($chosenTheme);
    }

    if (!$errorMsg) {
        $msg = "System settings updated successfully.";
    }
}

$siteName = get_setting('site_name', 'RoseSMM');
$supportEmail = get_setting('support_email', 'support@rosesmm.com');
$bonusPct = get_setting('deposit_bonus_percent', '10');
$maintMode = get_setting('maintenance_mode', '0');
$telegramPopupEnabled = get_setting('telegram_popup_enabled', '0');
$telegramGroupUrl = get_setting('telegram_group_url', '');
$telegramPopupTitle = get_setting('telegram_popup_title', 'Stay Connected With Us');
$telegramPopupMessage = get_setting('telegram_popup_message', 'Join our official Telegram community for important updates, announcements, offers and latest news.');
$noticePopupEnabled = get_setting('notice_popup_enabled', '0');
$noticePopupTitle = get_setting('notice_popup_title', 'Important Notice');
$noticePopupMessage = get_setting('notice_popup_message', 'Scheduled maintenance will be carried out tonight.');
$activeTheme = get_active_theme();
$availableThemes = get_available_themes();
?>

<div class="max-w-3xl space-y-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">System Settings</h1>
    <p class="text-xs text-slate-500 mt-1">Configure global application variables, branding, Telegram community popup, and operational modes.</p>
  </div>

  <?php if ($msg): ?>
    <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($errorMsg): ?>
    <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
      <span><?= e($errorMsg) ?></span>
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

      <!-- Refer & Earn Quick Link -->
      <div class="p-4 rounded-2xl bg-rose-50/40 border border-rose-100 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0">
            <i data-lucide="gift" class="w-5 h-5"></i>
          </div>
          <div>
            <h4 class="text-xs font-bold text-slate-800">Refer & Earn Management</h4>
            <p class="text-[11px] text-slate-500">Configure referral commission rates, toggle program status, and review commission history.</p>
          </div>
        </div>
        <a href="/admin/referrals" class="px-4 py-2 rounded-xl bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 font-bold text-xs shrink-0 transition-colors shadow-2xs">
          Manage Referrals &rarr;
        </a>
      </div>

      <div>
        <div class="flex items-center justify-between mb-1">
          <label for="admin-settings-theme" class="block text-xs font-bold text-slate-700">Active Website Theme</label>
          <a href="/admin/theme" class="text-[11px] font-bold text-rose-600 hover:text-rose-700 flex items-center gap-1">
            <i data-lucide="palette" class="w-3.5 h-3.5"></i> Detailed Theme Manager &rarr;
          </a>
        </div>
        <select 
          id="admin-settings-theme" 
          name="active_theme" 
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-rose-500"
        >
          <option value="default" <?= $activeTheme === 'default' ? 'selected' : '' ?>>Existing Theme</option>
          <option value="premium_red" <?= $activeTheme === 'premium_red' ? 'selected' : '' ?>>Premium Red + White</option>
          <option value="premium_green" <?= $activeTheme === 'premium_green' ? 'selected' : '' ?>>Premium Green + White</option>
          <option value="midnight_blue" <?= $activeTheme === 'midnight_blue' ? 'selected' : '' ?>>Ultra-Premium Midnight + Electric Blue</option>
          <option value="holographic_aura" <?= $activeTheme === 'holographic_aura' ? 'selected' : '' ?>>Holographic Aura</option>
          <option value="premium_black_gold" <?= $activeTheme === 'premium_black_gold' ? 'selected' : '' ?>>Premium Black + Gold</option>
        </select>
        <p class="text-[11px] text-slate-400 mt-1">Global website theme applied across the platform. Configured exclusively by administrators.</p>
      </div>

      <!-- Telegram Community Popup Setting -->
      <div class="pt-5 border-t border-slate-100">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 flex items-center justify-center shrink-0">
            <svg class="w-4.5 h-4.5 fill-current" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
          </div>
          <div>
            <h3 class="text-xs font-bold text-slate-800">Telegram Group Popup</h3>
            <p class="text-[11px] text-slate-400">Promotional modal shown to logged-in users inviting them to your official Telegram community.</p>
          </div>
        </div>

        <div class="space-y-4 bg-slate-50/70 p-4 sm:p-5 rounded-2xl border border-slate-200/80">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-xs font-bold text-slate-800">Popup Status</div>
              <div class="text-[11px] text-slate-400">Enable or disable the Telegram promotional popup on the user dashboard.</div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input 
                type="checkbox" 
                name="telegram_popup_enabled" 
                value="1" 
                <?= $telegramPopupEnabled === '1' ? 'checked' : '' ?> 
                class="sr-only peer"
              >
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
            </label>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Telegram Group / Channel URL</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-sky-500">
                <i data-lucide="link" class="w-3.5 h-3.5"></i>
              </div>
              <input 
                type="url" 
                name="telegram_group_url" 
                value="<?= e($telegramGroupUrl) ?>" 
                placeholder="https://t.me/yourgroup" 
                class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-sky-500 text-slate-800"
              >
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Must be a secure HTTPS Telegram link (e.g. <code>https://t.me/yourgroup</code> or <code>https://telegram.me/yourchannel</code>).</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Popup Heading</label>
              <input 
                type="text" 
                name="telegram_popup_title" 
                value="<?= e($telegramPopupTitle) ?>" 
                placeholder="Stay Connected With Us" 
                class="w-full px-3.5 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-sky-500 text-slate-800"
              >
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Popup Subtitle / Message</label>
              <input 
                type="text" 
                name="telegram_popup_message" 
                value="<?= e($telegramPopupMessage) ?>" 
                placeholder="Join our official Telegram community for important updates..." 
                class="w-full px-3.5 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-sky-500 text-slate-800"
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Admin Notice Popup Setting -->
      <div class="pt-5 border-t border-slate-100">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
            <i data-lucide="bell" class="w-4.5 h-4.5"></i>
          </div>
          <div>
            <h3 class="text-xs font-bold text-slate-800">Admin Notice Popup</h3>
            <p class="text-[11px] text-slate-400">Display an urgent notice, maintenance alert, or system announcement modal to users.</p>
          </div>
        </div>

        <div class="space-y-4 bg-slate-50/70 p-4 sm:p-5 rounded-2xl border border-slate-200/80">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-xs font-bold text-slate-800">Notice Popup Status</div>
              <div class="text-[11px] text-slate-400">Enable or disable the announcement notice popup on user portal pages.</div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input 
                type="checkbox" 
                name="notice_popup_enabled" 
                value="1" 
                <?= $noticePopupEnabled === '1' ? 'checked' : '' ?> 
                class="sr-only peer"
              >
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Notice Title</label>
            <input 
              type="text" 
              name="notice_popup_title" 
              value="<?= e($noticePopupTitle) ?>" 
              placeholder="Important Notice" 
              class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500 text-slate-800"
            >
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Notice Content / Message</label>
            <textarea 
              name="notice_popup_message" 
              rows="3" 
              placeholder="Scheduled maintenance will be carried out tonight..." 
              class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500 text-slate-800"
            ><?= e($noticePopupMessage) ?></textarea>
            <p class="text-[10px] text-slate-400 mt-1">This message will be displayed in the compact professional notice popup to logged-in users.</p>
          </div>
        </div>
      </div>

      <!-- Visual Decoration Effects -->
      <div class="pt-5 border-t border-slate-100">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
              <i data-lucide="sparkles" class="w-4.5 h-4.5"></i>
            </div>
            <div>
              <h3 class="text-xs font-bold text-slate-800">Visual Decoration Effects</h3>
              <p class="text-[11px] text-slate-400">Control Aurora Glow, Light Streaks, and Corner Glow across all four themes.</p>
            </div>
          </div>
          <a href="/admin/theme" class="text-xs font-bold text-purple-600 hover:text-purple-700 inline-flex items-center gap-1">
            Theme Settings <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
          </a>
        </div>

        <input type="hidden" name="decor_submitted" value="1">
        <div class="space-y-3 bg-slate-50/70 p-4 sm:p-5 rounded-2xl border border-slate-200/80">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-xs font-bold text-slate-800">Aurora Glow</div>
              <div class="text-[11px] text-slate-400">Soft, large-scale ambient blurred glow behind content.</div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" name="decor_aurora_glow" value="1" <?= is_aurora_glow_enabled() ? 'checked' : '' ?> class="sr-only peer">
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
            </label>
          </div>

          <div class="flex items-center justify-between gap-4 pt-2 border-t border-slate-200/50">
            <div>
              <div class="text-xs font-bold text-slate-800">Light Streaks</div>
              <div class="text-[11px] text-slate-400">Thin illuminated digital trails along wave ribbons.</div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" name="decor_light_streaks" value="1" <?= is_light_streaks_enabled() ? 'checked' : '' ?> class="sr-only peer">
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
            </label>
          </div>

          <div class="flex items-center justify-between gap-4 pt-2 border-t border-slate-200/50">
            <div>
              <div class="text-xs font-bold text-slate-800">Corner Glow</div>
              <div class="text-[11px] text-slate-400">Subtle radial corner ambient glows grounding layout.</div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" name="decor_corner_glow" value="1" <?= is_corner_glow_enabled() ? 'checked' : '' ?> class="sr-only peer">
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
            </label>
          </div>
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
