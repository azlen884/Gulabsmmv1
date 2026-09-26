<?php
$pageTitle = 'Support Tickets - SMM Pro';
$activePage = 'support';
require_once __DIR__ . '/../layouts/header.php';

$db = getDB();
$userId = $user['id'];

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

$allTicketsStmt = $db->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC");
$allTicketsStmt->execute([$userId]);
$tickets = $allTicketsStmt->fetchAll();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="help-circle" class="w-6 h-6 text-[#FF2D78]"></i>
        Support Tickets
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">Direct priority access to our 24/7 technical and fulfillment team.</p>
    </div>
    <button onclick="document.getElementById('new-ticket-modal').classList.remove('hidden')" class="smm-btn-pink px-5 py-2.5 text-xs font-bold">
      <i data-lucide="plus-circle" class="w-4 h-4"></i>
      <span>New Ticket</span>
    </button>
  </div>

  <?php if ($activeTicket): ?>
    <!-- Active Ticket Discussion Card -->
    <div class="smm-card p-6 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/5 pb-4">
        <div>
          <a href="/support" class="text-xs font-bold text-[#FF2D78] hover:underline flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to all tickets
          </a>
          <h2 class="text-lg font-black text-white flex items-center gap-2">
            <span>Ticket #<?= $activeTicket['id'] ?>: <?= e($activeTicket['subject']) ?></span>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase <?= $activeTicket['status'] === 'open' ? 'bg-[#F59E0B]/15 text-[#FBBF24]' : ($activeTicket['status'] === 'answered' ? 'bg-[#10B981]/15 text-[#34D399]' : 'bg-white/10 text-[#9D9DB8]') ?>">
              <?= ucfirst($activeTicket['status']) ?>
            </span>
          </h2>
        </div>
        <div class="text-xs text-[#9D9DB8]">
          Priority: <span class="font-bold text-white capitalize"><?= e($activeTicket['priority']) ?></span>
        </div>
      </div>

      <!-- Messages Stream -->
      <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
        <?php foreach ($messages as $msg): 
          $isAdmin = ($msg['role'] === 'admin');
        ?>
          <div class="flex flex-col <?= $isAdmin ? 'items-start' : 'items-end' ?>">
            <div class="max-w-xl rounded-2xl p-4 <?= $isAdmin ? 'bg-[#18182D] border border-white/10 text-white' : 'bg-gradient-to-r from-[#FF2D78] to-[#D91B5C] text-white shadow-md shadow-[#FF2D78]/20' ?>">
              <div class="flex items-center gap-2 mb-1.5 text-[10px] <?= $isAdmin ? 'text-[#9D9DB8]' : 'text-white/80' ?>">
                <span class="font-bold"><?= e($msg['full_name']) ?> <?= $isAdmin ? '(Support Agent)' : '(You)' ?></span>
                <span>•</span>
                <span><?= date('M d, H:i', strtotime($msg['created_at'])) ?></span>
              </div>
              <p class="text-xs leading-relaxed whitespace-pre-wrap"><?= e($msg['message']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Reply Box -->
      <?php if ($activeTicket['status'] !== 'closed'): ?>
        <form onsubmit="handleReplySubmit(event, <?= (int)$activeTicket['id'] ?>)" class="pt-4 border-t border-white/5 space-y-3">
          <textarea id="reply-message" rows="3" required placeholder="Type your reply here..." class="smm-input text-xs"></textarea>
          <div class="flex justify-end">
            <button type="submit" id="reply-btn" class="smm-btn-pink px-5 py-2 text-xs">
              <span>Send Reply</span>
              <i data-lucide="send" class="w-3.5 h-3.5"></i>
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Tickets List -->
  <div class="smm-card overflow-hidden">
    <div class="p-5 border-b border-white/5 flex items-center justify-between">
      <h2 class="text-base font-black text-white flex items-center gap-2">
        <i data-lucide="message-square" class="w-5 h-5 text-[#FF2D78]"></i>
        Your Tickets
      </h2>
      <span class="text-xs text-[#9D9DB8]"><?= count($tickets) ?> Ticket(s)</span>
    </div>

    <div class="overflow-x-auto">
      <table class="smm-table">
        <thead>
          <tr>
            <th>Ticket ID</th>
            <th>Subject</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Last Updated</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tickets)): ?>
            <tr>
              <td colspan="7" class="text-center py-10 text-[#9D9DB8]">
                No support tickets found. Create a ticket if you need any help.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($tickets as $t): ?>
              <tr>
                <td class="font-mono text-xs font-bold text-[#FF2D78]">
                  #<?= (int)$t['id'] ?>
                </td>
                <td class="font-bold text-white max-w-xs truncate">
                  <?= e($t['subject']) ?>
                </td>
                <td class="capitalize text-xs text-[#9D9DB8]">
                  <?= e($t['category'] ?? 'General') ?>
                </td>
                <td class="capitalize text-xs font-semibold text-white">
                  <?= e($t['priority']) ?>
                </td>
                <td>
                  <span class="smm-badge-<?= $t['status'] === 'closed' ? 'canceled' : ($t['status'] === 'answered' ? 'completed' : 'pending') ?>">
                    <?= ucfirst($t['status']) ?>
                  </span>
                </td>
                <td class="text-xs text-[#9D9DB8]">
                  <?= date('M d, Y H:i', strtotime($t['updated_at'])) ?>
                </td>
                <td>
                  <a href="/support?ticket_id=<?= (int)$t['id'] ?>" class="smm-btn-dark px-3 py-1 text-xs">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- New Ticket Modal -->
<div id="new-ticket-modal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="w-full max-w-lg smm-card p-6 sm:p-7 space-y-4">
    <div class="flex items-center justify-between border-b border-white/5 pb-3">
      <h3 class="text-base font-black text-white flex items-center gap-2">
        <i data-lucide="plus-circle" class="w-5 h-5 text-[#FF2D78]"></i>
        Create Support Ticket
      </h3>
      <button onclick="document.getElementById('new-ticket-modal').classList.add('hidden')" class="p-1 rounded-lg text-[#9D9DB8] hover:text-white">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form onsubmit="handleNewTicketSubmit(event)" class="space-y-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Subject</label>
        <input type="text" id="new-ticket-subject" required placeholder="e.g. Order #1234 inquiry or Payment issue" class="smm-input text-xs">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Category</label>
          <select id="new-ticket-category" class="smm-input text-xs">
            <option value="order">Order Issue</option>
            <option value="payment">Payment & Billing</option>
            <option value="refill">Refill Request</option>
            <option value="general">General / Other</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Priority</label>
          <select id="new-ticket-priority" class="smm-input text-xs">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High (Urgent)</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Detailed Message</label>
        <textarea id="new-ticket-message" rows="4" required placeholder="Please provide your Order ID, Link, or transaction details..." class="smm-input text-xs"></textarea>
      </div>

      <div class="pt-3 border-t border-white/5 flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('new-ticket-modal').classList.add('hidden')" class="smm-btn-dark px-4 py-2 text-xs">
          Cancel
        </button>
        <button type="submit" id="submit-ticket-btn" class="smm-btn-pink px-5 py-2 text-xs">
          Submit Ticket
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function handleNewTicketSubmit(e) {
  e.preventDefault();
  const btn = document.getElementById('submit-ticket-btn');
  const subject = document.getElementById('new-ticket-subject').value.trim();
  const category = document.getElementById('new-ticket-category').value;
  const priority = document.getElementById('new-ticket-priority').value;
  const message = document.getElementById('new-ticket-message').value.trim();

  btn.disabled = true;
  btn.textContent = 'Submitting...';

  fetch('/api/tickets/create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ subject, category, priority, message })
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.textContent = 'Submit Ticket';
    if (data.success) {
      window.location.href = '/support?ticket_id=' + data.ticket_id;
    } else {
      alert(data.error || 'Failed to create ticket.');
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.textContent = 'Submit Ticket';
    alert('Communication error creating ticket.');
  });
}

function handleReplySubmit(e, ticketId) {
  e.preventDefault();
  const btn = document.getElementById('reply-btn');
  const msgInput = document.getElementById('reply-message');
  const message = msgInput.value.trim();
  if (!message) return;

  btn.disabled = true;
  btn.textContent = 'Sending...';

  fetch('/api/tickets/reply', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ticket_id: ticketId, message })
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<span>Send Reply</span>';
    if (data.success) {
      window.location.reload();
    } else {
      alert(data.error || 'Failed to send reply.');
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<span>Send Reply</span>';
    alert('Communication error sending reply.');
  });
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
