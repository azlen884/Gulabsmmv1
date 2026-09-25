    </main>

    <footer class="bg-white border-t border-slate-200 px-4 lg:px-8 py-3.5 text-xs text-slate-400 flex items-center justify-between">
      <div><?= e(get_site_name()) ?> Control Center • Version 2.0</div>
      <div>&copy; <?= date('Y') ?> <?= e(get_site_name()) ?>. All rights reserved.</div>
    </footer>
  </div>
</div>

<script>
  if (window.lucide) {
    lucide.createIcons();
  }

  function toggleAdminCurrencyDropdown() {
    const d = document.getElementById('admin-currency-dropdown');
    if (d) d.classList.toggle('hidden');
  }

  function changeAdminCurrency(code) {
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
    })
    .catch(err => {
      console.error('Failed to change currency:', err);
    });
  }

  document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('admin-currency-dropdown-wrapper');
    const drop = document.getElementById('admin-currency-dropdown');
    if (wrapper && drop && !wrapper.contains(e.target)) {
      drop.classList.add('hidden');
    }
  });
</script>
</body>
</html>
