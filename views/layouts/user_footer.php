    </main>
    </div>

    <!-- Bottom Trust Bar matching screenshot -->
    <div class="border-t border-[#FCE4E8] bg-white px-4 lg:px-8 py-4">
      <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-500">
        <div class="flex flex-wrap items-center gap-6 sm:gap-8">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
              <i data-lucide="zap" class="w-3.5 h-3.5"></i>
            </div>
            <div>
              <div class="font-bold text-slate-700">Instant Delivery</div>
              <div class="text-[11px] text-slate-400">Most orders start within minutes.</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
              <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
            </div>
            <div>
              <div class="font-bold text-slate-700">Secure Payments</div>
              <div class="text-[11px] text-slate-400">100% safe and encrypted.</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
              <i data-lucide="headphones" class="w-3.5 h-3.5"></i>
            </div>
            <div>
              <div class="font-bold text-slate-700">24/7 Support</div>
              <div class="text-[11px] text-slate-400">We're always here to help.</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-rose-50 flex items-center justify-center text-rose-500">
              <i data-lucide="heart" class="w-3.5 h-3.5"></i>
            </div>
            <div>
              <div class="font-bold text-slate-700">Real Engagement</div>
              <div class="text-[11px] text-slate-400">High quality and non-drop.</div>
            </div>
          </div>
        </div>

        <div class="font-serif italic text-rose-500 font-bold text-sm tracking-wide flex items-center gap-1">
          <span>Your Success Our Priority!</span>
          <svg class="w-4 h-4 fill-current inline text-rose-400" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Plain JavaScript helpers -->
<script>
  // Initialize Lucide icons
  if (window.lucide) {
    lucide.createIcons();
  }

  // Mobile sidebar toggle
  function toggleMobileSidebar() {
    const sidebar = document.getElementById('mobile-sidebar');
    const backdrop = document.getElementById('mobile-sidebar-backdrop');
    if (!sidebar || !backdrop) return;
    const isClosed = sidebar.classList.contains('-translate-x-full');
    if (isClosed) {
      sidebar.classList.remove('-translate-x-full');
      backdrop.classList.remove('hidden');
    } else {
      sidebar.classList.add('-translate-x-full');
      backdrop.classList.add('hidden');
    }
  }

  // Currency dropdown toggle
  function toggleCurrencyDropdown() {
    const drop = document.getElementById('currency-dropdown');
    if (drop) {
      drop.classList.toggle('hidden');
    }
  }

  // Change currency via AJAX
  function changeCurrency(code) {
    fetch('/api/currency/switch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ currency: code })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      }
    });
  }

  // Close dropdown on outside click
  document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('currency-dropdown-wrapper');
    const drop = document.getElementById('currency-dropdown');
    if (wrapper && drop && !wrapper.contains(e.target)) {
      drop.classList.add('hidden');
    }
  });

  // Balance show/hide toggle
  let balanceVisible = true;
  function toggleBalanceVisibility() {
    const el = document.getElementById('user-balance-display');
    const icon = document.getElementById('balance-eye-icon');
    if (!el) return;
    balanceVisible = !balanceVisible;
    if (balanceVisible) {
      el.textContent = el.getAttribute('data-original') || '$24.58';
    } else {
      el.textContent = '••••••';
    }
  }
</script>

<?php
// Theme-matched styles for compact popups
$currentTheme = get_active_theme();
$popupThemeStyles = [
    'default' => [
        'name' => 'Classic Rose',
        'card' => 'bg-white border border-[#FCE4E8] shadow-xl text-slate-800',
        'badge' => 'bg-rose-50 text-rose-600 border border-rose-100',
        'tg_icon' => 'bg-rose-50 text-rose-500 border border-rose-100',
        'notice_icon' => 'bg-rose-50 text-rose-600 border border-rose-100',
        'heading' => 'text-slate-900',
        'body' => 'text-slate-500',
        'tg_btn' => 'bg-rose-500 hover:bg-rose-600 text-white shadow-xs hover:shadow-sm',
        'notice_btn' => 'bg-rose-500 hover:bg-rose-600 text-white shadow-xs hover:shadow-sm',
        'close_btn' => 'text-slate-400 hover:text-slate-700 bg-slate-100/80 hover:bg-slate-200/80',
        'subtle_btn' => 'text-slate-400 hover:text-rose-600',
        'backdrop' => 'bg-slate-900/40 backdrop-blur-xs',
    ],
    'premium_red' => [
        'name' => 'Glassmorphic Luxury',
        'card' => 'bg-white/95 backdrop-blur-md border border-red-200 shadow-xl shadow-red-950/5 text-slate-800',
        'badge' => 'bg-red-50 text-red-600 border border-red-200/80',
        'tg_icon' => 'bg-red-50 text-red-600 border border-red-200/80',
        'notice_icon' => 'bg-red-50 text-red-600 border border-red-200/80',
        'heading' => 'text-slate-900',
        'body' => 'text-slate-500',
        'tg_btn' => 'bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white shadow-xs shadow-red-600/20',
        'notice_btn' => 'bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white shadow-xs shadow-red-600/20',
        'close_btn' => 'text-slate-400 hover:text-red-700 bg-red-50/80 hover:bg-red-100/80',
        'subtle_btn' => 'text-slate-400 hover:text-red-600',
        'backdrop' => 'bg-slate-900/40 backdrop-blur-xs',
    ],
    'premium_green' => [
        'name' => 'Emerald Luxury',
        'card' => 'bg-white border border-emerald-200 shadow-xl shadow-emerald-950/5 text-slate-800',
        'badge' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/80',
        'tg_icon' => 'bg-emerald-50 text-emerald-600 border border-emerald-200/80',
        'notice_icon' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/80',
        'heading' => 'text-slate-900',
        'body' => 'text-slate-500',
        'tg_btn' => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs shadow-emerald-600/20',
        'notice_btn' => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs shadow-emerald-600/20',
        'close_btn' => 'text-slate-400 hover:text-emerald-700 bg-emerald-50/80 hover:bg-emerald-100/80',
        'subtle_btn' => 'text-slate-400 hover:text-emerald-600',
        'backdrop' => 'bg-slate-900/40 backdrop-blur-xs',
    ],
    'midnight_blue' => [
        'name' => 'Midnight Luxury',
        'card' => 'bg-[#0F172A] border border-slate-800 shadow-2xl shadow-black/70 text-slate-100',
        'badge' => 'bg-blue-950/80 text-blue-300 border border-blue-800/70',
        'tg_icon' => 'bg-blue-950/80 text-blue-400 border border-blue-800/70',
        'notice_icon' => 'bg-blue-950/80 text-blue-400 border border-blue-800/70',
        'heading' => 'text-white',
        'body' => 'text-slate-300',
        'tg_btn' => 'bg-blue-600 hover:bg-blue-500 text-white shadow-md shadow-blue-600/25',
        'notice_btn' => 'bg-blue-600 hover:bg-blue-500 text-white shadow-md shadow-blue-600/25',
        'close_btn' => 'text-slate-400 hover:text-white bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/50',
        'subtle_btn' => 'text-slate-400 hover:text-blue-300',
        'backdrop' => 'bg-black/75 backdrop-blur-xs',
    ],
    'holographic_aura' => [
        'name' => 'Holographic Aura',
        'card' => 'bg-white/95 backdrop-blur-md border border-purple-300/40 shadow-2xl shadow-indigo-500/15 text-slate-800',
        'badge' => 'bg-gradient-to-r from-cyan-500/15 to-purple-500/15 text-indigo-700 border border-purple-300/40',
        'tg_icon' => 'bg-gradient-to-br from-cyan-400/20 to-purple-500/20 text-indigo-600 border border-purple-300/40',
        'notice_icon' => 'bg-gradient-to-br from-cyan-400/20 to-purple-500/20 text-indigo-600 border border-purple-300/40',
        'heading' => 'text-slate-900',
        'body' => 'text-slate-600',
        'tg_btn' => 'bg-gradient-to-r from-cyan-500 via-indigo-500 to-purple-500 hover:from-cyan-400 hover:via-indigo-400 hover:to-purple-400 text-white font-bold shadow-md shadow-indigo-500/25',
        'notice_btn' => 'bg-gradient-to-r from-cyan-500 via-indigo-500 to-purple-500 hover:from-cyan-400 hover:via-indigo-400 hover:to-purple-400 text-white font-bold shadow-md shadow-indigo-500/25',
        'close_btn' => 'text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200',
        'subtle_btn' => 'text-slate-400 hover:text-indigo-600',
        'backdrop' => 'bg-slate-950/45 backdrop-blur-xs',
    ],
    'premium_black_gold' => [
        'name' => 'Black + Gold Luxury',
        'card' => 'bg-[#121216] border border-amber-500/30 shadow-2xl shadow-black/80 text-slate-100',
        'badge' => 'bg-amber-500/15 text-amber-300 border border-amber-500/30',
        'tg_icon' => 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
        'notice_icon' => 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
        'heading' => 'text-white',
        'body' => 'text-slate-300',
        'tg_btn' => 'bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-400 hover:to-yellow-500 text-slate-950 font-bold shadow-md shadow-amber-500/25',
        'notice_btn' => 'bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-400 hover:to-yellow-500 text-slate-950 font-bold shadow-md shadow-amber-500/25',
        'close_btn' => 'text-slate-400 hover:text-white bg-slate-900 hover:bg-slate-800 border border-slate-800',
        'subtle_btn' => 'text-slate-400 hover:text-amber-300',
        'backdrop' => 'bg-black/80 backdrop-blur-xs',
    ],
    'ocean_mint' => [
        'name' => 'Ocean Mint Luxury',
        'card' => 'bg-[#0C2338] border border-[#0AD1C8]/30 shadow-2xl shadow-black/80 text-slate-100',
        'badge' => 'bg-[#0AD1C8]/15 text-[#45DFB1] border border-[#0AD1C8]/30',
        'tg_icon' => 'bg-[#0AD1C8]/20 text-[#0AD1C8] border border-[#0AD1C8]/30',
        'notice_icon' => 'bg-[#0AD1C8]/20 text-[#0AD1C8] border border-[#0AD1C8]/30',
        'heading' => 'text-white',
        'body' => 'text-slate-300',
        'tg_btn' => 'bg-gradient-to-r from-[#0AD1C8] to-[#14919B] hover:from-[#45DFB1] hover:to-[#0AD1C8] text-[#071C2B] font-extrabold shadow-md shadow-[#0AD1C8]/25',
        'notice_btn' => 'bg-gradient-to-r from-[#0AD1C8] to-[#14919B] hover:from-[#45DFB1] hover:to-[#0AD1C8] text-[#071C2B] font-extrabold shadow-md shadow-[#0AD1C8]/25',
        'close_btn' => 'text-slate-400 hover:text-white bg-[#092032] hover:bg-[#0E2E47] border border-slate-700/50',
        'subtle_btn' => 'text-slate-400 hover:text-[#0AD1C8]',
        'backdrop' => 'bg-black/75 backdrop-blur-xs',
    ],
];
$thm = $popupThemeStyles[$currentTheme] ?? $popupThemeStyles['default'];

// Telegram Group Join Popup settings
$tgPopupEnabled = get_setting('telegram_popup_enabled', '0');
$rawTgUrl = get_setting('telegram_group_url', '');
$tgValidUrl = validate_telegram_url($rawTgUrl);
$tgPopupTitle = get_setting('telegram_popup_title', 'Stay Connected With Us');
$tgPopupMessage = get_setting('telegram_popup_message', 'Join our official Telegram community for important updates, announcements, offers and latest news.');
$showTelegram = ($tgPopupEnabled === '1' && !empty($tgValidUrl) && is_logged_in());

// Admin Notice Popup settings
$noticePopupEnabled = get_setting('notice_popup_enabled', '0');
$noticePopupTitle = get_setting('notice_popup_title', 'Important Notice');
$noticePopupMessage = get_setting('notice_popup_message', 'Scheduled maintenance will be carried out tonight.');
$showNotice = ($noticePopupEnabled === '1' && !empty(trim($noticePopupMessage)) && is_logged_in());
?>

<?php if ($showNotice): ?>
<!-- Admin Notice Modal -->
<div id="notice-popup-modal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 opacity-0 pointer-events-none transition-opacity duration-200 ease-out" role="dialog" aria-modal="true" aria-labelledby="notice-popup-heading">
  <!-- Subtle dark backdrop -->
  <div id="notice-popup-backdrop" class="fixed inset-0 <?= $thm['backdrop'] ?> transition-opacity duration-200 ease-out opacity-0" onclick="closeNoticePopup()"></div>

  <!-- Modal Dialog Card -->
  <div id="notice-popup-card" class="relative w-full max-w-[370px] <?= $thm['card'] ?> rounded-2xl p-5 sm:p-6 transform scale-95 transition-all duration-200 ease-out z-10 max-h-[85vh] flex flex-col justify-between">
    <!-- Close Button (×) -->
    <button 
      type="button" 
      onclick="closeNoticePopup()" 
      class="absolute top-3.5 right-3.5 w-7 h-7 rounded-lg <?= $thm['close_btn'] ?> flex items-center justify-center transition-colors focus:outline-none cursor-pointer"
      aria-label="Close notice"
    >
      <svg class="w-4 h-4 stroke-current stroke-2" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>

    <div>
      <!-- Compact Header: icon + badge -->
      <div class="flex items-center gap-3 mb-3 pr-6">
        <div class="w-10 h-10 rounded-xl <?= $thm['notice_icon'] ?> flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
          </svg>
        </div>
        <div>
          <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $thm['badge'] ?>">
            Announcement
          </span>
        </div>
      </div>

      <!-- Title & Content -->
      <div class="mb-4">
        <h3 id="notice-popup-heading" class="text-sm sm:text-base font-bold <?= $thm['heading'] ?> tracking-tight leading-snug">
          <?= e($noticePopupTitle) ?>
        </h3>
        <div class="text-xs <?= $thm['body'] ?> leading-relaxed mt-2 max-h-36 overflow-y-auto pr-1 whitespace-pre-line">
          <?= nl2br(e($noticePopupMessage)) ?>
        </div>
      </div>
    </div>

    <!-- Action Button -->
    <div class="pt-1">
      <button 
        type="button" 
        onclick="closeNoticePopup()" 
        class="w-full inline-flex items-center justify-center py-2.5 px-4 rounded-xl <?= $thm['notice_btn'] ?> text-xs font-bold transition-all duration-150 cursor-pointer"
      >
        Understood
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($showTelegram): ?>
<!-- Telegram Group Join Promotional Modal -->
<div id="telegram-popup-modal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 opacity-0 pointer-events-none transition-opacity duration-200 ease-out" role="dialog" aria-modal="true" aria-labelledby="telegram-popup-heading">
  <!-- Subtle dark backdrop -->
  <div id="telegram-popup-backdrop" class="fixed inset-0 <?= $thm['backdrop'] ?> transition-opacity duration-200 ease-out opacity-0" onclick="closeTelegramPopup()"></div>

  <!-- Modal Dialog Card -->
  <div id="telegram-popup-card" class="relative w-full max-w-[370px] <?= $thm['card'] ?> rounded-2xl p-5 sm:p-6 transform scale-95 transition-all duration-200 ease-out z-10 max-h-[85vh] flex flex-col justify-between">
    <!-- Close Button (×) -->
    <button 
      type="button" 
      onclick="closeTelegramPopup()" 
      class="absolute top-3.5 right-3.5 w-7 h-7 rounded-lg <?= $thm['close_btn'] ?> flex items-center justify-center transition-colors focus:outline-none cursor-pointer"
      aria-label="Close modal"
    >
      <svg class="w-4 h-4 stroke-current stroke-2" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>

    <div>
      <!-- Compact Header: icon + badge -->
      <div class="flex items-center gap-3 mb-3 pr-6">
        <div class="w-10 h-10 rounded-xl <?= $thm['tg_icon'] ?> flex items-center justify-center shrink-0">
          <!-- Standard-sized Telegram SVG icon -->
          <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
          </svg>
        </div>
        <div>
          <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $thm['badge'] ?>">
            Community
          </span>
        </div>
      </div>

      <!-- Title & Content -->
      <div class="mb-4">
        <h3 id="telegram-popup-heading" class="text-sm sm:text-base font-bold <?= $thm['heading'] ?> tracking-tight leading-snug">
          <?= e($tgPopupTitle) ?>
        </h3>
        <p class="text-xs <?= $thm['body'] ?> leading-relaxed mt-2 max-h-32 overflow-y-auto pr-1">
          <?= e($tgPopupMessage) ?>
        </p>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="space-y-2 pt-1">
      <a 
        href="<?= e($tgValidUrl) ?>" 
        target="_blank" 
        rel="noopener noreferrer" 
        onclick="handleTelegramJoinClick()"
        class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl <?= $thm['tg_btn'] ?> text-xs font-bold transition-all duration-150 cursor-pointer"
      >
        <svg class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
        </svg>
        <span>Join Telegram Group</span>
      </a>

      <button 
        type="button" 
        onclick="closeTelegramPopup()" 
        class="w-full py-1 text-[11px] font-medium <?= $thm['subtle_btn'] ?> transition-colors cursor-pointer text-center"
      >
        Maybe later
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  // Unified Popup Controller
  (function initPopups() {
    try {
      const noticeModal = document.getElementById('notice-popup-modal');
      const tgModal = document.getElementById('telegram-popup-modal');

      const noticeDismissed = sessionStorage.getItem('notice_popup_dismissed') === '1';
      const tgDismissed = sessionStorage.getItem('telegram_popup_dismissed') === '1';

      if (noticeModal && !noticeDismissed) {
        setTimeout(function() {
          openPopupModal('notice-popup-modal', 'notice-popup-backdrop', 'notice-popup-card');
        }, 500);
      } else if (tgModal && !tgDismissed) {
        setTimeout(function() {
          openPopupModal('telegram-popup-modal', 'telegram-popup-backdrop', 'telegram-popup-card');
        }, 800);
      }
    } catch (e) {}
  })();

  function openPopupModal(modalId, backdropId, cardId) {
    const modal = document.getElementById(modalId);
    const backdrop = document.getElementById(backdropId);
    const card = document.getElementById(cardId);
    if (!modal || !backdrop || !card) return;

    modal.classList.remove('opacity-0', 'pointer-events-none');
    modal.classList.add('opacity-100', 'pointer-events-auto');
    backdrop.classList.remove('opacity-0');
    backdrop.classList.add('opacity-100');
    card.classList.remove('scale-95');
    card.classList.add('scale-100');
  }

  function closePopupModal(modalId, backdropId, cardId, callback) {
    const modal = document.getElementById(modalId);
    const backdrop = document.getElementById(backdropId);
    const card = document.getElementById(cardId);
    if (!modal || !backdrop || !card) return;

    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
    card.classList.remove('scale-100');
    card.classList.add('scale-95');
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');

    setTimeout(function() {
      modal.classList.remove('pointer-events-auto');
      modal.classList.add('pointer-events-none');
      if (typeof callback === 'function') {
        callback();
      }
    }, 200);
  }

  function closeNoticePopup() {
    try {
      sessionStorage.setItem('notice_popup_dismissed', '1');
      closePopupModal('notice-popup-modal', 'notice-popup-backdrop', 'notice-popup-card', function() {
        const tgModal = document.getElementById('telegram-popup-modal');
        if (tgModal && sessionStorage.getItem('telegram_popup_dismissed') !== '1') {
          setTimeout(function() {
            openPopupModal('telegram-popup-modal', 'telegram-popup-backdrop', 'telegram-popup-card');
          }, 350);
        }
      });
    } catch (e) {}
  }

  function closeTelegramPopup() {
    try {
      sessionStorage.setItem('telegram_popup_dismissed', '1');
      closePopupModal('telegram-popup-modal', 'telegram-popup-backdrop', 'telegram-popup-card');
    } catch (e) {}
  }

  function handleTelegramJoinClick() {
    try {
      sessionStorage.setItem('telegram_popup_dismissed', '1');
      closeTelegramPopup();
    } catch (e) {}
  }

  // Support ESC key to close active modal
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const noticeModal = document.getElementById('notice-popup-modal');
      if (noticeModal && !noticeModal.classList.contains('pointer-events-none')) {
        closeNoticePopup();
        return;
      }
      const tgModal = document.getElementById('telegram-popup-modal');
      if (tgModal && !tgModal.classList.contains('pointer-events-none')) {
        closeTelegramPopup();
      }
    }
  });
</script>
</body>
</html>
