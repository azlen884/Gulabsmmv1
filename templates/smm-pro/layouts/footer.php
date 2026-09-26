    </main>

    <!-- Mobile Bottom Navigation Bar (Hidden on desktop) -->
    <div class="smm-mobile-nav fixed bottom-0 left-0 right-0 z-40 py-2 px-4 flex items-center justify-around lg:hidden">
      <a href="/dashboard" class="smm-mobile-tab <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
        <span>Home</span>
      </a>
      <a href="/order" class="smm-mobile-tab <?= ($activePage ?? '') === 'order' ? 'active' : '' ?>">
        <div class="w-9 h-9 -mt-4 rounded-full bg-gradient-to-tr from-[#FF2D78] to-[#D91B5C] flex items-center justify-center text-white shadow-lg shadow-[#FF2D78]/40">
          <i data-lucide="plus" class="w-5 h-5"></i>
        </div>
        <span class="mt-0.5">Order</span>
      </a>
      <a href="/services" class="smm-mobile-tab <?= ($activePage ?? '') === 'services' ? 'active' : '' ?>">
        <i data-lucide="sparkles" class="w-5 h-5"></i>
        <span>Services</span>
      </a>
      <a href="/wallet" class="smm-mobile-tab <?= ($activePage ?? '') === 'wallet' ? 'active' : '' ?>">
        <i data-lucide="wallet" class="w-5 h-5"></i>
        <span>Wallet</span>
      </a>
      <a href="/profile" class="smm-mobile-tab <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
        <i data-lucide="user" class="w-5 h-5"></i>
        <span>Profile</span>
      </a>
    </div>

    <!-- SMM Pro Footer -->
    <footer class="py-6 px-4 sm:px-8 border-t border-white/5 text-center text-xs text-[#6C6C8A] bg-[#08080E] relative z-10 pb-20 lg:pb-6">
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 max-w-7xl mx-auto">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-[#10B981]"></span>
          <span>All SMM API Clusters Operational (99.98% uptime)</span>
        </div>
        <div>
          &copy; <?= date('Y') ?> <strong class="text-white"><?= e(get_site_name()) ?></strong>. SMM Pro Theme Engine.
        </div>
      </div>
    </footer>
  </div>
</div>

<!-- Core Scripts -->
<script>
  if (window.lucide) {
    lucide.createIcons();
  }

  function toggleSmmMobileMenu() {
    const drawer = document.getElementById('smm-mobile-drawer');
    if (drawer) {
      drawer.classList.toggle('hidden');
    }
  }

  function toggleSmmCurrencyDropdown() {
    const menu = document.getElementById('smm-currency-menu');
    if (menu) {
      menu.classList.toggle('hidden');
    }
  }

  document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('smm-currency-wrapper');
    const menu = document.getElementById('smm-currency-menu');
    if (wrapper && menu && !wrapper.contains(e.target)) {
      menu.classList.add('hidden');
    }
  });

  function switchSmmCurrency(curr) {
    fetch('/api/currency/switch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ currency: curr })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      }
    })
    .catch(() => {
      window.location.reload();
    });
  }
</script>
</body>
</html>
