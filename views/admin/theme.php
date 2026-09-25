<?php
require_once __DIR__ . '/../../config/database.php';

// Server-side Admin authentication check
if (!is_admin()) {
    if (isset($_POST['ajax_toggle_decor'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header("Location: /admin/login");
    exit;
}

// Handle AJAX decoration toggle request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajax_toggle_decor'])) {
    header('Content-Type: application/json');
    $effect = trim($_POST['effect'] ?? '');
    $val = trim($_POST['value'] ?? '');
    $allowed = ['decor_aurora_glow', 'decor_light_streaks', 'decor_corner_glow'];
    if (in_array($effect, $allowed, true)) {
        $enabled = ($val === '1' || $val === 'true' || $val === 'on');
        set_setting($effect, $enabled ? '1' : '0');
        echo json_encode([
            'success' => true,
            'effect' => $effect,
            'enabled' => $enabled
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid effect key']);
    }
    exit;
}

$pageTitle = 'Website Theme - Admin Console';
$adminPage = 'theme';
require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';
$error = '';
$availableThemes = get_available_themes();

// Process Theme or Decoration Update via POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Check if decoration effects were submitted
    if (isset($_POST['update_decorations'])) {
        $aurora = isset($_POST['decor_aurora_glow']) ? '1' : '0';
        $streaks = isset($_POST['decor_light_streaks']) ? '1' : '0';
        $corners = isset($_POST['decor_corner_glow']) ? '1' : '0';
        set_setting('decor_aurora_glow', $aurora);
        set_setting('decor_light_streaks', $streaks);
        set_setting('decor_corner_glow', $corners);
        $msg = "Decoration effects updated successfully in database.";
    }

    // Check if active theme was submitted
    if (isset($_POST['active_theme'])) {
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
}

$activeTheme = get_active_theme();
$auroraGlowOn = is_aurora_glow_enabled();
$lightStreaksOn = is_light_streaks_enabled();
$cornerGlowOn = is_corner_glow_enabled();
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
            <option value="premium_red" <?= $activeTheme === 'premium_red' ? 'selected' : '' ?>>Premium Red + White</option>
            <option value="premium_green" <?= $activeTheme === 'premium_green' ? 'selected' : '' ?>>Premium Green + White</option>
            <option value="midnight_blue" <?= $activeTheme === 'midnight_blue' ? 'selected' : '' ?>>Ultra-Premium Midnight + Electric Blue</option>
            <option value="holographic_aura" <?= $activeTheme === 'holographic_aura' ? 'selected' : '' ?>>Holographic Aura</option>
            <option value="premium_black_gold" <?= $activeTheme === 'premium_black_gold' ? 'selected' : '' ?>>Premium Black + Gold</option>
            <option value="ocean_mint" <?= $activeTheme === 'ocean_mint' ? 'selected' : '' ?>>Ocean Mint Luxury</option>
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

    <!-- Decoration Effects Independent Controls -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
        <div>
          <h2 class="text-base font-black text-slate-800 tracking-tight flex items-center gap-2">
            <i data-lucide="sparkles" class="w-5 h-5 text-amber-500"></i>
            Decoration Effects
          </h2>
          <p class="text-xs text-slate-500 mt-1">
            Independently toggle the three visual decoration layers across all four themes. Settings are stored in MySQL and take effect immediately.
          </p>
        </div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-bold text-slate-600">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span>MySQL Synchronized</span>
        </div>
      </div>

      <div class="space-y-4">
        <!-- 1. Aurora Glow -->
        <div class="p-4 sm:p-5 rounded-2xl border border-slate-200 bg-slate-50/60 hover:bg-slate-50 transition-colors flex items-center justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-slate-900">Aurora Glow</span>
              <span class="text-[10px] uppercase font-bold text-purple-700 bg-purple-100/70 px-2 py-0.5 rounded-md">Ambient Flow</span>
            </div>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
              Very soft, large-scale blurred ambient glow behind content. Adapts smoothly to the active theme palette.
            </p>
          </div>
          <div class="shrink-0 flex items-center gap-3">
            <button 
              type="button" 
              id="btn-decor-aurora" 
              onclick="toggleDecoration('decor_aurora_glow')"
              class="inline-flex items-center justify-center min-w-[76px] px-3.5 py-1.5 rounded-xl text-xs font-black tracking-wider transition-all cursor-pointer shadow-sm <?= $auroraGlowOn ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' ?>"
            >
              [ <?= $auroraGlowOn ? 'ON' : 'OFF' ?> ]
            </button>
            <input type="hidden" name="decor_aurora_glow" id="input-decor-aurora" value="<?= $auroraGlowOn ? '1' : '0' ?>">
          </div>
        </div>

        <!-- 2. Light Streaks -->
        <div class="p-4 sm:p-5 rounded-2xl border border-slate-200 bg-slate-50/60 hover:bg-slate-50 transition-colors flex items-center justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-slate-900">Light Streaks</span>
              <span class="text-[10px] uppercase font-bold text-sky-700 bg-sky-100/70 px-2 py-0.5 rounded-md">Digital Shimmer</span>
            </div>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
              Fine illuminated trails with smooth light flow that complement the multi-line flowing wave ribbons.
            </p>
          </div>
          <div class="shrink-0 flex items-center gap-3">
            <button 
              type="button" 
              id="btn-decor-streaks" 
              onclick="toggleDecoration('decor_light_streaks')"
              class="inline-flex items-center justify-center min-w-[76px] px-3.5 py-1.5 rounded-xl text-xs font-black tracking-wider transition-all cursor-pointer shadow-sm <?= $lightStreaksOn ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' ?>"
            >
              [ <?= $lightStreaksOn ? 'ON' : 'OFF' ?> ]
            </button>
            <input type="hidden" name="decor_light_streaks" id="input-decor-streaks" value="<?= $lightStreaksOn ? '1' : '0' ?>">
          </div>
        </div>

        <!-- 3. Corner Glow -->
        <div class="p-4 sm:p-5 rounded-2xl border border-slate-200 bg-slate-50/60 hover:bg-slate-50 transition-colors flex items-center justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-slate-900">Corner Glow</span>
              <span class="text-[10px] uppercase font-bold text-amber-700 bg-amber-100/70 px-2 py-0.5 rounded-md">Canvas Depth</span>
            </div>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
              Subtle radial gradient illumination at viewport corners grounding the layout without obscuring text.
            </p>
          </div>
          <div class="shrink-0 flex items-center gap-3">
            <button 
              type="button" 
              id="btn-decor-corners" 
              onclick="toggleDecoration('decor_corner_glow')"
              class="inline-flex items-center justify-center min-w-[76px] px-3.5 py-1.5 rounded-xl text-xs font-black tracking-wider transition-all cursor-pointer shadow-sm <?= $cornerGlowOn ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' ?>"
            >
              [ <?= $cornerGlowOn ? 'ON' : 'OFF' ?> ]
            </button>
            <input type="hidden" name="decor_corner_glow" id="input-decor-corners" value="<?= $cornerGlowOn ? '1' : '0' ?>">
          </div>
        </div>
      </div>

      <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <button 
          type="submit" 
          name="update_decorations" 
          value="1" 
          class="px-5 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition-colors flex items-center gap-2 cursor-pointer shadow-sm"
        >
          <i data-lucide="check" class="w-4 h-4"></i>
          Save Decoration Settings
        </button>
        <span id="decor-status-text" class="text-xs font-semibold text-slate-500">Toggles save immediately via MySQL.</span>
      </div>
    </div>

    <!-- Theme Visual Cards Grid -->
    <div>
      <h2 class="text-sm font-bold text-slate-700 mb-3 px-1 flex items-center gap-2">
        <i data-lucide="layers" class="w-4 h-4 text-slate-400"></i> Theme Overview & Presets
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        
        <!-- 1. Premium Red + White -->
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

        <!-- 5. Holographic Aura -->
        <?php $isHolo = ($activeTheme === 'holographic_aura'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isHolo ? 'border-cyan-400 ring-4 ring-cyan-400/20' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isHolo): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-gradient-to-r from-cyan-500 to-indigo-600 text-white text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-400/20 to-purple-500/20 border border-purple-300/40 flex items-center justify-center text-indigo-600 mb-4 shadow-sm">
              <i data-lucide="sparkles" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Holographic Aura</h3>
            <span class="inline-block text-[11px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md mt-1 mb-2">Holographic Aura</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              Futuristic holographic aesthetic with soft cyan, aqua, and lavender gradients, translucent glowing cards, and polished SaaS elegance.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #06B6D4;" title="#06B6D4 Soft Cyan"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #2DD4BF;" title="#2DD4BF Aqua"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #818CF8;" title="#818CF8 Soft Violet"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #C084FC;" title="#C084FC Light Lavender"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #F472B6;" title="#F472B6 Pastel Pink"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('holographic_aura')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isHolo ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-cyan-50 text-slate-700 hover:text-indigo-600 border border-slate-200' ?>"
            >
              <?= $isHolo ? 'Current Active Theme' : 'Switch to Holographic Aura' ?>
            </button>
          </div>
        </div>

        <!-- 6. Premium Black + Gold -->
        <?php $isGold = ($activeTheme === 'premium_black_gold' || $activeTheme === 'black_gold'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isGold ? 'border-amber-500 ring-4 ring-amber-500/20' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isGold): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-gradient-to-r from-amber-500 to-yellow-600 text-slate-950 text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-slate-950 border border-amber-500/40 flex items-center justify-center text-amber-400 mb-4 shadow-sm">
              <i data-lucide="crown" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Premium Black + Gold</h3>
            <span class="inline-block text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md mt-1 mb-2">Black + Gold Luxury</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              Opulent deep obsidian black canvas paired with metallic champagne gold accents, warm flowing ribbons, and elite luxury aesthetic.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #0A0A0C;" title="#0A0A0C Obsidian Black"></span>
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #18181E;" title="#18181E Charcoal Surface"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #D4AF37;" title="#D4AF37 Metallic Gold"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #F5D77F;" title="#F5D77F Champagne Gold"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #F8FAFC;" title="#F8FAFC Platinum"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('premium_black_gold')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isGold ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-amber-50 text-slate-700 hover:text-amber-600 border border-slate-200' ?>"
            >
              <?= $isGold ? 'Current Active Theme' : 'Switch to Black + Gold' ?>
            </button>
          </div>
        </div>

        <!-- 6. Ocean Mint Luxury -->
        <?php $isOcean = ($activeTheme === 'ocean_mint'); ?>
        <div class="bg-white rounded-3xl border-2 <?= $isOcean ? 'border-[#0AD1C8] ring-4 ring-[#0AD1C8]/20' : 'border-slate-200' ?> p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all relative overflow-hidden">
          <?php if ($isOcean): ?>
            <div class="absolute top-4 right-4 px-2.5 py-1 bg-gradient-to-r from-[#0AD1C8] to-[#45DFB1] text-[#071C2B] text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm">
              Active
            </div>
          <?php endif; ?>
          <div>
            <div class="w-12 h-12 rounded-2xl bg-[#071C2B] border border-[#0AD1C8]/30 flex items-center justify-center text-[#0AD1C8] mb-4 shadow-sm">
              <i data-lucide="waves" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Ocean Mint Luxury</h3>
            <span class="inline-block text-[11px] font-bold text-[#0B6477] bg-teal-50 border border-teal-100 px-2 py-0.5 rounded-md mt-1 mb-2">Ocean Mint Luxury</span>
            <p class="text-xs text-slate-500 leading-relaxed">
              Deep navy and rich teal canvas illuminated by luminous bright cyan accents, refreshing mint highlights, and ethereal digital glow.
            </p>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11px] font-bold text-slate-400">Palette:</span>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #071C2B;" title="#071C2B Deep Navy"></span>
                <span class="w-4 h-4 rounded-full border border-slate-700" style="background-color: #0B6477;" title="#0B6477 Deep Teal"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #0AD1C8;" title="#0AD1C8 Bright Cyan"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #45DFB1;" title="#45DFB1 Mint"></span>
                <span class="w-4 h-4 rounded-full border border-slate-200" style="background-color: #80ED99;" title="#80ED99 Soft Green"></span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100">
            <button 
              type="button" 
              onclick="selectTheme('ocean_mint')"
              class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center transition-all cursor-pointer <?= $isOcean ? 'bg-slate-100 text-slate-500 cursor-default' : 'bg-slate-50 hover:bg-teal-50 text-slate-700 hover:text-[#0B6477] border border-slate-200' ?>"
            >
              <?= $isOcean ? 'Current Active Theme' : 'Switch to Ocean Mint Luxury' ?>
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

function toggleDecoration(key) {
  let inputId = '';
  let btnId = '';
  if (key === 'decor_aurora_glow') {
    inputId = 'input-decor-aurora';
    btnId = 'btn-decor-aurora';
  } else if (key === 'decor_light_streaks') {
    inputId = 'input-decor-streaks';
    btnId = 'btn-decor-streaks';
  } else if (key === 'decor_corner_glow') {
    inputId = 'input-decor-corners';
    btnId = 'btn-decor-corners';
  }

  const input = document.getElementById(inputId);
  const btn = document.getElementById(btnId);
  const statusText = document.getElementById('decor-status-text');

  if (!input || !btn) return;

  const currentVal = input.value === '1';
  const newVal = !currentVal;
  input.value = newVal ? '1' : '0';

  // Instant UI update
  btn.textContent = '[ ' + (newVal ? 'ON' : 'OFF') + ' ]';
  if (newVal) {
    btn.className = 'inline-flex items-center justify-center min-w-[76px] px-3.5 py-1.5 rounded-xl text-xs font-black tracking-wider transition-all cursor-pointer shadow-sm bg-emerald-600 text-white hover:bg-emerald-700';
  } else {
    btn.className = 'inline-flex items-center justify-center min-w-[76px] px-3.5 py-1.5 rounded-xl text-xs font-black tracking-wider transition-all cursor-pointer shadow-sm bg-slate-200 text-slate-600 hover:bg-slate-300';
  }

  if (statusText) statusText.textContent = 'Saving to database...';

  const fd = new FormData();
  fd.append('ajax_toggle_decor', '1');
  fd.append('effect', key);
  fd.append('value', newVal ? '1' : '0');

  fetch('/admin/theme', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (statusText) {
      if (data.success) {
        statusText.textContent = 'Saved directly to MySQL.';
        setTimeout(() => { 
          if (statusText.textContent === 'Saved directly to MySQL.') {
            statusText.textContent = 'Toggles save immediately via MySQL.';
          }
        }, 2500);
      } else {
        statusText.textContent = 'Save failed: ' + (data.error || 'Server error');
      }
    }
  })
  .catch(err => {
    if (statusText) statusText.textContent = 'Network error saving setting';
  });
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
