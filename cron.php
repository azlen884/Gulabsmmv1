#!/usr/bin/env php
<?php
/**
 * RoseSMM - Master Cron Job Entrypoint
 * Can be executed via CLI: `php cron.php` or `php cron.php order_status`
 * Or via HTTP / cPanel Web Cron: `GET /cron.php?task=order_status`
 */

// Safeguards for cron executions
@set_time_limit(300);
@ini_set('memory_limit', '256M');
if (function_exists('ignore_user_abort')) {
    @ignore_user_abort(true);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/CronJobHelper.php';

// CLI or Web execution
$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

// Parse task parameter from Web query string or CLI arguments
$task = $_GET['task'] ?? null;

if ($isCli && isset($argv) && is_array($argv)) {
    for ($i = 1; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);
        if (strpos($arg, '--task=') === 0) {
            $task = substr($arg, 7);
        } elseif (strpos($arg, 'task=') === 0) {
            $task = substr($arg, 5);
        } elseif ($arg !== '' && $arg[0] !== '-') {
            $task = $arg;
        }
    }
}

try {
    if (!empty($task)) {
        $res = CronJobHelper::runTask($task);
        if ($isCli) {
            echo "[" . date('Y-m-d H:i:s') . "] Task '{$task}' executed: " . ($res['status'] ?? 'unknown') . " (" . ($res['duration_ms'] ?? 0) . "ms)\n";
            if (!empty($res['output'])) echo "Output: " . $res['output'] . "\n";
            if (!empty($res['error'])) echo "Error: " . $res['error'] . "\n";
        } else {
            echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    } else {
        $res = CronJobHelper::runAllDue();
        if ($isCli) {
            echo "[" . date('Y-m-d H:i:s') . "] Executed " . ($res['executed_count'] ?? 0) . " due cron tasks.\n";
            foreach (($res['tasks'] ?? []) as $t => $info) {
                echo "  - {$t}: " . ($info['status'] ?? 'unknown') . " (" . ($info['duration_ms'] ?? 0) . "ms)\n";
            }
        } else {
            echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
} catch (Throwable $e) {
    $errRes = [
        'success' => false,
        'status' => 'failed',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    if ($isCli) {
        echo "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode($errRes, JSON_PRETTY_PRINT);
        exit;
    }
}
