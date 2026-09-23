<?php
$pageTitle = 'Coupons & Discounts - Admin Console';
$adminPage = 'coupons';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/CouponHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_coupon' || $_POST['action'] === 'edit_coupon') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['discount_type'] ?? 'percentage';
        $val = (float)($_POST['discount_value'] ?? 0);
        $minOrder = (float)($_POST['min_order_amount'] ?? 0);
        $maxDisc = (float)($_POST['max_discount'] ?? 0);
        $maxUses = !empty($_POST['total_usage_limit']) ? (int)$_POST['total_usage_limit'] : 0;
        $perUser = !empty($_POST['per_user_limit']) ? (int)$_POST['per_user_limit'] : 1;
        $startsAt = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $isEnabled = !empty($_POST['is_enabled']) ? 1 : 0;
        $couponId = (int)($_POST['coupon_id'] ?? 0);

        if (empty($code) || $val <= 0) {
            $error = 'Valid coupon code and discount value are required.';
        } else {
            if ($_POST['action'] === 'create_coupon') {
                $stmt = $db->prepare("
                    INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount, total_usage_limit, per_user_limit, starts_at, expires_at, is_enabled, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$code, $type, $val, $minOrder, $maxDisc ?: null, $maxUses, $perUser, $startsAt, $expiresAt, $isEnabled]);
                $msg = "Coupon '{$code}' created successfully.";
            } else {
                $stmt = $db->prepare("
                    UPDATE coupons 
                    SET code = ?, discount_type = ?, discount_value = ?, min_order_amount = ?, max_discount = ?, total_usage_limit = ?, per_user_limit = ?, starts_at = ?, expires_at = ?, is_enabled = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $type, $val, $minOrder, $maxDisc ?: null, $maxUses, $perUser, $startsAt, $expiresAt, $isEnabled, $couponId]);
                $msg = "Coupon '{$code}' updated successfully.";
            }
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $couponId = (int)$_POST['coupon_id'];
        $db->prepare("UPDATE coupons SET is_enabled = IF(is_enabled=1, 0, 1) WHERE id = ?")->execute([$couponId]);
        $msg = 'Coupon status toggled.';
    } elseif ($_POST['action'] === 'delete_coupon') {
        $couponId = (int)$_POST['coupon_id'];
        $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$couponId]);
        $msg = 'Coupon deleted.';
    }
}

// Fetch all coupons
$coupons = $db->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();

// Fetch recent usages
$usages = $db->query("
    SELECT u.*, c.code, usr.username
    FROM coupon_usages u
    JOIN coupons c ON u.coupon_id = c.id
    JOIN users usr ON u.user_id = usr.id
    ORDER BY u.id DESC LIMIT 40
")->fetchAll();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-50 border border-purple-200/60 text-purple-700 text-xs font-bold mb-2">
        <i data-lucide="tag" class="w-3.5 h-3.5"></i> Promo Codes & Discounts
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Coupons & Discount Management
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Create percentage and fixed-amount coupon codes, set usage limits, and track redemption history.
      </p>
    </div>

    <!-- Create Coupon Button -->
    <button 
      type="button" 
      onclick="openCouponModal()"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
    >
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>Create New Coupon</span>
    </button>
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

  <!-- Coupons List Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Active & Historical Coupons</h3>
        <p class="text-xs text-slate-500 mt-0.5">Manage codes available for user redemption during checkout.</p>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
        <?= count($coupons) ?> Coupons
      </span>
    </div>

    <?php if (empty($coupons)): ?>
      <div class="text-center py-10 text-slate-400 text-xs font-medium">No coupons found. Create your first coupon above.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Code</th>
              <th class="py-2.5 px-3">Discount</th>
              <th class="py-2.5 px-3">Min Order</th>
              <th class="py-2.5 px-3">Max Discount</th>
              <th class="py-2.5 px-3">Usage (Total / Limit)</th>
              <th class="py-2.5 px-3">Per-User Limit</th>
              <th class="py-2.5 px-3">Validity</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($coupons as $c): ?>
              <tr>
                <td class="py-3 px-3">
                  <span class="font-mono font-black text-rose-600 bg-rose-50 px-2 py-1 rounded border border-rose-100 text-xs">
                    <?= e($c['code']) ?>
                  </span>
                </td>
                <td class="py-3 px-3 font-bold text-slate-800">
                  <?= $c['discount_type'] === 'percentage' ? $c['discount_value'] . '%' : '$' . number_format($c['discount_value'], 2) ?>
                </td>
                <td class="py-3 px-3 text-slate-600">
                  <?= (float)$c['min_order_amount'] > 0 ? '$' . number_format($c['min_order_amount'], 2) : 'None' ?>
                </td>
                <td class="py-3 px-3 text-slate-600">
                  <?= (float)($c['max_discount'] ?? 0) > 0 ? '$' . number_format($c['max_discount'], 2) : 'Unlimited' ?>
                </td>
                <td class="py-3 px-3 font-semibold text-slate-700">
                  <?= $c['used_count'] ?> / <?= !empty($c['total_usage_limit']) ? $c['total_usage_limit'] : '∞' ?>
                </td>
                <td class="py-3 px-3 text-slate-600"><?= $c['per_user_limit'] ?>x</td>
                <td class="py-3 px-3 text-[11px] text-slate-500">
                  <?= !empty($c['expires_at']) ? date('M d, Y', strtotime($c['expires_at'])) : 'No Expiry' ?>
                </td>
                <td class="py-3 px-3">
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="cursor-pointer">
                      <?php if (!empty($c['is_enabled'])): ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                      <?php else: ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Inactive</span>
                      <?php endif; ?>
                    </button>
                  </form>
                </td>
                <td class="py-3 px-3 text-right space-x-2">
                  <button type="button" onclick='editCoupon(<?= json_encode($c) ?>)' class="text-slate-600 hover:text-slate-900 font-bold">
                    Edit
                  </button>
                  <form method="POST" class="inline" onsubmit="return confirm('Delete this coupon?');">
                    <input type="hidden" name="action" value="delete_coupon">
                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
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

  <!-- Coupon Usages Audit Log -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Recent Coupon Redemptions</h3>
        <p class="text-xs text-slate-500 mt-0.5">Audit log of customer orders placed using coupons.</p>
      </div>
      <span class="text-xs text-slate-400 font-semibold"><?= count($usages) ?> Redemptions</span>
    </div>

    <?php if (empty($usages)): ?>
      <div class="text-center py-8 text-slate-400 text-xs">No coupon redemptions recorded yet.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Redemption ID</th>
              <th class="py-2.5 px-3">Coupon</th>
              <th class="py-2.5 px-3">User</th>
              <th class="py-2.5 px-3">Order #</th>
              <th class="py-2.5 px-3">Discount Saved</th>
              <th class="py-2.5 px-3">Final Charged</th>
              <th class="py-2.5 px-3">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($usages as $u): ?>
              <tr>
                <td class="py-2.5 px-3 font-mono text-slate-400">#<?= $u['id'] ?></td>
                <td class="py-2.5 px-3 font-mono font-bold text-rose-600"><?= e($u['code']) ?></td>
                <td class="py-2.5 px-3 font-semibold text-slate-700"><?= e($u['username']) ?></td>
                <td class="py-2.5 px-3 font-bold text-slate-800">
                  <a href="/admin/orders?search=<?= $u['order_id'] ?>" class="hover:underline">
                    #<?= $u['order_id'] ?>
                  </a>
                </td>
                <td class="py-2.5 px-3 font-black text-emerald-600">-$<?= number_format($u['discount_amount'], 4) ?></td>
                <td class="py-2.5 px-3 font-bold text-slate-800">$<?= number_format($u['final_amount'], 4) ?></td>
                <td class="py-2.5 px-3 text-slate-400"><?= date('M d, H:i', strtotime($u['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Create / Edit Coupon Modal -->
<div id="couponModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl border border-slate-200">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h3 class="text-base font-black text-slate-800" id="couponModalTitle">Create Coupon</h3>
      <button type="button" onclick="closeCouponModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" id="couponForm" class="space-y-4 text-xs">
      <input type="hidden" name="action" id="formAction" value="create_coupon">
      <input type="hidden" name="coupon_id" id="formCouponId" value="0">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Coupon Code</label>
          <input type="text" name="code" id="couponCode" required placeholder="e.g. VIP20 or BONUS5" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-mono font-bold uppercase focus:border-rose-400">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Discount Type</label>
          <select name="discount_type" id="couponDiscountType" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed">Fixed Amount ($)</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Discount Value</label>
          <input type="number" step="0.01" name="discount_value" id="couponDiscountValue" required placeholder="10" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-bold">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Min Order ($)</label>
          <input type="number" step="0.01" name="min_order_amount" id="couponMinOrder" value="0.00" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Max Cap ($)</label>
          <input type="number" step="0.01" name="max_discount" id="couponMaxDisc" placeholder="Optional" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Total Max Uses</label>
          <input type="number" name="total_usage_limit" id="couponMaxUses" placeholder="Unlimited" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Per-User Limit</label>
          <input type="number" name="per_user_limit" id="couponPerUser" value="1" min="1" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Starts At (Optional)</label>
          <input type="datetime-local" name="starts_at" id="couponStartsAt" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Expires At (Optional)</label>
          <input type="datetime-local" name="expires_at" id="couponExpiresAt" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200">
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Status</label>
        <select name="is_enabled" id="couponIsEnabled" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold">
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
        <button type="button" onclick="closeCouponModal()" class="px-4 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 cursor-pointer">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-sm cursor-pointer">
          Save Coupon
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openCouponModal() {
  document.getElementById('couponModalTitle').textContent = 'Create Coupon';
  document.getElementById('formAction').value = 'create_coupon';
  document.getElementById('formCouponId').value = '0';
  document.getElementById('couponCode').value = '';
  document.getElementById('couponDiscountType').value = 'percentage';
  document.getElementById('couponDiscountValue').value = '';
  document.getElementById('couponMinOrder').value = '0';
  document.getElementById('couponMaxDisc').value = '';
  document.getElementById('couponMaxUses').value = '';
  document.getElementById('couponPerUser').value = '1';
  document.getElementById('couponStartsAt').value = '';
  document.getElementById('couponExpiresAt').value = '';
  document.getElementById('couponIsEnabled').value = '1';
  document.getElementById('couponModal').classList.remove('hidden');
}

function closeCouponModal() {
  document.getElementById('couponModal').classList.add('hidden');
}

function editCoupon(c) {
  document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
  document.getElementById('formAction').value = 'edit_coupon';
  document.getElementById('formCouponId').value = c.id;
  document.getElementById('couponCode').value = c.code;
  document.getElementById('couponDiscountType').value = c.discount_type;
  document.getElementById('couponDiscountValue').value = c.discount_value;
  document.getElementById('couponMinOrder').value = c.min_order_amount;
  document.getElementById('couponMaxDisc').value = c.max_discount || '';
  document.getElementById('couponMaxUses').value = c.total_usage_limit || '';
  document.getElementById('couponPerUser').value = c.per_user_limit || '1';
  document.getElementById('couponStartsAt').value = c.starts_at ? c.starts_at.replace(' ', 'T') : '';
  document.getElementById('couponExpiresAt').value = c.expires_at ? c.expires_at.replace(' ', 'T') : '';
  document.getElementById('couponIsEnabled').value = c.is_enabled == 1 ? '1' : '0';
  document.getElementById('couponModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
