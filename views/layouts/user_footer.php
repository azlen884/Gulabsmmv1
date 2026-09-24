    </main>

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
// Telegram Group Join Popup (User side only, when enabled by admin with a valid HTTPS Telegram URL)
$tgPopupEnabled = get_setting('telegram_popup_enabled', '0');
$rawTgUrl = get_setting('telegram_group_url', '');
$tgValidUrl = validate_telegram_url($rawTgUrl);
$tgPopupTitle = get_setting('telegram_popup_title', 'Stay Connected With Us');
$tgPopupMessage = get_setting('telegram_popup_message', 'Join our official Telegram community for important updates, announcements, offers and latest news.');

if ($tgPopupEnabled === '1' && !empty($tgValidUrl) && is_logged_in()):
?>
<!-- Telegram Group Join Promotional Modal -->
<div id="telegram-popup-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300 ease-out" role="dialog" aria-modal="true" aria-labelledby="telegram-popup-heading">
  <!-- Subtle dark backdrop -->
  <div id="telegram-popup-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-300 ease-out opacity-0" onclick="closeTelegramPopup()"></div>

  <!-- Modal Dialog Card -->
  <div id="telegram-popup-card" class="relative w-full max-w-sm sm:max-w-md bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden transform scale-95 transition-all duration-300 ease-out z-10">
    <!-- Top decorative accent banner with subtle pattern -->
    <div class="h-24 sm:h-28 bg-gradient-to-br from-sky-400 via-sky-500 to-blue-600 relative overflow-hidden flex items-center justify-center">
      <div class="absolute inset-0 opacity-15">
        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="white"/></svg>
      </div>
      <!-- Floating glowing Telegram icon bubble -->
      <div class="relative z-10 w-16 h-16 sm:w-18 sm:h-18 rounded-2xl bg-white text-sky-500 shadow-xl flex items-center justify-center transform -rotate-3 hover:rotate-0 transition-transform">
        <svg class="w-9 h-9 sm:w-10 sm:h-10 fill-current ml-[-2px] mt-[1px]" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
        </svg>
      </div>

      <!-- Close Button (×) -->
      <button 
        type="button" 
        onclick="closeTelegramPopup()" 
        class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-colors focus:outline-none"
        aria-label="Close modal"
      >
        <svg class="w-4 h-4 stroke-current stroke-2" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- Modal Content -->
    <div class="p-6 sm:p-7 text-center">
      <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 border border-sky-100 text-sky-600 text-[11px] font-bold tracking-wide uppercase mb-3">
        <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
        Official Channel
      </div>
      <h3 id="telegram-popup-heading" class="text-lg sm:text-xl font-black text-slate-800 tracking-tight mb-2">
        <?= e($tgPopupTitle) ?>
      </h3>
      <p class="text-xs sm:text-sm text-slate-500 leading-relaxed max-w-sm mx-auto mb-6">
        <?= e($tgPopupMessage) ?>
      </p>

      <!-- Action Buttons -->
      <div class="space-y-2.5">
        <a 
          href="<?= e($tgValidUrl) ?>" 
          target="_blank" 
          rel="noopener noreferrer" 
          onclick="handleTelegramJoinClick()"
          class="w-full inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white text-xs sm:text-sm font-bold shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0"
        >
          <svg class="w-4.5 h-4.5 fill-current shrink-0" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
          </svg>
          <span>Join Telegram Group</span>
        </a>

        <button 
          type="button" 
          onclick="closeTelegramPopup()" 
          class="w-full py-2.5 text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors"
        >
          Maybe later
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  (function initTelegramPopup() {
    try {
      // Check if user already dismissed the popup in the current session
      if (sessionStorage.getItem('telegram_popup_dismissed') === '1') {
        return;
      }

      const modal = document.getElementById('telegram-popup-modal');
      const backdrop = document.getElementById('telegram-popup-backdrop');
      const card = document.getElementById('telegram-popup-card');
      if (!modal || !backdrop || !card) return;

      // Graceful entrance delay after initial render
      setTimeout(function() {
        modal.classList.remove('opacity-0', 'pointer-events-none');
        modal.classList.add('opacity-100', 'pointer-events-auto');
        backdrop.classList.remove('opacity-0');
        backdrop.classList.add('opacity-100');
        card.classList.remove('scale-95');
        card.classList.add('scale-100');
      }, 800);
    } catch (e) {
      // Silent error boundary
    }
  })();

  function closeTelegramPopup() {
    try {
      sessionStorage.setItem('telegram_popup_dismissed', '1');
      const modal = document.getElementById('telegram-popup-modal');
      const backdrop = document.getElementById('telegram-popup-backdrop');
      const card = document.getElementById('telegram-popup-card');
      if (!modal || !backdrop || !card) return;

      // Smooth exit animation
      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('opacity-0');
      card.classList.remove('scale-100');
      card.classList.add('scale-95');
      modal.classList.remove('opacity-100');
      modal.classList.add('opacity-0');

      setTimeout(function() {
        modal.classList.add('pointer-events-none');
      }, 300);
    } catch (e) {
      // Silent boundary
    }
  }

  function handleTelegramJoinClick() {
    try {
      sessionStorage.setItem('telegram_popup_dismissed', '1');
      closeTelegramPopup();
    } catch (e) {}
  }

  // Support ESC key to close
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const modal = document.getElementById('telegram-popup-modal');
      if (modal && !modal.classList.contains('pointer-events-none')) {
        closeTelegramPopup();
      }
    }
  });
</script>
<?php endif; ?>
</body>
</html>
