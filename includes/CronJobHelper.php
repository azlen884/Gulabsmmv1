<?php
/**
 * RoseSMM - Central Cron Job Automation Engine
 * Coordinates automated background executions for Refill, Refund, Drip-Feed, Flash Sales, and Tickets.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/TicketAutomationHelper.php';
require_once __DIR__ . '/RefillHelper.php';
require_once __DIR__ . '/RefundHelper.php';
require_once __DIR__ . '/DripFeedHelper.php';
require_once __DIR__ . '/FlashSaleHelper.php';
require_once __DIR__ . '/OrderStatusHelper.php';

class CronJobHelper {

    /**
     * Execute a specific cron task by its key
     */
    public static function runTask($taskKey) {
        $db = getDB();
        $startTime = microtime(true);

        $stmt = $db->prepare("SELECT * FROM cron_jobs WHERE task_key = ?");
        $stmt->execute([$taskKey]);
        $job = $stmt->fetch();

        if (!$job) {
            return ['success' => false, 'error' => "Cron task '{$taskKey}' not found"];
        }

        // Set status to running
        $db->prepare("UPDATE cron_jobs SET last_status = 'running' WHERE id = ?")->execute([$job['id']]);

        $output = '';
        $isSuccess = true;
        $errorMessage = null;

        try {
            switch ($taskKey) {
                case 'order_status':
                case 'order_sync':
                case 'sync_orders':
                    $res = OrderStatusHelper::syncProviderOrders();
                    $output = $res['message'];
                    if (!empty($res['logs'])) {
                        $output .= " Details: " . implode(" | ", array_slice($res['logs'], 0, 5));
                    }
                    break;

                case 'auto_refill':
                    $updated = RefillHelper::syncPendingRefills();
                    $output = "Processed pending refill requests. {$updated} refills synced with upstream providers.";
                    break;

                case 'auto_refund':
                    $res = RefundHelper::sweepAutoRefunds();
                    $output = "Processed auto-refunds. {$res['processed']} orders refunded (Total: $" . number_format($res['total_amount'], 2) . ").";
                    break;

                case 'drip_feed':
                    $batches = DripFeedHelper::processDueBatches();
                    $output = "Executed due drip-feed batches. {$batches} order batches submitted.";
                    break;

                case 'flash_sale':
                    // Check for sales that reached limit or expired
                    $db->query("
                        UPDATE flash_sales 
                        SET is_enabled = 0 
                        WHERE is_enabled = 1 AND (ends_at <= NOW() OR (sale_limit > 0 AND sales_count >= sale_limit))
                    ");
                    $activeCount = count(FlashSaleHelper::getActiveFlashSales());
                    $output = "Flash sales verified. {$activeCount} active flash sales running.";
                    break;

                case 'ticket_automation':
                    $closed = TicketAutomationHelper::sweepInactiveTickets();
                    $output = "Ticket automation sweep complete. {$closed} inactive answered tickets closed.";
                    break;

                default:
                    throw new Exception("Unknown task handler: {$taskKey}");
            }
        } catch (Exception $e) {
            $isSuccess = false;
            $errorMessage = $e->getMessage();
            $output = "Task failed with exception: " . $e->getMessage();
        }

        $durationMs = round((microtime(true) - $startTime) * 1000);
        $statusStr = $isSuccess ? 'success' : 'failed';

        // Calculate next run
        $intervalMins = max(1, (int)$job['interval_minutes']);
        $nextRun = date('Y-m-d H:i:s', time() + ($intervalMins * 60));

        // Update cron_jobs record
        $db->prepare("
            UPDATE cron_jobs 
            SET last_run_at = NOW(), 
                next_run_at = ?, 
                last_status = ?, 
                last_error = ?, 
                execution_count = execution_count + 1 
            WHERE id = ?
        ")->execute([$nextRun, $statusStr, $errorMessage, $job['id']]);

        // Insert into cron_logs
        $db->prepare("
            INSERT INTO cron_logs (cron_job_id, task_key, status, output, error_message, duration_ms, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$job['id'], $taskKey, $statusStr, $output, $errorMessage, $durationMs]);

        return [
            'success' => $isSuccess,
            'task_key' => $taskKey,
            'status' => $statusStr,
            'output' => $output,
            'error' => $errorMessage,
            'duration_ms' => $durationMs,
            'next_run_at' => $nextRun
        ];
    }

    /**
     * Run all active cron jobs that are due
     */
    public static function runAllDue() {
        $db = getDB();

        if (get_setting('cron_automation_enabled', '1') !== '1') {
            return ['success' => false, 'message' => 'Cron automation is globally disabled'];
        }

        $stmt = $db->query("
            SELECT task_key FROM cron_jobs 
            WHERE is_enabled = 1 
              AND (next_run_at IS NULL OR next_run_at <= NOW())
            ORDER BY id ASC
        ");
        $dueTasks = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($dueTasks as $taskKey) {
            $results[$taskKey] = self::runTask($taskKey);
        }

        return [
            'success' => true,
            'executed_count' => count($results),
            'tasks' => $results
        ];
    }
}
