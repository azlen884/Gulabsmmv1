<?php
$pageTitle = 'Currencies & Rates - Admin Console';
$adminPage = 'currencies';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_rate') {
        $currId = (int)$_POST['currency_id'];
        $rate = (float)$_POST['rate'];
        $symbol = trim($_POST['symbol']);

        $db->prepare("UPDATE currencies SET rate = ?, symbol = ? WHERE id = ?")->execute([$rate, $symbol, $currId]);
        $msg = "Currency rate updated.";
    } elseif ($action === 'set_default') {
        $currId = (int)$_POST['currency_id'];
        $cStmt = $db->prepare("SELECT code, symbol FROM currencies WHERE id = ?");
        $cStmt->execute([$currId]);
        $curr = $cStmt->fetch();

        if ($curr) {
            $db->exec("UPDATE currencies SET is_default = 0");
            $db->prepare("UPDATE currencies SET is_default = 1 WHERE id = ?")->execute([$currId]);
            $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'currency_default'")->execute([$curr['code']]);
            $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'currency_symbol'")->execute([$curr['symbol']]);
            $msg = "Default platform currency set to " . $curr['code'] . " (" . $curr['symbol'] . ").";
        }
    } elseif ($action === 'add_currency') {
        $code = strtoupper(trim($_POST['code']));
        $name = trim($_POST['name']);
        $symbol = trim($_POST['symbol']);
        $rate = (float)$_POST['rate'];

        if (!empty($code) && !empty($symbol) && $rate > 0) {
            $db->prepare("INSERT INTO currencies (code, name, symbol, rate, is_default, status) VALUES (?, ?, ?, ?, 0, 'active')")
                ->execute([$code, $name, $symbol, $rate]);
            $msg = "Added new currency $code.";
        }
    }
}

$currencies = $db->query("SELECT * FROM currencies ORDER BY is_default DESC, id ASC")->fetchAll();
$defaultCurrency = get_setting('currency_default', 'INR');
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Currencies & FX Rates</h1>
    <p class="text-xs text-slate-500 mt-1">Configure exchange rates against USD base and designate default panel currency (e.g. INR / USD).</p>
  </div>
  <button onclick="document.getElementById('new-curr-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
    <i data-lucide="plus-circle" class="w-4 h-4"></i>
    <span>Add Currency</span>
  </button>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<!-- Currency Card List (NO TABLE UI!) -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
  <?php foreach ($currencies as $c): ?>
    <div class="bg-white rounded-3xl border <?= $c['is_default'] ? 'border-rose-400 ring-2 ring-rose-100' : 'border-slate-200' ?> p-6 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 font-black text-sm flex items-center justify-center">
              <?= e($c['symbol']) ?>
            </div>
            <div>
              <h3 class="font-extrabold text-base text-slate-800"><?= e($c['code']) ?></h3>
              <div class="text-[11px] text-slate-400"><?= e($c['name']) ?></div>
            </div>
          </div>

          <?php if ($c['is_default']): ?>
            <span class="px-3 py-1 rounded-full bg-rose-500 text-white text-[10px] font-extrabold tracking-wider uppercase">
              DEFAULT
            </span>
          <?php endif; ?>
        </div>

        <form method="POST" class="space-y-3 mb-4">
          <input type="hidden" name="action" value="update_rate">
          <input type="hidden" name="currency_id" value="<?= $c['id'] ?>">

          <div>
            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Exchange Rate (per $1 USD)</label>
            <input 
              type="number" 
              step="0.000001" 
              name="rate" 
              value="<?= $c['rate'] ?>" 
              required 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold"
            >
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Display Symbol</label>
            <input 
              type="text" 
              name="symbol" 
              value="<?= e($c['symbol']) ?>" 
              required 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold"
            >
          </div>

          <button type="submit" class="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs transition-colors">
            Save Rate
          </button>
        </form>
      </div>

      <?php if (!$c['is_default']): ?>
        <form method="POST" class="pt-2 border-t border-slate-100 text-center">
          <input type="hidden" name="action" value="set_default">
          <input type="hidden" name="currency_id" value="<?= $c['id'] ?>">
          <button type="submit" class="text-xs font-bold text-rose-600 hover:underline">
            Set as Default Currency
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal -->
<div id="new-curr-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 border border-slate-200 shadow-2xl relative">
    <button onclick="document.getElementById('new-curr-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-4">Add Currency</h2>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="add_currency">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Currency Code (3 Letters)</label>
        <input type="text" name="code" required placeholder="e.g. CAD" maxlength="5" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs uppercase font-mono">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Currency Name</label>
        <input type="text" name="name" required placeholder="e.g. Canadian Dollar" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Symbol</label>
        <input type="text" name="symbol" required placeholder="e.g. C$" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Exchange Rate (per $1 USD)</label>
        <input type="number" step="0.000001" name="rate" required placeholder="e.g. 1.35" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('new-curr-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Add Currency</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
