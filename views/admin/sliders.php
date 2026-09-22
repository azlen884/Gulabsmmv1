<?php
$pageTitle = 'Dashboard Sliders - Admin Console';
$adminPage = 'sliders';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_slider') {
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $btnText = trim($_POST['button_text'] ?: 'Boost Now');
        $btnLink = trim($_POST['button_link'] ?: '/order');
        $image = trim($_POST['image_url'] ?? '');

        if (!empty($title)) {
            $stmt = $db->prepare("INSERT INTO sliders (title, subtitle, button_text, button_link, image_url, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$title, $subtitle, $btnText, $btnLink, $image]);
            $msg = "New dashboard slider added.";
        }
    } elseif ($action === 'toggle_status') {
        $sliderId = (int)$_POST['slider_id'];
        $curr = $_POST['status'];
        $next = $curr === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE sliders SET status = ? WHERE id = ?")->execute([$next, $sliderId]);
        $msg = "Slider banner status changed.";
    } elseif ($action === 'delete_slider') {
        $sliderId = (int)$_POST['slider_id'];
        $db->prepare("DELETE FROM sliders WHERE id = ?")->execute([$sliderId]);
        $msg = "Slider deleted.";
    }
}

$sliders = $db->query("SELECT * FROM sliders ORDER BY sort_order ASC, id DESC")->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Dashboard Banners & Sliders</h1>
    <p class="text-xs text-slate-500 mt-1">Configure the rotating promotional banners displayed on the customer dashboard.</p>
  </div>
  <button onclick="document.getElementById('new-slider-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
    <i data-lucide="plus-circle" class="w-4 h-4"></i>
    <span>Add Banner Slider</span>
  </button>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<!-- Slider Cards (NO TABLE UI!) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <?php foreach ($sliders as $s): ?>
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-4">
          <span class="px-3 py-1 rounded-full text-xs font-bold <?= $s['status'] === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' ?>">
            <?= ucfirst($s['status']) ?>
          </span>
          <span class="text-xs font-mono text-slate-400">Order: <?= $s['sort_order'] ?></span>
        </div>

        <div class="p-4 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 text-white mb-4 shadow-sm">
          <h3 class="text-lg font-black tracking-tight mb-1"><?= e($s['title']) ?></h3>
          <p class="text-xs text-rose-100 mb-3"><?= e($s['subtitle']) ?></p>
          <span class="inline-block px-3 py-1 rounded-full bg-white text-rose-600 text-[11px] font-bold">
            <?= e($s['button_text']) ?> →
          </span>
        </div>

        <div class="text-xs text-slate-500 space-y-1">
          <div>Action Link: <span class="font-mono text-slate-700 font-bold"><?= e($s['button_link']) ?></span></div>
          <?php if (!empty($s['image_url'])): ?>
            <div>Graphic: <span class="font-mono text-slate-400 text-[10px] break-all"><?= e($s['image_url']) ?></span></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="flex items-center justify-between pt-4 border-t border-slate-100 mt-4 text-xs">
        <form method="POST">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="slider_id" value="<?= $s['id'] ?>">
          <input type="hidden" name="status" value="<?= $s['status'] ?>">
          <button type="submit" class="text-xs font-bold text-slate-600 hover:text-slate-800">
            <?= $s['status'] === 'active' ? 'Disable Banner' : 'Activate Banner' ?>
          </button>
        </form>

        <form method="POST" onsubmit="return confirm('Delete this banner?')">
          <input type="hidden" name="action" value="delete_slider">
          <input type="hidden" name="slider_id" value="<?= $s['id'] ?>">
          <button type="submit" class="text-xs font-bold text-red-500 hover:text-red-700">
            Delete
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal -->
<div id="new-slider-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 border border-slate-200 shadow-2xl relative">
    <button onclick="document.getElementById('new-slider-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-4">Add Dashboard Banner</h2>

    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="action" value="create_slider">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Headline Title</label>
        <input type="text" name="title" required placeholder="e.g. 50% Off YouTube Views" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Subtitle / Promo description</label>
        <input type="text" name="subtitle" required placeholder="e.g. Instant high retention start time." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Button Text</label>
        <input type="text" name="button_text" value="Order Now" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Target Link</label>
        <input type="text" name="button_link" value="/order" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('new-slider-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Save Banner</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
