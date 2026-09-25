<?php
$pageTitle = 'Testimonials & Reviews - Admin Console';
$adminPage = 'testimonials';
require_once __DIR__ . '/../layouts/admin_header.php';

if (!is_admin()) {
    header("Location: /admin/login");
    exit;
}

$db = getDB();
$msg = '';
$error = '';

// Handle Actions (Add, Edit, Delete, Status Toggle)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'Verified Client');
        $avatar = trim($_POST['avatar'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (!empty($name) && !empty($content)) {
            $insStmt = $db->prepare("INSERT INTO testimonials (name, role, avatar, content, rating, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($insStmt->execute([$name, $role, $avatar, $content, $rating, $status, $sortOrder])) {
                $msg = "Testimonial from '{$name}' created successfully.";
            } else {
                $error = "Failed to create testimonial.";
            }
        } else {
            $error = "Name and review content are required.";
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'Verified Client');
        $avatar = trim($_POST['avatar'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($id > 0 && !empty($name) && !empty($content)) {
            $updStmt = $db->prepare("UPDATE testimonials SET name = ?, role = ?, avatar = ?, content = ?, rating = ?, status = ?, sort_order = ? WHERE id = ?");
            if ($updStmt->execute([$name, $role, $avatar, $content, $rating, $status, $sortOrder, $id])) {
                $msg = "Testimonial #{$id} updated successfully.";
            } else {
                $error = "Failed to update testimonial.";
            }
        } else {
            $error = "Name and review content are required.";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $delStmt = $db->prepare("DELETE FROM testimonials WHERE id = ?");
            if ($delStmt->execute([$id])) {
                $msg = "Testimonial #{$id} deleted successfully.";
            } else {
                $error = "Failed to delete testimonial.";
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $togStmt = $db->prepare("UPDATE testimonials SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
            if ($togStmt->execute([$id])) {
                $msg = "Status toggled successfully.";
            }
        }
    }
}

// Fetch all testimonials
$testimonials = [];
try {
    $stmt = $db->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC");
    if ($stmt) {
        $testimonials = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $testimonials = [];
}
?>

<div class="max-w-7xl space-y-6">
  <!-- Top Bar -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        <i data-lucide="message-square" class="w-6 h-6 text-rose-600"></i>
        Testimonials & Client Reviews
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Manage genuine client testimonials displayed on the public landing page. Only real records are published.
      </p>
    </div>
    <button 
      onclick="document.getElementById('modal-create-testimonial').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-600 text-white rounded-xl text-xs font-bold hover:bg-rose-700 shadow-md shadow-rose-600/20 transition-all cursor-pointer"
    >
      <i data-lucide="plus" class="w-4 h-4"></i> Add Real Testimonial
    </button>
  </div>

  <!-- Flash Alerts -->
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

  <!-- Real Testimonials List -->
  <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="list" class="w-4 h-4 text-slate-400"></i>
        Existing Reviews (<?= count($testimonials) ?>)
      </h2>
      <span class="text-xs text-slate-400">Strictly real customer reviews</span>
    </div>

    <?php if (empty($testimonials)): ?>
      <div class="p-12 text-center">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto mb-3 shadow-sm">
          <i data-lucide="message-circle-off" class="w-6 h-6"></i>
        </div>
        <h3 class="font-bold text-sm text-slate-800 mb-1">No Real Testimonials Yet</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mb-5 leading-relaxed">
          The public landing page is currently displaying an authentic verified completion notice rather than fabricating fake reviews.
        </p>
        <button 
          onclick="document.getElementById('modal-create-testimonial').classList.remove('hidden')"
          class="px-5 py-2 bg-rose-600 text-white rounded-xl text-xs font-bold hover:bg-rose-700 shadow-sm transition-all"
        >
          Add First Testimonial
        </button>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
          <thead class="bg-slate-50 text-[11px] uppercase tracking-wider font-bold text-slate-500 border-b border-slate-100">
            <tr>
              <th class="py-3 px-4">Client</th>
              <th class="py-3 px-4">Role / Title</th>
              <th class="py-3 px-4">Review Content</th>
              <th class="py-3 px-4">Rating</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Sort</th>
              <th class="py-3 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($testimonials as $t): ?>
              <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="py-3.5 px-4 font-semibold text-slate-800 whitespace-nowrap">
                  <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center font-bold text-xs shrink-0 overflow-hidden">
                      <?php if (!empty($t['avatar'])): ?>
                        <img src="<?= e($t['avatar']) ?>" alt="<?= e($t['name']) ?>" class="w-full h-full object-cover">
                      <?php else: ?>
                        <?= strtoupper(substr($t['name'], 0, 2)) ?>
                      <?php endif; ?>
                    </div>
                    <span><?= e($t['name']) ?></span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                  <?= e($t['role'] ?: 'Verified Customer') ?>
                </td>
                <td class="py-3.5 px-4 max-w-xs">
                  <div class="truncate text-slate-700" title="<?= e($t['content']) ?>">
                    &ldquo;<?= e($t['content']) ?>&rdquo;
                  </div>
                </td>
                <td class="py-3.5 px-4 whitespace-nowrap text-amber-500 font-bold">
                  ★ <?= (int)$t['rating'] ?>/5
                </td>
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $t['status'] === 'active' ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> transition-colors">
                      <?= ucfirst($t['status']) ?>
                    </button>
                  </form>
                </td>
                <td class="py-3.5 px-4 text-slate-400">
                  <?= (int)$t['sort_order'] ?>
                </td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-2">
                  <button 
                    onclick='editTestimonial(<?= json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                    title="Edit"
                  >
                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                  </button>
                  <form method="POST" class="inline" onsubmit="return confirm('Delete this testimonial?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors" title="Delete">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: Add Testimonial -->
<div id="modal-create-testimonial" class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 shadow-2xl relative">
    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
      <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
        <i data-lucide="message-square-plus" class="w-5 h-5 text-rose-600"></i>
        Add Real Client Testimonial
      </h3>
      <button onclick="document.getElementById('modal-create-testimonial').classList.add('hidden')" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" class="space-y-4 text-xs">
      <input type="hidden" name="action" value="create">

      <div>
        <label class="block font-bold text-slate-700 mb-1">Customer / Client Name *</label>
        <input type="text" name="name" required placeholder="e.g., Sarah Jenkins" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Role / Subtitle</label>
          <input type="text" name="role" placeholder="e.g., Social Agency Owner" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Rating (1-5)</label>
          <select name="rating" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
            <option value="5" selected>5 Stars (★★★★★)</option>
            <option value="4">4 Stars (★★★★☆)</option>
            <option value="3">3 Stars (★★★☆☆)</option>
            <option value="2">2 Stars (★★☆☆☆)</option>
            <option value="1">1 Star (★☆☆☆☆)</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Avatar Image URL (Optional)</label>
        <input type="url" name="avatar" placeholder="https://example.com/avatar.jpg" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Review Content *</label>
        <textarea name="content" required rows="3" placeholder="Genuine client feedback on speed, support, and delivery..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500"></textarea>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Status</label>
          <select name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
            <option value="active" selected>Active (Show on Landing)</option>
            <option value="inactive">Inactive (Hidden)</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Sort Order</label>
          <input type="number" name="sort_order" value="0" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
        </div>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('modal-create-testimonial').classList.add('hidden')" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 font-bold hover:bg-slate-50">Cancel</button>
        <button type="submit" class="px-5 py-2 bg-rose-600 text-white rounded-xl font-bold hover:bg-rose-700 shadow-sm">Save Testimonial</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit Testimonial -->
<div id="modal-edit-testimonial" class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 shadow-2xl relative">
    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
      <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
        <i data-lucide="edit-2" class="w-5 h-5 text-rose-600"></i>
        Edit Client Testimonial
      </h3>
      <button onclick="document.getElementById('modal-edit-testimonial').classList.add('hidden')" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" class="space-y-4 text-xs">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit-id" name="id" value="">

      <div>
        <label class="block font-bold text-slate-700 mb-1">Customer / Client Name *</label>
        <input type="text" id="edit-name" name="name" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Role / Subtitle</label>
          <input type="text" id="edit-role" name="role" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Rating (1-5)</label>
          <select id="edit-rating" name="rating" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
            <option value="5">5 Stars (★★★★★)</option>
            <option value="4">4 Stars (★★★★☆)</option>
            <option value="3">3 Stars (★★★☆☆)</option>
            <option value="2">2 Stars (★★☆☆☆)</option>
            <option value="1">1 Star (★☆☆☆☆)</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Avatar Image URL (Optional)</label>
        <input type="url" id="edit-avatar" name="avatar" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Review Content *</label>
        <textarea id="edit-content" name="content" required rows="3" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500"></textarea>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Status</label>
          <select id="edit-status" name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
            <option value="active">Active (Show on Landing)</option>
            <option value="inactive">Inactive (Hidden)</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Sort Order</label>
          <input type="number" id="edit-sort" name="sort_order" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-rose-500">
        </div>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('modal-edit-testimonial').classList.add('hidden')" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 font-bold hover:bg-slate-50">Cancel</button>
        <button type="submit" class="px-5 py-2 bg-rose-600 text-white rounded-xl font-bold hover:bg-rose-700 shadow-sm">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function editTestimonial(data) {
  document.getElementById('edit-id').value = data.id || '';
  document.getElementById('edit-name').value = data.name || '';
  document.getElementById('edit-role').value = data.role || '';
  document.getElementById('edit-avatar').value = data.avatar || '';
  document.getElementById('edit-content').value = data.content || '';
  document.getElementById('edit-rating').value = data.rating || '5';
  document.getElementById('edit-status').value = data.status || 'active';
  document.getElementById('edit-sort').value = data.sort_order || '0';
  document.getElementById('modal-edit-testimonial').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
