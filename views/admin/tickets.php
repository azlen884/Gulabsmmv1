<?php
$pageTitle = 'Support Tickets - Admin Console';
$adminPage = 'tickets';
require_once __DIR__ . '/../layouts/admin_header.php';

$db = getDB();
$msg = '';

$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$activeTicket = null;
$messages = [];

// Handle Admin Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_reply') {
        $tId = (int)$_POST['ticket_id'];
        $replyText = trim($_POST['message']);

        if ($tId > 0 && !empty($replyText)) {
            $db->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 1)")
                ->execute([$tId, $adminUser['id'], $replyText]);
            $db->prepare("UPDATE tickets SET status = 'answered', updated_at = NOW() WHERE id = ?")->execute([$tId]);
            $msg = "Reply sent to customer.";
            $ticketId = $tId;
        }
    } elseif ($action === 'update_status') {
        $tId = (int)$_POST['ticket_id'];
        $st = $_POST['status'];
        $db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$st, $tId]);
        $msg = "Ticket status updated to $st.";
        $ticketId = $tId;
    }
}

if ($ticketId > 0) {
    $tStmt = $db->prepare("SELECT t.*, u.username, u.email FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $tStmt->execute([$ticketId]);
    $activeTicket = $tStmt->fetch();

    if ($activeTicket) {
        $mStmt = $db->prepare("SELECT tm.*, u.full_name, u.username, u.role FROM ticket_messages tm JOIN users u ON tm.user_id = u.id WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC");
        $mStmt->execute([$ticketId]);
        $messages = $mStmt->fetchAll();
    }
}

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$sql = "SELECT t.*, u.username, u.email FROM tickets t JOIN users u ON t.user_id = u.id WHERE 1=1";
$params = [];
if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY t.updated_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">Customer Support Tickets</h1>
    <p class="text-xs text-slate-500 mt-1">Review user inquiries and deliver customer assistance.</p>
  </div>
</div>

<?php if ($msg): ?>
  <div class="mb-4 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    <span><?= e($msg) ?></span>
  </div>
<?php endif; ?>

<?php if ($activeTicket): ?>
  <!-- Active Ticket Discussion Modal/Section -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm mb-6">
    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
      <div>
        <a href="/admin/tickets" class="text-xs font-bold text-rose-600 flex items-center gap-1 mb-1">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to all tickets
        </a>
        <h2 class="text-base font-bold text-slate-800">
          Ticket #<?= $activeTicket['id'] ?>: <?= e($activeTicket['subject']) ?>
          <span class="text-xs font-normal text-slate-400">(@<?= e($activeTicket['username']) ?>)</span>
        </h2>
      </div>

      <form method="POST" class="flex items-center gap-2">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
        <select name="status" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold">
          <option value="open" <?= $activeTicket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
          <option value="answered" <?= $activeTicket['status'] === 'answered' ? 'selected' : '' ?>>Answered</option>
          <option value="closed" <?= $activeTicket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
        <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-bold text-xs">Update</button>
      </form>
    </div>

    <!-- Messages -->
    <div class="space-y-3 mb-6 max-h-[400px] overflow-y-auto p-2 custom-scrollbar">
      <?php foreach ($messages as $msgItem): ?>
        <div class="p-4 rounded-2xl border <?= $msgItem['is_admin'] ? 'bg-rose-50/50 border-rose-200' : 'bg-slate-50 border-slate-100' ?>">
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-bold <?= $msgItem['is_admin'] ? 'text-rose-600' : 'text-slate-800' ?>">
              <?= $msgItem['is_admin'] ? 'RoseSMM Support Admin' : e($msgItem['username'] ?: 'Customer') ?>
            </span>
            <span class="text-[11px] text-slate-400"><?= date('d M Y, h:i A', strtotime($msgItem['created_at'])) ?></span>
          </div>
          <p class="text-xs text-slate-700 whitespace-pre-wrap leading-relaxed"><?= e($msgItem['message']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Admin Reply Box -->
    <form method="POST" class="space-y-3">
      <input type="hidden" name="action" value="admin_reply">
      <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
      <textarea 
        name="message" 
        rows="3" 
        required 
        placeholder="Type official support reply to user..." 
        class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs focus:outline-none focus:border-rose-500"
      ></textarea>
      <div class="flex justify-end">
        <button type="submit" class="px-6 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">
          Send Customer Reply
        </button>
      </div>
    </form>
  </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-sm mb-6 flex items-center gap-2 overflow-x-auto custom-scrollbar">
  <a href="/admin/tickets" class="px-4 py-1.5 rounded-full text-xs font-bold <?= $statusFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' ?>">All</a>
  <a href="/admin/tickets?status=open" class="px-4 py-1.5 rounded-full text-xs font-bold <?= $statusFilter === 'open' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600' ?>">Open</a>
  <a href="/admin/tickets?status=answered" class="px-4 py-1.5 rounded-full text-xs font-bold <?= $statusFilter === 'answered' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600' ?>">Answered</a>
  <a href="/admin/tickets?status=closed" class="px-4 py-1.5 rounded-full text-xs font-bold <?= $statusFilter === 'closed' ? 'bg-slate-500 text-white' : 'bg-slate-100 text-slate-600' ?>">Closed</a>
</div>

<!-- Ticket Card List (NO TABLE UI!) -->
<?php if (empty($tickets)): ?>
  <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center max-w-md mx-auto">
    <p class="text-xs text-slate-400">No support tickets found.</p>
  </div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($tickets as $t): ?>
      <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
          <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs shrink-0">
            #<?= $t['id'] ?>
          </div>
          <div>
            <h3 class="font-bold text-xs sm:text-sm text-slate-800"><?= e($t['subject']) ?></h3>
            <div class="text-[11px] text-slate-400 mt-0.5">
              User: <span class="font-bold text-slate-700">@<?= e($t['username']) ?></span> • Priority: <span class="capitalize font-semibold"><?= e($t['priority']) ?></span> • Updated: <?= date('d M Y, h:i A', strtotime($t['updated_at'])) ?>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between sm:justify-end gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
          <span class="px-3 py-1 rounded-full text-xs font-bold <?= $t['status'] === 'open' ? 'bg-amber-50 text-amber-600' : ($t['status'] === 'answered' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500') ?>">
            <?= ucfirst($t['status']) ?>
          </span>
          <a href="/admin/tickets?ticket_id=<?= $t['id'] ?>" class="px-4 py-1.5 rounded-full bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
            Respond
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
