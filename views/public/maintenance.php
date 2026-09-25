<?php
/**
 * Maintenance Mode Page
 * Displays when maintenance mode is active.
 * STRICT SECURITY: No admin bypass links, buttons, or hidden shortcuts.
 */
require_once __DIR__ . '/../../config/database.php';

http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(get_site_name()) ?> - Maintenance Mode</title>
  <meta name="description" content="<?= e(get_site_name()) ?> is currently undergoing scheduled maintenance.">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <?php render_theme_head_tags(); ?>
</head>
<body class="min-h-screen bg-[#FFF9FA] text-slate-800 antialiased flex items-center justify-center p-4 <?= get_theme_body_class() ?>">
  <div class="max-w-lg w-full bg-white/90 backdrop-blur-md rounded-3xl p-8 sm:p-10 border border-[#FCE4E8] shadow-xl text-center relative overflow-hidden">
    <!-- Subtle Theme Wave / Glow Accent -->
    <div class="absolute -top-24 -right-24 w-48 h-48 rounded-full bg-rose-500/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-48 h-48 rounded-full bg-rose-500/10 blur-3xl pointer-events-none"></div>

    <div class="relative z-10">
      <div class="w-16 h-16 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto mb-6 shadow-sm">
        <i data-lucide="wrench" class="w-8 h-8"></i>
      </div>

      <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-3">
        Under Scheduled Maintenance
      </h1>

      <p class="text-sm text-slate-600 leading-relaxed mb-6">
        We are currently upgrading our platform infrastructure to deliver faster speeds, enhanced stability, and better service delivery. All pending orders and balances remain secure.
      </p>

      <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs text-slate-500 flex items-center justify-center gap-2">
        <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
        <span>Systems are actively being optimized. Please check back shortly.</span>
      </div>

      <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-center gap-2 text-xs text-slate-400">
        <span class="font-bold text-slate-600"><?= e(get_site_name()) ?></span>
        <span>•</span>
        <span>Estimated Return: Under 30 minutes</span>
      </div>
    </div>
  </div>

  <script>
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
  </script>
</body>
</html>
