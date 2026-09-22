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
</body>
</html>
