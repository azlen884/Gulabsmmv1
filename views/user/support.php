<?php
$pageTitle = 'Support Tickets - RoseSMM';
$activePage = 'support';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = $user['id'];

// If viewing a specific ticket
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$activeTicket = null;
$messages = [];

if ($ticketId > 0) {
    $tStmt = $db->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
    $tStmt->execute([$ticketId, $userId]);
    $activeTicket = $tStmt->fetch();

    if ($activeTicket) {
        $mStmt = $db->prepare("SELECT tm.*, u.full_name, u.role FROM ticket_messages tm JOIN users u ON tm.user_id = u.id WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC");
        $mStmt->execute([$ticketId]);
        $messages = $mStmt->fetchAll();
    }
}

// Fetch all tickets for user
$allTicketsStmt = $db->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC");
$allTicketsStmt->execute([$userId]);
$tickets = $allTicketsStmt->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Customer Support</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Our dedicated team is ready 24/7 to assist with your orders and questions.</p>
  </div>
  <button onclick="document.getElementById('new-ticket-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs sm:text-sm shadow-sm transition-colors">
    <i data-lucide="plus-circle" class="w-4 h-4"></i>
    <span>Create New Ticket</span>
  </button>
</div>

<?php if ($activeTicket): ?>
  <!-- Active Ticket Discussion View -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm mb-6">
    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
      <div>
        <a href="/support" class="text-xs font-bold text-rose-500 hover:text-rose-600 flex items-center gap-1 mb-2">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to all tickets
        </a>
        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2.5">
          <span>Ticket #<?= $activeTicket['id'] ?>: <?= e($activeTicket['subject']) ?></span>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $activeTicket['status'] === 'open' ? 'bg-amber-50 text-amber-600' : ($activeTicket['status'] === 'answered' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500') ?>">
            <?= ucfirst($activeTicket['status']) ?>
          </span>
        </h2>
      </div>
      <div class="text-xs text-slate-400">
        Priority: <span class="font-bold text-slate-700 capitalize"><?= e($activeTicket['priority']) ?></span>
      </div>
    </div>

    <!-- Message Thread -->
    <div class="space-y-4 mb-6 max-h-[450px] overflow-y-auto p-2 custom-scrollbar">
      <?php foreach ($messages as $msg): ?>
        <div class="flex items-start gap-3 <?= $msg['is_admin'] ? 'bg-rose-50/50' : 'bg-slate-50' ?> p-4 rounded-2xl border border-slate-100">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs text-white shrink-0 <?= $msg['is_admin'] ? 'bg-rose-500' : 'bg-slate-700' ?>">
            <?= $msg['is_admin'] ? 'ADM' : 'YOU' ?>
          </div>
          <div class="flex-1">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-bold text-slate-800">
                <?= $msg['is_admin'] ? 'RoseSMM Support Agent' : e($msg['full_name'] ?: 'You') ?>
              </span>
              <span class="text-[10px] text-slate-400">
                <?= date('d M Y, h:i A', strtotime($msg['created_at'])) ?>
              </span>
            </div>
            <p class="text-xs text-slate-600 whitespace-pre-wrap leading-relaxed"><?= e($msg['message']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Reply Box -->
    <form action="/api/tickets/reply" method="POST" class="space-y-3">
      <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
      <textarea 
        name="message" 
        rows="3" 
        required 
        placeholder="Type your response here..." 
        class="w-full p-4 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs text-slate-700 focus:outline-none focus:border-rose-400"
      ></textarea>
      <div class="flex justify-end">
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors">
          Send Reply
        </button>
      </div>
    </form>
  </div>
<?php endif; ?>

<!-- Tickets List Cards (NO TABLE UI!) -->
<div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
  <h3 class="font-bold text-base text-slate-800 mb-4">Your Tickets</h3>

  <?php if (empty($tickets)): ?>
    <div class="text-center py-10">
      <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-2">
        <i data-lucide="message-square" class="w-6 h-6"></i>
      </div>
      <p class="text-xs text-slate-400">You haven't opened any support tickets yet.</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($tickets as $t): ?>
        <a href="/support?ticket_id=<?= $t['id'] ?>" class="p-4 rounded-2xl border border-slate-100 hover:border-rose-300 hover:bg-rose-50/20 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 block">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0 font-bold text-xs">
              #<?= $t['id'] ?>
            </div>
            <div>
              <div class="font-bold text-xs sm:text-sm text-slate-800">
                <?= e($t['subject']) ?>
              </div>
              <div class="text-[11px] text-slate-400 mt-0.5">
                Updated: <?= date('d M Y, h:i A', strtotime($t['updated_at'])) ?> • Priority: <span class="capitalize font-semibold text-slate-600"><?= e($t['priority']) ?></span>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between sm:justify-end gap-3">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $t['status'] === 'open' ? 'bg-amber-50 text-amber-600' : ($t['status'] === 'answered' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500') ?>">
              <?= ucfirst($t['status']) ?>
            </span>
            <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Create New Ticket Modal -->
<div id="new-ticket-modal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 border border-[#FCE4E8] shadow-2xl relative">
    <button onclick="document.getElementById('new-ticket-modal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>

    <h2 class="text-lg font-bold text-slate-800 mb-1">Create Support Ticket</h2>
    <p class="text-xs text-slate-400 mb-5">Please describe your inquiry with details so we can help quickly.</p>

    <form action="/api/tickets/create" method="POST" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Subject</label>
        <input 
          type="text" 
          name="subject" 
          required 
          placeholder="e.g. Order #1234 delivery speed" 
          class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Priority</label>
        <select name="priority" class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High - Urgent</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Message</label>
        <textarea 
          name="message" 
          rows="4" 
          required 
          placeholder="Please include links, transaction ID or order ID..." 
          class="w-full p-4 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400"
        ></textarea>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('new-ticket-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm">Submit Ticket</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
