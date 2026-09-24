<?php
$pageTitle = 'Ticket Automation - Admin Console';
$adminPage = 'ticket-automation';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/TicketAutomationHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle Global Automation Setting Toggle and Rule Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_global') {
        $enabled = !empty($_POST['ticket_automation_enabled']) ? '1' : '0';
        try {
            $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ticket_automation_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$enabled, $enabled]);
            $msg = 'Ticket automation status updated successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to update automation status: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'create_rule' || $_POST['action'] === 'edit_rule') {
        $name = trim($_POST['name'] ?? '');
        $triggerEvent = trim($_POST['trigger_event'] ?? 'ticket_created');
        $validTriggers = ['ticket_created', 'ticket_replied', 'status_changed'];
        if (!in_array($triggerEvent, $validTriggers)) {
            $triggerEvent = 'ticket_created';
        }

        $conditionMatchType = in_array($_POST['condition_match_type'] ?? '', ['all', 'any']) ? $_POST['condition_match_type'] : 'all';
        $keywordContains = trim($_POST['keyword_contains'] ?? $_POST['match_keyword'] ?? '');
        $priorityFilter = trim($_POST['priority_filter'] ?? $_POST['match_priority'] ?? 'all');
        if (empty($priorityFilter)) $priorityFilter = 'all';

        $categoryFilter = trim($_POST['category_filter'] ?? 'all');
        if (empty($categoryFilter)) $categoryFilter = 'all';

        $replyMessage = trim($_POST['reply_message'] ?? $_POST['action_reply'] ?? '');
        $actionAutoReply = !empty($replyMessage) ? 1 : 0;

        $actionChangeStatus = trim($_POST['action_change_status'] ?? $_POST['action_status'] ?? '');
        $validStatuses = ['open', 'answered', 'closed'];
        if (!in_array($actionChangeStatus, $validStatuses)) {
            $actionChangeStatus = null;
        }

        $actionChangePriority = trim($_POST['action_change_priority'] ?? $_POST['action_priority'] ?? '');
        $validPriorities = ['low', 'medium', 'high'];
        if (!in_array($actionChangePriority, $validPriorities)) {
            $actionChangePriority = null;
        }

        $isEnabled = !empty($_POST['is_enabled']) ? 1 : 0;
        $rulePriority = (int)($_POST['rule_priority'] ?? 0);
        $ruleId = (int)($_POST['rule_id'] ?? 0);

        if (empty($name)) {
            $error = 'Rule name is required.';
        } else {
            try {
                if ($_POST['action'] === 'create_rule') {
                    if ($rulePriority <= 0) {
                        $rulePriority = (int)$db->query("SELECT COALESCE(MAX(rule_priority), 0) + 1 FROM ticket_automation_rules")->fetchColumn();
                    }
                    $stmt = $db->prepare("
                        INSERT INTO ticket_automation_rules (
                            name, is_enabled, trigger_event, condition_match_type, keyword_contains, 
                            priority_filter, category_filter, action_auto_reply, reply_message, 
                            action_change_status, action_change_priority, rule_priority, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $name,
                        $isEnabled,
                        $triggerEvent,
                        $conditionMatchType,
                        $keywordContains,
                        $priorityFilter,
                        $categoryFilter,
                        $actionAutoReply,
                        $replyMessage ?: null,
                        $actionChangeStatus,
                        $actionChangePriority,
                        $rulePriority
                    ]);
                    $msg = "Automation rule '{$name}' created successfully.";
                } else {
                    $stmt = $db->prepare("
                        UPDATE ticket_automation_rules SET 
                            name = ?, is_enabled = ?, trigger_event = ?, condition_match_type = ?, 
                            keyword_contains = ?, priority_filter = ?, category_filter = ?, 
                            action_auto_reply = ?, reply_message = ?, action_change_status = ?, 
                            action_change_priority = ?, rule_priority = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name,
                        $isEnabled,
                        $triggerEvent,
                        $conditionMatchType,
                        $keywordContains,
                        $priorityFilter,
                        $categoryFilter,
                        $actionAutoReply,
                        $replyMessage ?: null,
                        $actionChangeStatus,
                        $actionChangePriority,
                        $rulePriority,
                        $ruleId
                    ]);
                    $msg = "Automation rule '{$name}' updated successfully.";
                }
            } catch (PDOException $e) {
                $error = 'Database error while saving automation rule: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'toggle_rule') {
        $ruleId = (int)$_POST['rule_id'];
        try {
            $db->prepare("UPDATE ticket_automation_rules SET is_enabled = IF(is_enabled=1, 0, 1) WHERE id = ?")->execute([$ruleId]);
            $msg = 'Rule status toggled successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to toggle rule: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_rule') {
        $ruleId = (int)$_POST['rule_id'];
        try {
            $db->prepare("DELETE FROM ticket_automation_rules WHERE id = ?")->execute([$ruleId]);
            $msg = 'Rule deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to delete rule: ' . $e->getMessage();
        }
    }
}

// Get global setting
$globStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'ticket_automation_enabled'");
$globStmt->execute();
$isGlobalEnabled = ($globStmt->fetchColumn() ?? '1') === '1';

// Fetch all rules
$rules = $db->query("SELECT * FROM ticket_automation_rules ORDER BY rule_priority ASC, id DESC")->fetchAll();

// Fetch recent automation execution logs
$logs = $db->query("
    SELECT l.*, t.subject, t.user_id, u.username
    FROM ticket_automation_logs l
    LEFT JOIN tickets t ON l.ticket_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY l.id DESC LIMIT 30
")->fetchAll();
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 border border-rose-200/60 text-rose-600 text-xs font-bold mb-2">
        <i data-lucide="bot" class="w-3.5 h-3.5"></i> Intelligent Support Auto-Pilot
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Ticket Automation System
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Configure automated rule-based triggers, instant intelligent replies, auto-assignments, and status transitions.
      </p>
    </div>

    <div class="flex items-center gap-3">
      <!-- Create Rule Button -->
      <button 
        type="button" 
        id="btn-create-automation-rule"
        onclick="openRuleModal()" 
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
      >
        <i data-lucide="plus" class="w-4 h-4"></i>
        <span>Create Automation Rule</span>
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

  <!-- Global Switch Card -->
  <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-700 font-bold">
        <i data-lucide="bot" class="w-6 h-6 text-rose-600"></i>
      </div>
      <div>
        <h3 class="text-sm font-black text-slate-800">Master Ticket Automation Switch</h3>
        <p class="text-xs text-slate-500 mt-0.5">
          <?= $isGlobalEnabled ? 'Automation is currently <span class="text-emerald-600 font-bold">ACTIVE</span> across all incoming support events.' : 'Automation is currently <span class="text-rose-600 font-bold">DISABLED</span>.' ?>
        </p>
      </div>
    </div>
    <form method="POST" class="flex items-center gap-3">
      <input type="hidden" name="action" value="toggle_global">
      <input type="hidden" name="ticket_automation_enabled" value="<?= $isGlobalEnabled ? '0' : '1' ?>">
      <button 
        type="submit" 
        class="px-5 py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors cursor-pointer <?= $isGlobalEnabled ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>"
      >
        <?= $isGlobalEnabled ? 'Disable Global Automation' : 'Enable Global Automation' ?>
      </button>
    </form>
  </div>

  <!-- Automation Rules Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Defined Automation Rules</h3>
        <p class="text-xs text-slate-500 mt-0.5">Rules are evaluated sequentially when a ticket is opened or answered.</p>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
        <?= count($rules) ?> Rules Configured
      </span>
    </div>

    <?php if (empty($rules)): ?>
      <div class="text-center py-12 text-slate-500 text-xs font-medium space-y-3">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
          <i data-lucide="bot" class="w-6 h-6"></i>
        </div>
        <p>No automation rules created yet.</p>
        <button 
          type="button" 
          onclick="openRuleModal()" 
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
        >
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span>Create Automation Rule</span>
        </button>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Rule Name</th>
              <th class="py-2.5 px-3">Trigger Event</th>
              <th class="py-2.5 px-3">Conditions</th>
              <th class="py-2.5 px-3">Automated Actions</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($rules as $r): ?>
              <tr>
                <td class="py-3.5 px-3">
                  <div class="font-bold text-slate-800"><?= e($r['name']) ?></div>
                  <div class="text-[10px] text-slate-400">Rule #<?= $r['id'] ?> • Order Priority: <?= (int)$r['rule_priority'] ?></div>
                </td>
                <td class="py-3.5 px-3">
                  <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-700">
                    <?= e(str_replace('_', ' ', $r['trigger_event'])) ?>
                  </span>
                </td>
                <td class="py-3.5 px-3 space-y-1">
                  <?php if (!empty($r['keyword_contains'])): ?>
                    <div>
                      <span class="font-mono bg-rose-50 text-rose-700 px-1.5 py-0.5 rounded border border-rose-200 font-bold text-[11px]">
                        "<?= e($r['keyword_contains']) ?>"
                      </span>
                      <span class="text-[10px] text-slate-400 ml-1">(<?= e($r['condition_match_type']) ?>)</span>
                    </div>
                  <?php else: ?>
                    <span class="text-slate-400">Any text</span>
                  <?php endif; ?>

                  <?php if (!empty($r['priority_filter']) && $r['priority_filter'] !== 'all'): ?>
                    <div class="text-[10px] text-slate-500">
                      Priority: <span class="font-bold uppercase text-slate-700"><?= e($r['priority_filter']) ?></span>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="py-3.5 px-3 space-y-1">
                  <?php if (!empty($r['reply_message'])): ?>
                    <div class="text-[11px] text-slate-600 truncate max-w-xs" title="<?= e($r['reply_message']) ?>">
                      <i data-lucide="message-square" class="w-3 h-3 inline text-rose-500 mr-1"></i> Auto-reply: "<?= e(substr($r['reply_message'], 0, 40)) ?><?= strlen($r['reply_message']) > 40 ? '...' : '' ?>"
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($r['action_change_status'])): ?>
                    <div class="text-[11px] text-slate-500">
                      <i data-lucide="check" class="w-3 h-3 inline text-emerald-500 mr-1"></i> Status → <strong class="uppercase text-emerald-700"><?= e($r['action_change_status']) ?></strong>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($r['action_change_priority'])): ?>
                    <div class="text-[11px] text-slate-500">
                      <i data-lucide="flag" class="w-3 h-3 inline text-amber-500 mr-1"></i> Priority → <strong class="uppercase text-amber-700"><?= e($r['action_change_priority']) ?></strong>
                    </div>
                  <?php endif; ?>
                  <?php if (empty($r['reply_message']) && empty($r['action_change_status']) && empty($r['action_change_priority'])): ?>
                    <span class="text-slate-400">No actions</span>
                  <?php endif; ?>
                </td>
                <td class="py-3.5 px-3">
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle_rule">
                    <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                    <button type="submit" class="cursor-pointer">
                      <?php if ($r['is_enabled']): ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                        </span>
                      <?php else: ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Disabled</span>
                      <?php endif; ?>
                    </button>
                  </form>
                </td>
                <td class="py-3.5 px-3 text-right space-x-2">
                  <button 
                    type="button" 
                    onclick='editRule(<?= json_encode($r) ?>)'
                    class="text-slate-600 hover:text-slate-900 font-bold cursor-pointer"
                  >
                    Edit
                  </button>
                  <form method="POST" class="inline" onsubmit="return confirm('Delete this automation rule?');">
                    <input type="hidden" name="action" value="delete_rule">
                    <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
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

  <!-- Automation Execution Logs -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Recent Automation Logs</h3>
        <p class="text-xs text-slate-500 mt-0.5">Audit trail of automated actions triggered by the engine.</p>
      </div>
      <span class="text-xs text-slate-400 font-semibold"><?= count($logs) ?> Recorded Events</span>
    </div>

    <?php if (empty($logs)): ?>
      <div class="text-center py-8 text-slate-400 text-xs">No automation triggers recorded yet.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Log ID</th>
              <th class="py-2.5 px-3">Ticket ID</th>
              <th class="py-2.5 px-3">User</th>
              <th class="py-2.5 px-3">Trigger Event</th>
              <th class="py-2.5 px-3">Actions Applied</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3">Executed At</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($logs as $l): ?>
              <tr>
                <td class="py-2.5 px-3 font-mono text-slate-400">#<?= $l['id'] ?></td>
                <td class="py-2.5 px-3 font-bold text-rose-600">
                  <a href="/admin/tickets?ticket_id=<?= $l['ticket_id'] ?>" class="hover:underline">
                    Ticket #<?= $l['ticket_id'] ?>
                  </a>
                </td>
                <td class="py-2.5 px-3 font-medium text-slate-700"><?= e($l['username'] ?? 'User') ?></td>
                <td class="py-2.5 px-3 font-semibold text-slate-600"><?= e($l['trigger_event']) ?></td>
                <td class="py-2.5 px-3 text-slate-600 font-mono text-[11px] max-w-xs truncate" title="<?= e($l['action_taken']) ?>">
                  <?= e($l['action_taken']) ?>
                </td>
                <td class="py-2.5 px-3">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700"><?= e(ucfirst($l['status'])) ?></span>
                </td>
                <td class="py-2.5 px-3 text-slate-400"><?= date('M d, H:i:s', strtotime($l['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Rule Create/Edit Modal -->
<div id="ruleModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl border border-slate-200" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h3 class="text-base font-black text-slate-800" id="ruleModalTitle">Create Automation Rule</h3>
      <button type="button" onclick="closeRuleModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" action="/admin/ticket-automation" id="ruleForm" class="space-y-4 text-xs">
      <input type="hidden" name="action" id="formAction" value="create_rule">
      <input type="hidden" name="rule_id" id="formRuleId" value="0">

      <div>
        <label class="block font-bold text-slate-700 mb-1">Rule Name <span class="text-rose-500">*</span></label>
        <input type="text" name="name" id="ruleName" required placeholder="e.g. Refill Keyword Auto-Responder" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-rose-400 font-semibold text-slate-800">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Trigger Event</label>
          <select name="trigger_event" id="ruleTriggerEvent" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold bg-white text-slate-800">
            <option value="ticket_created">When Ticket is Created</option>
            <option value="ticket_replied">When User Replies</option>
            <option value="status_changed">When Status Changes</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Match Priority Filter</label>
          <select name="priority_filter" id="rulePriorityFilter" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold bg-white text-slate-800">
            <option value="all">Any Priority</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="block font-bold text-slate-700 mb-1">Keyword / Substring (Optional)</label>
          <input type="text" name="keyword_contains" id="ruleKeywordContains" placeholder="e.g. refill, drop, cancel, refund, urgent" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-mono text-slate-800">
          <span class="text-[10px] text-slate-400 mt-1 block">Comma-separated keywords. Blank matches all.</span>
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Match Type</label>
          <select name="condition_match_type" id="ruleConditionMatchType" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold bg-white text-slate-800">
            <option value="all">Match All</option>
            <option value="any">Match Any</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Action: Automated Reply Message (Optional)</label>
        <textarea name="reply_message" id="ruleReplyMessage" rows="3" placeholder="Enter automated response to post into ticket thread..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-slate-800"></textarea>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Action: Change Status</label>
          <select name="action_change_status" id="ruleActionChangeStatus" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold bg-white text-slate-800">
            <option value="">Do not change status</option>
            <option value="open">Open</option>
            <option value="answered">Answered</option>
            <option value="closed">Closed</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Action: Escalate Priority</label>
          <select name="action_change_priority" id="ruleActionChangePriority" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 font-semibold bg-white text-slate-800">
            <option value="">Do not change priority</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-2 pt-2">
        <input type="checkbox" name="is_enabled" id="ruleIsEnabled" value="1" checked class="w-4 h-4 rounded text-rose-600">
        <label for="ruleIsEnabled" class="font-bold text-slate-700">Enable this rule immediately</label>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
        <button type="button" onclick="closeRuleModal()" class="px-4 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 cursor-pointer">
          Cancel
        </button>
        <button type="submit" id="ruleSubmitBtn" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-sm cursor-pointer">
          Create Automation Rule
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openRuleModal() {
  document.getElementById('ruleModalTitle').textContent = 'Create Automation Rule';
  document.getElementById('ruleSubmitBtn').textContent = 'Create Automation Rule';
  document.getElementById('formAction').value = 'create_rule';
  document.getElementById('formRuleId').value = '0';
  document.getElementById('ruleName').value = '';
  document.getElementById('ruleTriggerEvent').value = 'ticket_created';
  document.getElementById('rulePriorityFilter').value = 'all';
  document.getElementById('ruleKeywordContains').value = '';
  document.getElementById('ruleConditionMatchType').value = 'all';
  document.getElementById('ruleReplyMessage').value = '';
  document.getElementById('ruleActionChangeStatus').value = '';
  document.getElementById('ruleActionChangePriority').value = '';
  document.getElementById('ruleIsEnabled').checked = true;
  document.getElementById('ruleModal').classList.remove('hidden');
}

function closeRuleModal() {
  document.getElementById('ruleModal').classList.add('hidden');
}

function editRule(rule) {
  document.getElementById('ruleModalTitle').textContent = 'Edit Automation Rule';
  document.getElementById('ruleSubmitBtn').textContent = 'Save Changes';
  document.getElementById('formAction').value = 'edit_rule';
  document.getElementById('formRuleId').value = rule.id;
  document.getElementById('ruleName').value = rule.name || '';
  document.getElementById('ruleTriggerEvent').value = rule.trigger_event || 'ticket_created';
  document.getElementById('rulePriorityFilter').value = rule.priority_filter || 'all';
  document.getElementById('ruleKeywordContains').value = rule.keyword_contains || '';
  document.getElementById('ruleConditionMatchType').value = rule.condition_match_type || 'all';
  document.getElementById('ruleReplyMessage').value = rule.reply_message || '';
  document.getElementById('ruleActionChangeStatus').value = rule.action_change_status || '';
  document.getElementById('ruleActionChangePriority').value = rule.action_change_priority || '';
  document.getElementById('ruleIsEnabled').checked = (rule.is_enabled == 1);
  document.getElementById('ruleModal').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const path = window.location.pathname;
  if (urlParams.get('action') === 'create' || path.endsWith('/create') || urlParams.get('create') === '1') {
    openRuleModal();
  }
});

document.getElementById('ruleModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeRuleModal();
  }
});
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
