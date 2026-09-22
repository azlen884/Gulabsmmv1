<?php
$pageTitle = 'Categories Manager - Admin Console';
$adminPage = 'categories';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_category') {
        $name = trim($_POST['name']);
        $icon = trim($_POST['icon'] ?? 'folder');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        if (!empty($name)) {
            $stmt = $db->prepare("INSERT INTO categories (name, slug, icon, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$name, $slug, $icon]);
            $msg = "Category \"$name\" created.";
        }
    } elseif ($action === 'toggle_status') {
        $catId = (int)$_POST['category_id'];
        $curr = $_POST['status'];
        $next = $curr === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE categories SET status = ? WHERE id = ?")->execute([$next, $catId]);
        $msg = "Category status updated.";
    }
}

$categories = $db->query("
    SELECT c.*, (SELECT COUNT(*) FROM services WHERE category_id = c.id) AS service_count 
    FROM categories c 
    ORDER BY c.sort_order ASC, c.id ASC
")->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Categories Manager</h1>
    <p class="text-xs text-slate-500 mt-1">Organize social media platforms, games, and service groupings.</p>
  </div>
  <button onclick="document.getElementById('new-cat-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
    <i data-lucide="plus-circle" class="w-4 h-4"></i>
    <span>Add Category</span>
  </button>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<!-- Category Card List (NO TABLE UI!) -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
  <?php foreach ($categories as $c): ?>
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center">
            <i data-lucide="<?= e($c['icon'] ?: 'folder') ?>" class="w-5 h-5"></i>
          </div>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $c['status'] === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' ?>">
            <?= ucfirst($c['status']) ?>
          </span>
        </div>

        <h3 class="font-bold text-base text-slate-800 mb-1"><?= e($c['name']) ?></h3>
        <p class="text-xs text-slate-400 font-mono mb-4">slug: <?= e($c['slug']) ?></p>
      </div>

      <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
        <span class="font-bold text-slate-600"><?= $c['service_count'] ?> Services</span>
        <form method="POST">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
          <input type="hidden" name="status" value="<?= $c['status'] ?>">
          <button type="submit" class="text-slate-400 hover:text-slate-600 text-xs font-semibold">
            <?= $c['status'] === 'active' ? 'Disable' : 'Enable' ?>
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal -->
<div id="new-cat-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 border border-slate-200 shadow-2xl relative">
    <button onclick="document.getElementById('new-cat-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-4">Add Category</h2>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create_category">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Category Name</label>
        <input type="text" name="name" required placeholder="e.g. Spotify Streams" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Lucide Icon Name</label>
        <input type="text" name="icon" value="music" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('new-cat-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Save</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
