<?php
/**
 * RoseSMM - Master Cron Job Entrypoint
 * Can be executed via CLI: `php cron.php` or HTTP: `GET /cron.php?task=auto_refill`
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/CronJobHelper.php';

// CLI or Web execution
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json');
}

$task = $_GET['task'] ?? null;
if ($isCli && isset($argv[1])) {
    $task = $argv[1];
}

if ($task) {
    $res = CronJobHelper::runTask($task);
    if ($isCli) {
        echo "[" . date('Y-m-d H:i:s') . "] Task '{$task}' executed: {$res['status']} ({$res['duration_ms']}ms)\n";
        echo "Output: {$res['output']}\n";
        if (!empty($res['error'])) echo "Error: {$res['error']}\n";
    } else {
        echo json_encode($res, JSON_PRETTY_PRINT);
    }
} else {
    $res = CronJobHelper::runAllDue();
    if ($isCli) {
        echo "[" . date('Y-m-d H:i:s') . "] Executed {$res['executed_count']} due cron tasks.\n";
        foreach (($res['tasks'] ?? []) as $t => $info) {
            echo "  - {$t}: {$info['status']} ({$info['duration_ms']}ms)\n";
        }
    } else {
        echo json_encode($res, JSON_PRETTY_PRINT);
    }
}
