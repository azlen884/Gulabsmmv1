<?php
$pageTitle = 'Cron Job Automation - Admin Console';
$adminPage = 'cron-jobs';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../includes/CronJobHelper.php';

$db = getDB();
$msg = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_global') {
        $enabled = !empty($_POST['cron_automation_enabled']) ? '1' : '0';
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('cron_automation_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
            ->execute([$enabled, $enabled]);
        $msg = 'Cron automation setting saved.';
    } elseif ($_POST['action'] === 'toggle_job') {
        $jobId = (int)$_POST['job_id'];
        $db->prepare("UPDATE cron_jobs SET is_enabled = IF(is_enabled=1, 0, 1) WHERE id = ?")->execute([$jobId]);
        $msg = 'Cron job status toggled.';
    } elseif ($_POST['action'] === 'update_interval') {
        $jobId = (int)$_POST['job_id'];
        $mins = max(1, (int)$_POST['interval_minutes']);
        $db->prepare("UPDATE cron_jobs SET interval_minutes = ? WHERE id = ?")->execute([$mins, $jobId]);
        $msg = 'Cron interval updated.';
    }
}

// Global cron setting
$globStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'cron_automation_enabled'");
$globStmt->execute();
$isGlobalEnabled = ($globStmt->fetchColumn() ?? '1') === '1';

// Fetch all cron jobs
$jobs = $db->query("SELECT * FROM cron_jobs ORDER BY id ASC")->fetchAll();

// Fetch recent execution logs
$logs = $db->query("
    SELECT l.*, c.name AS job_name
    FROM cron_logs l
    LEFT JOIN cron_jobs c ON l.cron_job_id = c.id
    ORDER BY l.id DESC LIMIT 40
")->fetchAll();

$cronToken = 'rose_cron_secret_key_2026';
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/60 text-emerald-700 text-xs font-bold mb-2">
        <i data-lucide="clock-4" class="w-3.5 h-3.5"></i> Automated Background Dispatcher
      </div>
      <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
        Cron Job Automation
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        Centralized management for provider synchronization, auto-refill, drip-feed delivery, refund dispatch, and flash sale checks.
      </p>
    </div>

    <!-- Run All Button -->
    <div class="flex items-center gap-3">
      <button 
        type="button" 
        onclick="runCronTask('all')"
        id="runAllBtn"
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer"
      >
        <i data-lucide="play" class="w-4 h-4 text-emerald-400"></i>
        <span>Execute Full Master Cron</span>
      </button>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
      <span><?= e($msg) ?></span>
    </div>
  <?php endif; ?>

  <!-- Master Switch Card -->
  <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 font-bold border border-emerald-100">
        <i data-lucide="cpu" class="w-6 h-6"></i>
      </div>
      <div>
        <h3 class="text-sm font-black text-slate-800">Master Automated Cron Runner</h3>
        <p class="text-xs text-slate-500 mt-0.5">
          Background Node daemon runs <code class="bg-slate-100 px-1.5 py-0.5 rounded text-rose-600 font-bold">php cron.php</code> continuously every 60s.
        </p>
      </div>
    </div>
    <form method="POST" class="flex items-center gap-3">
      <input type="hidden" name="action" value="toggle_global">
      <input type="hidden" name="cron_automation_enabled" value="<?= $isGlobalEnabled ? '0' : '1' ?>">
      <button 
        type="submit" 
        class="px-5 py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors cursor-pointer <?= $isGlobalEnabled ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>"
      >
        <?= $isGlobalEnabled ? 'Disable Global Cron' : 'Enable Global Cron' ?>
      </button>
    </form>
  </div>

  <!-- Cron Jobs Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Scheduled Automation Tasks</h3>
        <p class="text-xs text-slate-500 mt-0.5">Each scheduled job handles isolated background routines.</p>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
        <?= count($jobs) ?> Active Tasks
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
            <th class="py-2.5 px-3">Task Name</th>
            <th class="py-2.5 px-3">Task Key</th>
            <th class="py-2.5 px-3">Interval</th>
            <th class="py-2.5 px-3">Last Run</th>
            <th class="py-2.5 px-3">Runs Count</th>
            <th class="py-2.5 px-3">Status</th>
            <th class="py-2.5 px-3 text-right">Instant Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($jobs as $j): ?>
            <tr>
              <td class="py-3.5 px-3">
                <div class="font-bold text-slate-800"><?= e($j['name']) ?></div>
                <div class="text-[10px] text-slate-400"><?= e($j['description']) ?></div>
              </td>
              <td class="py-3.5 px-3 font-mono font-bold text-rose-600">
                <?= e($j['task_key']) ?>
              </td>
              <td class="py-3.5 px-3">
                <form method="POST" class="flex items-center gap-1.5">
                  <input type="hidden" name="action" value="update_interval">
                  <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                  <input 
                    type="number" 
                    name="interval_minutes" 
                    value="<?= $j['interval_minutes'] ?>" 
                    min="1" 
                    max="1440"
                    class="w-16 px-2 py-1 rounded-lg border border-slate-200 text-xs font-bold text-slate-700 text-center"
                  >
                  <button type="submit" class="text-[10px] text-slate-500 hover:text-slate-800 font-bold px-1.5 py-1 bg-slate-100 rounded">
                    min
                  </button>
                </form>
              </td>
              <td class="py-3.5 px-3 text-slate-500">
                <?= !empty($j['last_run_at']) ? date('M d, H:i:s', strtotime($j['last_run_at'])) : '<span class="text-slate-400">Never</span>' ?>
              </td>
              <td class="py-3.5 px-3 font-bold text-slate-700">
                <?= number_format($j['total_runs']) ?>
              </td>
              <td class="py-3.5 px-3">
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="toggle_job">
                  <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                  <button type="submit" class="cursor-pointer">
                    <?php if ($j['is_enabled']): ?>
                      <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Enabled
                      </span>
                    <?php else: ?>
                      <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Disabled</span>
                    <?php endif; ?>
                  </button>
                </form>
              </td>
              <td class="py-3.5 px-3 text-right">
                <button 
                  type="button" 
                  onclick="runCronTask('<?= e($j['task_key']) ?>')"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 font-bold text-xs transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                >
                  <i data-lucide="play" class="w-3.5 h-3.5"></i>
                  <span>Run Now</span>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Server Crontab Manual Setup Card -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-3">
    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
      <i data-lucide="terminal" class="w-4 h-4 text-emerald-600"></i>
      Production Crontab Setup Command
    </h3>
    <p class="text-xs text-slate-500">
      If deploying to external hosting or cPanel, configure your server's native crontab to execute this command every minute:
    </p>
    <div class="p-3 bg-slate-900 rounded-2xl font-mono text-xs text-emerald-400 overflow-x-auto flex items-center justify-between">
      <code>* * * * * php <?= __DIR__ ?>/../../cron.php >> /dev/null 2>&1</code>
      <button 
        type="button" 
        onclick="navigator.clipboard.writeText('* * * * * php <?= __DIR__ ?>/../../cron.php >> /dev/null 2>&1'); alert('Copied crontab command to clipboard!');"
        class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-white font-bold ml-2 shrink-0 cursor-pointer"
      >
        Copy
      </button>
    </div>
  </div>

  <!-- Execution Logs Table -->
  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <div>
        <h3 class="text-base font-black text-slate-800">Cron Job Execution History</h3>
        <p class="text-xs text-slate-500 mt-0.5">Real-time log of recent background task executions.</p>
      </div>
      <button type="button" onclick="location.reload()" class="text-xs text-rose-500 hover:text-rose-600 font-bold inline-flex items-center gap-1 cursor-pointer">
        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh Logs
      </button>
    </div>

    <?php if (empty($logs)): ?>
      <div class="text-center py-8 text-slate-400 text-xs">No execution history recorded yet.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
              <th class="py-2.5 px-3">Log ID</th>
              <th class="py-2.5 px-3">Task</th>
              <th class="py-2.5 px-3">Status</th>
              <th class="py-2.5 px-3">Duration</th>
              <th class="py-2.5 px-3">Output Summary</th>
              <th class="py-2.5 px-3">Executed At</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($logs as $l): ?>
              <tr>
                <td class="py-2.5 px-3 font-mono text-slate-400">#<?= $l['id'] ?></td>
                <td class="py-2.5 px-3 font-bold text-slate-800">
                  <?= e($l['job_name'] ?: ($l['task_key'] ?? ('Task #' . $l['cron_job_id']))) ?>
                </td>
                <td class="py-2.5 px-3">
                  <?php if ($l['status'] === 'success'): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Success</span>
                  <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">Failed</span>
                  <?php endif; ?>
                </td>
                <td class="py-2.5 px-3 font-mono text-slate-600">
                  <?= $l['duration_ms'] ?? $l['execution_time_ms'] ?? 0 ?> ms
                </td>
                <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 max-w-sm truncate" title="<?= e($l['output']) ?>">
                  <?= e($l['output']) ?>
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

<!-- Output Terminal Modal -->
<div id="outputModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
  <div class="bg-slate-950 text-slate-200 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl border border-slate-800">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
      <div class="flex items-center gap-2">
        <div class="w-3 h-3 rounded-full bg-rose-500"></div>
        <div class="w-3 h-3 rounded-full bg-amber-500"></div>
        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
        <span class="text-xs font-mono text-slate-400 ml-2">Cron Task Execution Console</span>
      </div>
      <button type="button" onclick="closeOutputModal()" class="text-slate-400 hover:text-white cursor-pointer">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <pre id="terminalOutput" class="p-4 rounded-2xl bg-slate-900 font-mono text-xs text-emerald-400 max-h-80 overflow-y-auto custom-scrollbar whitespace-pre-wrap leading-relaxed">Running task...</pre>

    <div class="flex justify-end">
      <button type="button" onclick="closeOutputModal(); location.reload();" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs cursor-pointer">
        Close & Refresh Table
      </button>
    </div>
  </div>
</div>

<script>
function runCronTask(taskKey) {
  const modal = document.getElementById('outputModal');
  const term = document.getElementById('terminalOutput');
  modal.classList.remove('hidden');
  term.textContent = 'Executing task [' + taskKey + '] via secure runner...';

  const url = taskKey === 'all' 
    ? '/cron.php?token=<?= $cronToken ?>' 
    : '/cron.php?task=' + encodeURIComponent(taskKey) + '&token=<?= $cronToken ?>';

  fetch(url)
    .then(r => r.json())
    .then(data => {
      term.textContent = JSON.stringify(data, null, 2);
    })
    .catch(err => {
      term.textContent = 'Error executing cron task: ' + err.message;
    });
}

function closeOutputModal() {
  document.getElementById('outputModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
