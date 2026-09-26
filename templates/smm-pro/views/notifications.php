<?php
require_once __DIR__ . '/../../../config/database.php';

if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$user = current_user();
$userId = $user['id'];
$db = getDB();

$markStmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
$markStmt->execute([$userId]);

$pageTitle = 'Notifications - SMM Pro';
$activePage = 'notifications';
require_once __DIR__ . '/../layouts/header.php';

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
?>

<div class="space-y-6 max-w-4xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="bell" class="w-6 h-6 text-[#FF2D78]"></i>
        Notifications
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Platform alerts, fulfillment notifications, and system bulletins.</p>
    </div>
    <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full bg-[#10B981]/15 text-[#34D399] border border-[#10B981]/30">
      <i data-lucide="check-check" class="w-3.5 h-3.5"></i> All caught up
    </span>
  </div>

  <div class="smm-card p-6 space-y-3">
    <?php if (empty($notifications)): ?>
      <div class="text-center py-12">
        <div class="w-12 h-12 rounded-2xl bg-white/5 text-[#FF2D78] flex items-center justify-center mx-auto mb-3">
          <i data-lucide="bell-off" class="w-6 h-6"></i>
        </div>
        <h3 class="text-sm font-bold text-white mb-1">No notifications</h3>
        <p class="text-xs text-[#6C6C8A]">You have no unread notifications or announcements at this time.</p>
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="p-4 rounded-2xl bg-[#161628] border border-white/5 hover:border-[#FF2D78]/30 transition-all flex items-start gap-4">
          <div class="w-10 h-10 rounded-2xl bg-[#FF2D78]/15 border border-[#FF2D78]/30 text-[#FF2D78] flex items-center justify-center shrink-0">
            <i data-lucide="<?= $n['type'] === 'order' ? 'shopping-bag' : ($n['type'] === 'wallet' ? 'credit-card' : 'bell') ?>" class="w-5 h-5"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2 mb-1">
              <h4 class="font-bold text-xs sm:text-sm text-white truncate"><?= e($n['title']) ?></h4>
              <span class="text-[10px] text-[#6C6C8A] whitespace-nowrap"><?= date('M d, H:i', strtotime($n['created_at'])) ?></span>
            </div>
            <p class="text-xs text-[#9D9DB8] leading-relaxed"><?= e($n['message']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
