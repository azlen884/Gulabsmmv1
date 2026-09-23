<?php
$pageTitle = 'Flash Sales Management - Admin Console';
$adminPage = 'flash-sales';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/FlashSaleHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_sale' || $_POST['action'] === 'edit_sale') {
        $title = trim($_POST['title'] ?? '');
        $badge = trim($_POST['badge_text'] ?? 'FLASH SALE');
        $type = $_POST['discount_type'] ?? 'percentage';
        $val = (float)($_POST['discount_value'] ?? 0);
        $appliesTo = $_POST['applies_to'] ?? 'all';
        $targetId = '';
        if ($appliesTo === 'category') {
            $targetId = !empty($_POST['category_id']) ? (string)$_POST['category_id'] : '';
        } elseif ($appliesTo === 'service') {
            $targetId = !empty($_POST['service_id']) ? (string)$_POST['service_id'] : '';
        }
        $startsAt = !empty($_POST['starts_at']) ? $_POST['starts_at'] : date('Y-m-d H:i:s');
        $endsAt = !empty($_POST['ends_at']) ? $_POST['ends_at'] : date('Y-m-d H:i:s', strtotime('+7 days'));
        $isEnabled = !empty($_POST['is_enabled']) ? 1 : 0;
        $saleId = (int)($_POST['sale_id'] ?? 0);

        if (empty($title) || $val <= 0) {
            $error = 'Valid campaign title and discount value are required.';
        } else {
            if ($_POST['action'] === 'create_sale') {
                $stmt = $db->prepare("
                    INSERT INTO flash_sales (title, badge_text, discount_type, discount_value, applies_to, target_ids, starts_at, ends_at, is_enabled, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$title, $badge, $type, $val, $appliesTo, $targetId, $startsAt, $endsAt, $isEnabled]);
                $msg = "Flash Sale campaign '{$title}' created successfully.";
            } else {
                $stmt = $db->prepare("
                    UPDATE flash_sales 
                    SET title = ?, badge_text = ?, discount_type = ?, discount_value = ?, applies_to = ?, target_ids = ?, starts_at = ?, ends_at = ?, is_enabled = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $badge, $type, $val, $appliesTo, $targetId, $startsAt, $endsAt, $isEnabled, $saleId]);
                $msg = "Flash Sale campaign '{$title}' updated successfully.";
            }
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $saleId = (int)$_POST['sale_id'];
        $db->prepare("UPDATE flash_sales SET is_enabled = IF(is_enabled=1, 0, 1) WHERE id = ?")->execute([$saleId]);
        $msg = 'Campaign status updated.';
    } elseif ($_POST['action'] === 'delete_sale') {
        $saleId = (int)$_POST['sale_id'];
        $db->prepare("DELETE FROM flash_sales WHERE id = ?")->execute([$saleId]);
        $msg = 'Flash sale deleted.';
    }
}

// Fetch categories & services for selectors
$categories = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$services = $db->query("SELECT id, name FROM services WHERE status = 'active' ORDER BY name ASC LIMIT 100")->fetchAll();

// Fetch sales
$sales = $db->query("
    SELECT fs.*
    FROM flash_sales fs
    ORDER BY fs.id DESC
")->fetchAll();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 border border-rose-200/60 text-rose-700 text-xs font-bold mb-2">
        <i data-lucide="zap" class="w-3.5 h-3.5"></i> Limited-Time Boost Campaigns
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Flash Sales Management
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Launch countdown discounts, boost service visibility with promo badges, and automate campaign expirations.
      </p>
    </div>

    <!-- Create Button -->
    <div class="flex items-center gap-2">
      <a href="/flash-sales" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-bold text-xs shadow-sm">
        <i data-lucide="external-link" class="w-4 h-4 text-rose-500"></i>
        <span>View Customer Hub</span>
      </a>
      <button 
        type="button" 
        onclick="openSaleModal()"
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
      >
        <i data-lucide="plus" class="w-4 h-4"></i>
        <span>Create Flash Sale</span>
      </button>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- Flash Sales Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Flash Sale Campaigns</h3>
        <p class="text-xs text-slate-500 mt-0.5">Automated discounted pricing applied in checkout.</p>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
        <?= count($sales) ?> Campaigns
      </span>
    </div>

    <?php if (empty($sales)): ?>
      <div class="text-center py-10 text-slate-400 text-xs font-medium">No flash sales created yet. Click "Create Flash Sale" to launch a promotion.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Campaign</th>
              <th class="py-2.5 px-3">Discount</th>
              <th class="py-2.5 px-3">Scope</th>
              <th class="py-2.5 px-3">Schedule Window</th>
              <th class="py-2.5 px-3">Purchases</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($sales as $s): ?>
              <tr>
                <td class="py-3.5 px-3">
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700 uppercase tracking-wider">
                      <?= e($s['badge_text']) ?>
                    </span>
                    <span class="font-bold text-slate-800"><?= e($s['title']) ?></span>
                  </div>
                </td>
                <td class="py-3.5 px-3 font-black text-rose-600">
                  <?= $s['discount_type'] === 'percentage' ? $s['discount_value'] . '% OFF' : '$' . number_format($s['discount_value'], 2) . ' OFF' ?>
                </td>
                <td class="py-3.5 px-3">
                  <?php if ($s['applies_to'] === 'all'): ?>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700">Store-wide</span>
                  <?php elseif ($s['applies_to'] === 'category'): ?>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700">Category #<?= e($s['target_ids']) ?></span>
                  <?php else: ?>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 truncate max-w-xs block">
                      Service #<?= e($s['target_ids']) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-3.5 px-3 text-[11px] text-slate-500">
                  <?= date('M d', strtotime($s['starts_at'])) ?> → <?= date('M d, Y H:i', strtotime($s['ends_at'])) ?>
                </td>
                <td class="py-3.5 px-3 font-bold text-slate-700">
                  <?= number_format($s['sales_count']) ?>
                </td>
                <td class="py-3.5 px-3">
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                    <button type="submit" class="cursor-pointer">
                      <?php if (!empty($s['is_enabled'])): ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
                        </span>
                      <?php else: ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Inactive</span>
                      <?php endif; ?>
                    </button>
                  </form>
                </td>
                <td class="py-3.5 px-3 text-right space-x-2">
                  <button type="button" onclick='editSale(<?= json_encode($s) ?>)' class="text-slate-600 hover:text-slate-900 font-bold">
                    Edit
                  </button>
                  <form method="POST" class="inline" onsubmit="return confirm('Delete this flash sale?');">
                    <input type="hidden" name="action" value="delete_sale">
                    <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                    <button type="submit" class="text-rose-600 hover:text-rose-800 font-bold cursor-pointer">
                      Delete
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

<!-- Create / Edit Sale Modal -->
<div id="saleModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl border border-slate-200">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h3 class="text-base font-black text-slate-800" id="saleModalTitle">Create Flash Sale</h3>
      <button type="button" onclick="closeSaleModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" id="saleForm" class="space-y-4 text-xs">
      <input type="hidden" name="action" id="formAction" value="create_sale">
      <input type="hidden" name="sale_id" id="formSaleId" value="0">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Campaign Title</label>
          <input type="text" name="title" id="saleTitle" required placeholder="e.g. Midnight Weekend Madness" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-bold focus:border-rose-400">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Badge Text</label>
          <input type="text" name="badge_text" id="saleBadge" value="FLASH DEAL" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-bold uppercase text-rose-600">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Discount Type</label>
          <select name="discount_type" id="saleType" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed_discount">Fixed Amount ($)</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Discount Value</label>
          <input type="number" step="0.01" name="discount_value" id="saleVal" required placeholder="25" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-bold">
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Campaign Scope</label>
        <select name="applies_to" id="saleScope" onchange="toggleScopeInputs()" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold">
          <option value="all">All Services (Store-wide)</option>
          <option value="category">Specific Category</option>
          <option value="service">Specific Single Service</option>
        </select>
      </div>

      <div id="catSelectWrapper" class="hidden">
        <label class="block font-bold text-slate-700 mb-1">Target Category</label>
        <select name="category_id" id="saleCatId" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
          <option value="">Select Category</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="srvSelectWrapper" class="hidden">
        <label class="block font-bold text-slate-700 mb-1">Target Service</label>
        <select name="service_id" id="saleSrvId" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
          <option value="">Select Service</option>
          <?php foreach ($services as $srv): ?>
            <option value="<?= $srv['id'] ?>">#<?= $srv['id'] ?> - <?= e($srv['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Starts At</label>
          <input type="datetime-local" name="starts_at" id="saleStartsAt" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Ends At</label>
          <input type="datetime-local" name="ends_at" id="saleEndsAt" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Status</label>
        <select name="is_enabled" id="saleIsEnabled" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold">
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
        <button type="button" onclick="closeSaleModal()" class="px-4 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 cursor-pointer">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-sm cursor-pointer">
          Save Campaign
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleScopeInputs() {
  const scope = document.getElementById('saleScope').value;
  document.getElementById('catSelectWrapper').classList.toggle('hidden', scope !== 'category');
  document.getElementById('srvSelectWrapper').classList.toggle('hidden', scope !== 'service');
}

function openSaleModal() {
  document.getElementById('saleModalTitle').textContent = 'Create Flash Sale';
  document.getElementById('formAction').value = 'create_sale';
  document.getElementById('formSaleId').value = '0';
  document.getElementById('saleTitle').value = '';
  document.getElementById('saleBadge').value = 'FLASH DEAL';
  document.getElementById('saleType').value = 'percentage';
  document.getElementById('saleVal').value = '';
  document.getElementById('saleScope').value = 'all';
  document.getElementById('saleCatId').value = '';
  document.getElementById('saleSrvId').value = '';
  const now = new Date();
  const nextWeek = new Date(Date.now() + 7 * 24 * 3600 * 1000);
  document.getElementById('saleStartsAt').value = now.toISOString().slice(0, 16);
  document.getElementById('saleEndsAt').value = nextWeek.toISOString().slice(0, 16);
  document.getElementById('saleIsEnabled').value = '1';
  toggleScopeInputs();
  document.getElementById('saleModal').classList.remove('hidden');
}

function closeSaleModal() {
  document.getElementById('saleModal').classList.add('hidden');
}

function editSale(s) {
  document.getElementById('saleModalTitle').textContent = 'Edit Flash Sale';
  document.getElementById('formAction').value = 'edit_sale';
  document.getElementById('formSaleId').value = s.id;
  document.getElementById('saleTitle').value = s.title;
  document.getElementById('saleBadge').value = s.badge_text || 'FLASH DEAL';
  document.getElementById('saleType').value = s.discount_type;
  document.getElementById('saleVal').value = s.discount_value;
  document.getElementById('saleScope').value = s.applies_to;
  if (s.applies_to === 'category') {
    document.getElementById('saleCatId').value = s.target_ids || '';
  } else if (s.applies_to === 'service') {
    document.getElementById('saleSrvId').value = s.target_ids || '';
  }
  document.getElementById('saleStartsAt').value = s.starts_at ? s.starts_at.replace(' ', 'T').slice(0, 16) : '';
  document.getElementById('saleEndsAt').value = s.ends_at ? s.ends_at.replace(' ', 'T').slice(0, 16) : '';
  document.getElementById('saleIsEnabled').value = s.is_enabled == 1 ? '1' : '0';
  toggleScopeInputs();
  document.getElementById('saleModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
