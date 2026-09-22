<?php
$pageTitle = 'Broadcast Notifications - Admin Console';
$adminPage = 'notifications';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_broadcast') {
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $type = $_POST['type'] ?? 'system';
        $targetUser = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;

        if (!empty($title) && !empty($message)) {
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$targetUser, $title, $message, $type]);
            $msg = "Notification broadcasted successfully.";
        }
    } elseif ($action === 'delete_notification') {
        $notifId = (int)$_POST['notification_id'];
        $db->prepare("DELETE FROM notifications WHERE id = ?")->execute([$notifId]);
        $msg = "Notification deleted.";
    }
}

$notifications = $db->query("
    SELECT n.*, u.username 
    FROM notifications n 
    LEFT JOIN users u ON n.user_id = u.id 
    ORDER BY n.id DESC
")->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Broadcast Notifications</h1>
    <p class="text-xs text-slate-500 mt-1">Send global service updates, promotional alerts, or personal account notices.</p>
  </div>
  <button onclick="document.getElementById('new-broadcast-modal').classList.remove('hidden')" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
    <i data-lucide="send" class="w-4 h-4"></i>
    <span>Send Broadcast</span>
  </button>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<!-- Notifications Card List (NO TABLE UI!) -->
<div class="space-y-3">
  <?php foreach ($notifications as $n): ?>
    <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-start gap-3.5">
        <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
          <i data-lucide="bell" class="w-5 h-5"></i>
        </div>
        <div>
          <h3 class="font-bold text-xs sm:text-sm text-slate-800 flex items-center gap-2">
            <span><?= e($n['title']) ?></span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-100 text-slate-600">
              <?= $n['user_id'] ? '@' . e($n['username']) : 'GLOBAL' ?>
            </span>
          </h3>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?= e($n['message']) ?></p>
          <div class="text-[11px] text-slate-400 mt-1">
            <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?> • Type: <?= e($n['type']) ?>
          </div>
        </div>
      </div>

      <form method="POST" onsubmit="return confirm('Delete this notification?')">
        <input type="hidden" name="action" value="delete_notification">
        <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
        <button type="submit" class="text-xs font-bold text-red-500 hover:text-red-700">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal -->
<div id="new-broadcast-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 border border-slate-200 shadow-2xl relative">
    <button onclick="document.getElementById('new-broadcast-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="text-lg font-bold text-slate-800 mb-4">Send System Broadcast</h2>

    <form method="POST" class="space-y-3.5">
      <input type="hidden" name="action" value="create_broadcast">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Headline Title</label>
        <input type="text" name="title" required placeholder="e.g. Maintenance Announcement" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Message Content</label>
        <textarea name="message" rows="3" required placeholder="Detailed message..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Type</label>
        <select name="type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
          <option value="system">System Notification</option>
          <option value="promo">Promotional / Bonus</option>
          <option value="wallet">Wallet Alert</option>
        </select>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('new-broadcast-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Send Broadcast</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
