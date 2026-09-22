<?php
$pageTitle = 'Notifications - RoseSMM';
$activePage = 'notifications';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = $user['id'];

// If mark all read
if (isset($_GET['mark_all_read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL")->execute([$userId]);
    header("Location: /notifications");
    exit;
}

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">System Notifications</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Stay updated on order completions, deposits, and platform announcements.</p>
  </div>
  <a href="/notifications?mark_all_read=1" class="text-xs font-bold text-rose-500 hover:text-rose-600 bg-rose-50 px-4 py-2 rounded-full transition-colors">
    Mark All as Read
  </a>
</div>

<!-- Notifications Card List (NO TABLE UI!) -->
<div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
  <?php if (empty($notifications)): ?>
    <div class="text-center py-10">
      <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-2">
        <i data-lucide="bell-off" class="w-6 h-6"></i>
      </div>
      <p class="text-xs text-slate-400">No notifications found.</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($notifications as $n): ?>
        <div class="p-4 rounded-2xl border <?= $n['is_read'] ? 'border-slate-100 bg-white' : 'border-rose-200 bg-rose-50/20' ?> transition-all flex items-start gap-4">
          <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 <?= $n['type'] === 'order' ? 'bg-blue-50 text-blue-600' : ($n['type'] === 'wallet' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600') ?>">
            <i data-lucide="<?= $n['type'] === 'order' ? 'shopping-bag' : ($n['type'] === 'wallet' ? 'credit-card' : 'bell') ?>" class="w-5 h-5"></i>
          </div>
          <div class="flex-1">
            <div class="flex items-center justify-between gap-2 mb-1">
              <h4 class="font-bold text-xs sm:text-sm text-slate-800 flex items-center gap-2">
                <span><?= e($n['title']) ?></span>
                <?php if (!$n['is_read']): ?>
                  <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <?php endif; ?>
              </h4>
              <span class="text-[11px] text-slate-400 shrink-0">
                <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
              </span>
            </div>
            <p class="text-xs text-slate-600 leading-relaxed"><?= e($n['message']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
