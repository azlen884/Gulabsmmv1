<?php
$pageTitle = 'Website Theme - Admin Console';
$adminPage = 'theme';
require_once __DIR__ . '/../layouts/admin_header.php';

// Server-side Admin authentication check
if (!is_admin()) {
    header("Location: /admin/login");
    exit;
}

$msg = '';
$error = '';
$availableThemes = get_available_themes();

// Process Theme Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $selectedTheme = trim($_POST['active_theme'] ?? '');

    // Server-side strict validation against allowed themes
    if (array_key_exists($selectedTheme, $availableThemes)) {
        if (set_active_theme($selectedTheme)) {
            $themeName = $availableThemes[$selectedTheme]['name'];
            $msg = "Theme successfully switched to '{$themeName}'. The selected theme is now active site-wide.";
        } else {
            $error = "Failed to update theme in database.";
        }
    } else {
        $error = "Invalid theme selection. Please select an authorized theme.";
    }
}

$activeTheme = get_active_theme();
?>

<div class="max-w-5xl space-y-6">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        <i data-lucide="palette" class="w-6 h-6 text-rose-600"></i>
        Website Theme Management
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Configure the global visual theme across the platform. Theme selection is strictly controlled by administrators.
      </p>
    </div>
    <a href="/dashboard" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-colors">
      <i data-lucide="external-link" class="w-4 h-4"></i> Preview Portal
    </a>
  </div>

  <!-- Flash Messages -->
  <?php if ($msg): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2.5 shadow-sm">
      <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2.5 shadow-sm">
      <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- Theme Selector Form -->
  <form method="POST" class="space-y-6">
    <!-- Active Theme Selector Box -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
      <div class="max-w-xl space-y-4">
        <div>
          <label for="admin-theme-select" class="block text-sm font-bold text-slate-800 mb-1.5">
            Select Active Website Theme
          </label>
          <p class="text-xs text-slate-500 mb-3">
            Choose which theme will be displayed to all users and visitors. Changing this immediately updates the active theme across all portal and public pages.
          </p>
          
          <!-- Strict Theme Select Control with authorized options -->
          <select 
            id="admin-theme-select" 
            name="active_theme" 
            class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-2xl text-sm font-bold text-slate-800 focus:outline-none focus:border-rose-500 transition-colors"
          >
            <option value="default" <?= $activeTheme === 'default' ? 'selected' : '' ?>>Existing Theme</option>
            <option value="premium_red" <?= $activeTheme === 'premium_red' ? 'selected' : '' ?>>Premium Red + White</option>
            <option value="premium_green" <?= $activeTheme === 'premium_green' ? 'selected' : '' ?>>Premium Green + White</option>
            <option value="midnight_blue" <?= $activeTheme === 'midnight_blue' ? 'selected' : '' ?>>Ultra-Premium Midnight + Electric Blue</option>
            <option value="guardian_glow" <?= $activeTheme === 'guardian_glow' ? 'selected' : '' ?>>Guardian Glow</option>
          </select>
        </div>

        <div class="pt-2 flex items-center gap-3">
          <button 
            type="submit" 
            class="px-6 py-2.5 bg-rose-600 text-white rounded-xl text-xs font-bold hover:bg-rose-700 shadow-md shadow-rose-600/20 transition-all flex items-center gap-2 cursor-pointer"
          >
            <i data-lucide="save" class="w-4 h-4"></i>
            Save Active Theme
          </button>
          <span class="text-xs text-slate-400">Current active: <strong class="text-slate-700"><?= e($availableThemes[$activeTheme]['name']) ?></strong></span>
        </div>
      </div>
    </div>

    <!-- Theme Visual Cards Grid -->
    <div>
      <h2 class="text-sm font-bold text-slate-700 mb-3 px-1 flex items-center gap-2">
        <i data-lucide="layers" class="w-4 h-4 text-slate-400"></i> Theme Overview & Presets
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        
        <!-- 1. Existing Theme -->
        <?php $isDef = ($activeTheme === 'default'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isDef ? 'border-rose-500 ring-4 ring-rose-500/10' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isDef): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-rose-500 text-white text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-[#FFF0F3] border border-[#FCE4E8] flex items-center justify-center text-[#FF3B69] mb-4">
              <i data-lucide="sparkles" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Existing Theme</h3>
            <span class="inline-block text-[11px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md mt-1 mb-2">Classic Rose</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              The original signature soft Rose & Pink palette with soft gradient accents and clean light UI.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #FF3B69;" title="#FF3B69"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #E11D48;" title="#E11D48"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #FFF0F3;" title="#FFF0F3"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #FFFFFF;" title="#FFFFFF"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('default')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isDef ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-rose-50 text-slate-700 hover:text-rose-600 border border-slate-200' ?>"
            >
              <?= $isDef ? 'Current Active Theme' : 'Switch to Existing Theme' ?>
            </button>
          </div>
        </div>

        <!-- 2. Premium Red + White -->
        <?php $isRed = ($activeTheme === 'premium_red'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isRed ? 'border-red-600 ring-4 ring-red-600/10' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isRed): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-red-600 text-white text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-red-50 border border-red-200 flex items-center justify-center text-red-600 mb-4 shadow-sm">
              <i data-lucide="shield" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Premium Red + White</h3>
            <span class="inline-block text-[11px] font-bold text-red-700 bg-red-50 border border-red-100 px-2 py-0.5 rounded-md mt-1 mb-2">Glassmorphic Luxury</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              Sophisticated crimson & ruby shades (#DC2626) with tasteful glassmorphism on the navbar and dashboard cards, paired with crisp white contrast.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #DC2626;" title="#DC2626 Crimson"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #B91C1C;" title="#B91C1C Ruby"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #FEF2F2;" title="#FEF2F2 Soft Red"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #FFFFFF;" title="#FFFFFF Crisp White"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('premium_red')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isRed ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-red-50 text-slate-700 hover:text-red-600 border border-slate-200' ?>"
            >
              <?= $isRed ? 'Current Active Theme' : 'Switch to Red + White' ?>
            </button>
          </div>
        </div>

        <!-- 3. Premium Green + White -->
        <?php $isGreen = ($activeTheme === 'premium_green'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isGreen ? 'border-emerald-600 ring-4 ring-emerald-600/10' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isGreen): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-emerald-600 text-white text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 mb-4 shadow-sm">
              <i data-lucide="gem" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Premium Green + White</h3>
            <span class="inline-block text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-md mt-1 mb-2">Emerald Luxury</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              Fresh, modern emerald & jade tones (#059669) paired with elevated white cards, refined borders, and distinct visual identity.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #059669;" title="#059669 Emerald"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #047857;" title="#047857 Forest"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #ECFDF5;" title="#ECFDF5 Soft Mint"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #FFFFFF;" title="#FFFFFF Crisp White"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('premium_green')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isGreen ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-emerald-50 text-slate-700 hover:text-emerald-600 border border-slate-200' ?>"
            >
              <?= $isGreen ? 'Current Active Theme' : 'Switch to Green + White' ?>
            </button>
          </div>
        </div>

        <!-- 4. Ultra-Premium Midnight + Electric Blue -->
        <?php $isMidnight = ($activeTheme === 'midnight_blue'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isMidnight ? 'border-blue-600 ring-4 ring-blue-600/10' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isMidnight): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-blue-600 text-white text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-blue-400 mb-4 shadow-sm">
              <i data-lucide="moon" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Midnight + Electric Blue</h3>
            <span class="inline-block text-[11px] font-bold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-md mt-1 mb-2">Midnight Luxury</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              Ultra-premium deep midnight & navy surfaces paired with high-contrast electric cobalt blue, soft periwinkle highlights, and platinum accents.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #080C15;" title="#080C15 Deep Midnight"></span>
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #0B1120;" title="#0B1120 Deep Navy"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #2563EB;" title="#2563EB Electric Blue"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #A5B4FC;" title="#A5B4FC Soft Periwinkle"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #F8FAFC;" title="#F8FAFC Platinum"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('midnight_blue')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isMidnight ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-blue-50 text-slate-700 hover:text-blue-600 border border-slate-200' ?>"
            >
              <?= $isMidnight ? 'Current Active Theme' : 'Switch to Midnight Blue' ?>
            </button>
          </div>
        </div>

        <!-- 5. Guardian Glow -->
        <?php $isGuardian = ($activeTheme === 'guardian_glow'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isGuardian ? 'border-amber-500 ring-4 ring-amber-500/10' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isGuardian): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-amber-500 text-slate-950 text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-500 mb-4 shadow-sm">
              <i data-lucide="shield-check" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Guardian Glow</h3>
            <span class="inline-block text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md mt-1 mb-2">Guardian Glow</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              Futuristic dark luxury canvas paired with an ethereal luminous amber-gold guardian glow, high-contrast cards, and polished aesthetic.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #0A0B10;" title="#0A0B10 Deep Obsidian"></span>
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #10121A;" title="#10121A Dark Void"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #F59E0B;" title="#F59E0B Guardian Amber"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #FBBF24;" title="#FBBF24 Solar Gold"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #F8FAFC;" title="#F8FAFC Platinum"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('guardian_glow')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isGuardian ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-amber-50 text-slate-700 hover:text-amber-600 border border-slate-200' ?>"
            >
              <?= $isGuardian ? 'Current Active Theme' : 'Switch to Guardian Glow' ?>
            </button>
          </div>
        </div>

      </div>
    </div>
  </form>
</div>

<script>
function selectTheme(themeKey) {
  const select = document.getElementById('admin-theme-select');
  if (select) {
    select.value = themeKey;
    select.form.submit();
  }
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
